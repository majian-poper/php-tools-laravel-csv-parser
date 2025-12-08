<?php

namespace PHPTools\LaravelCsvParser\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PHPTools\LaravelCsvParser\Contracts\CsvFile;
use PHPTools\LaravelCsvParser\Contracts\HasUniqueKey;
use PHPTools\LaravelCsvParser\Events;
use PHPTools\LaravelCsvParser\Models\CsvParsedRow;

class ApplyParsedCsvRowsJob implements ShouldQueue
{
    use Concerns\Configure, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(CsvFile & Model $file)
    {
        $this->configure($file);
    }

    public function failed(\Throwable $exception): void
    {
        event(new Events\ParsedCsvApplyFailed($this->file, $exception));
    }

    public function handle(): void
    {
        event(new Events\ParsedCsvApplying($this->file));

        $modelTypes = $this->file->parsed_rows()
            ->whereNotNull('model_type')
            ->reorder()
            ->orderBy('order_number')
            ->groupLimit(1, 'model_type')
            ->get(['model_type', 'order_number'])
            ->sortBy('order_number')
            ->pluck('model_type');

        $targetModels = [];

        foreach ($modelTypes as $modelType) {
            $modelClass = Relation::getMorphedModel($modelType) ?? $modelType;

            if (! \is_subclass_of($modelClass, HasUniqueKey::class) || ! \is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $targetModel = $targetModels[$modelClass] ??= new $modelClass;

            $this->file->parsed_rows()
                ->where('model_type', $modelClass)
                ->reorder()
                ->lazyById()
                ->chunk($this->chunkSize)
                ->each(fn(LazyCollection $parsedRows) => $this->applyParsedRows(collect($parsedRows), $targetModel));
        }

        event(new Events\ParsedCsvApplied($this->file));
    }

    protected function applyParsedRows(Collection $parsedRows, HasUniqueKey & Model $targetModel): void
    {
        $targetModel->newModelQuery()->getConnection()->transaction(
            function () use ($parsedRows, $targetModel): void {
                $grouped = $parsedRows->groupBy(
                    static fn(CsvParsedRow $row): string => \is_null($row->model_id) ? 'create' : 'update'
                );

                $modelType = \get_class($targetModel);

                foreach ($grouped as $type => $group) {
                    event(new Events\ParsedCsvRowsApplying($group, $modelType));

                    $this->{$type}($group, $targetModel->newModelQuery());

                    event(new Events\ParsedCsvRowsApplied($group, $modelType));
                }
            }
        );
    }

    protected function create(Collection $parsedRows, Builder $targetModelBuilder): void
    {
        $targetModelBuilder->fillAndInsert($parsedRows->map->values->all());

        $targetModel = $targetModelBuilder->getModel();

        $modelKeys = $targetModelBuilder
            ->whereIn(DB::raw($targetModel->getUniqueKeyName()), $parsedRows->pluck('model_unique_key'))
            ->pluck($targetModel->getKeyName(), DB::raw($targetModel->getUniqueKeyName() . ' as model_unique_key'));

        if ($modelKeys->isEmpty()) {
            return;
        }

        $parsedRows->filter->isDirty()->isEmpty()
            ? $this->updateCsvParsedRowsModelId($parsedRows, $modelKeys, $targetModel)
            : $this->updateCsvParsedRows($parsedRows, $modelKeys);
    }

    protected function updateCsvParsedRowsModelId(Collection $parsedRows, Collection $modelKeys, HasUniqueKey & Model $targetModel): void
    {
        /** @var Builder $builder */
        $builder = $parsedRows->first()->newModelQuery();

        $uniqueKeys = $parsedRows->pluck('model_unique_key');
        $pdo = $builder->getConnection()->getPdo();

        $modelId = $modelKeys
            ->map(
                static fn(string $key, string $uniqueKey): string => \sprintf(
                    'when model_unique_key = %s then %s',
                    $pdo->quote($uniqueKey),
                    $pdo->quote($key)
                )
            )
            ->implode(' ');

        $builder
            ->where('model_type', $targetModel->getMorphClass())
            ->whereIn('model_unique_key', $uniqueKeys)
            ->update(['model_id' => DB::raw("case {$modelId} end")]);
    }

    protected function updateCsvParsedRows(Collection $parsedRows, Collection $modelKeys): void
    {
        $update = [];

        /** @var CsvParsedRow $row */
        foreach ($parsedRows as $row) {
            $modelKey = $modelKeys->get($row->model_unique_key);

            if (! \is_null($modelKey)) {
                $row->setAttribute('model_id', $modelKey);

                $update[] = $row->getAttributes();
            }
        }

        $parsedRows->first()->newModelQuery()->upsert($update, ['id']);
    }

    protected function update(Collection $parsedRows, Builder $targetModelBuilder): void
    {
        $update = [];

        $keyName = $targetModelBuilder->getModel()->getKeyName();

        /** @var CsvParsedRow $parsedRow */
        foreach ($parsedRows as $parsedRow) {
            $update[] = [...$parsedRow->values, $keyName => $parsedRow->model_id];
        }

        $targetModelBuilder->upsert($update, [$keyName]);
    }
}
