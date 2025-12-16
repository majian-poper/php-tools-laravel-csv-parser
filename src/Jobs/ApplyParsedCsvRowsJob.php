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

        foreach ($modelTypes as $modelType) {
            $modelClass = Relation::getMorphedModel($modelType) ?? $modelType;

            if (! $this->isUniqueKeyModel($modelClass)) {
                continue;
            }

            $this->file->parsed_rows()
                ->where('model_type', $modelClass)
                ->reorder()
                ->lazyById()
                ->chunk($this->chunkSize)
                ->each(fn(LazyCollection $parsedRows) => $this->applyParsedRows(collect($parsedRows)));
        }

        event(new Events\ParsedCsvApplied($this->file));
    }

    protected function isUniqueKeyModel(Model | string $model): bool
    {
        return \is_subclass_of($model, HasUniqueKey::class) && \is_subclass_of($model, Model::class);
    }

    protected function applyParsedRows(Collection $parsedRows): void
    {
        /** @var CsvParsedRow $firstParsedRow */
        $firstParsedRow = $parsedRows->first();

        $targetModelBuilder = $firstParsedRow->model()->getRelated()->newModelQuery();

        $targetModelBuilder->getConnection()->transaction(
            function () use ($parsedRows, $targetModelBuilder): void {
                $grouped = $parsedRows->groupBy(
                    static fn(CsvParsedRow $row): string => \is_null($row->model_id) ? 'create' : 'update'
                );

                $modelType = \get_class($targetModelBuilder->getModel());

                foreach ($grouped as $type => $group) {
                    event(new Events\ParsedCsvRowsApplying($this->file, $group, $modelType));

                    $this->{$type}($targetModelBuilder, $group);

                    event(new Events\ParsedCsvRowsApplied($this->file, $group, $modelType));
                }
            }
        );

        $firstParsedRow->newModelQuery()->upsert(
            $parsedRows->map->getAttributes()->all(),
            [$firstParsedRow->getKeyName()],
            ['model_id', 'created_unique_key', 'values']
        );
    }

    protected function create(Builder $targetModelBuilder, Collection $parsedRows): void
    {
        $this->fillForeignKeys($targetModelBuilder, $parsedRows);

        $modelKeys = $this->getApprovableKeys($targetModelBuilder, $parsedRows->pluck('created_unique_key')->all());

        if ($modelKeys->isNotEmpty()) {
            $update = Collection::make();
            $insert = Collection::make();

            foreach ($parsedRows as $parsedRow) {
                if ($modelKeys->has($parsedRow->created_unique_key)) {
                    $parsedRow->model_id = $modelKeys->get($parsedRow->created_unique_key);

                    $update->push($parsedRow);
                } else {
                    $insert->push($parsedRow);
                }
            }

            $this->update($targetModelBuilder, $update);
        }

        $insert = $insert ?? $parsedRows;

        $targetModelBuilder->fillAndInsert($insert->map->values->all());

        $insertModelKeys = $this->getApprovableKeys($targetModelBuilder, $insert->pluck('created_unique_key')->all());

        if ($insertModelKeys->isNotEmpty()) {
            foreach ($insert as $parsedRow) {
                $parsedRow->model_id = $insertModelKeys->get($parsedRow->created_unique_key);
            }
        }
    }

    protected function fillForeignKeys(Builder $targetModelBuilder, Collection $parsedRows): void
    {
        $foreignUniqueKeys = [];

        foreach ($parsedRows as $parsedRow) {
            /** @var HasUniqueKey & Model $targetModel */
            $targetModel = $targetModelBuilder->make()->setRawAttributes($parsedRow->values);

            foreach ($targetModel->getForeignModelKeys() as $foreignModel => $foreignKeyName) {
                $foreignUniqueKeys[$foreignModel][$parsedRow->values[$foreignKeyName]] = null;
            }

            $parsedRow->setRelation('model', $targetModel);
        }

        if (empty($foreignUniqueKeys)) {
            return;
        }

        foreach ($foreignUniqueKeys as $foreignModel => &$foreignKeys) {
            $foreignKeys = $this->file->parsed_rows()
                ->where('model_type', Relation::getMorphAlias($foreignModel))
                ->whereNotNull('model_id')
                ->whereIn('model_unique_key', \array_keys($foreignKeys))
                ->groupLimit(1, 'model_unique_key')
                ->pluck('model_id', 'model_unique_key')
                ->all();
        }

        foreach ($parsedRows as $parsedRow) {
            /** @var HasUniqueKey & Model $targetModel */
            $targetModel = $parsedRow->getRelation('model');

            foreach ($targetModel->getForeignModelKeys() as $foreignModel => $foreignKeyName) {
                $foreignKeyValue = $foreignUniqueKeys[$foreignModel][$parsedRow->values[$foreignKeyName]] ?? null;

                $parsedRow->setAttribute("values->{$foreignKeyName}", $foreignKeyValue);

                $targetModel->setAttribute($foreignKeyName, $foreignKeyValue);
            }

            $parsedRow->created_unique_key = $targetModel->getUniqueKey();
        }
    }

    protected function getApprovableKeys(Builder $targetModelBuilder, array $createdUniqueKeys): Collection
    {
        $targetModel = $targetModelBuilder->getModel();

        return $targetModelBuilder
            ->whereIn(DB::raw($targetModel->getUniqueKeyName()), $createdUniqueKeys)
            ->pluck($targetModel->getKeyName(), DB::raw($targetModel->getUniqueKeyName() . ' as created_unique_key'));
    }

    protected function update(Builder $targetModelBuilder, Collection $parsedRows): void
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
