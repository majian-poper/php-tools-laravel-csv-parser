<?php

namespace PHPTools\LaravelCsvParser\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;
use PHPTools\LaravelCsvParser\Contracts\CsvFile;
use PHPTools\LaravelCsvParser\Events;

class CollectCsvRowsJob implements ShouldQueue
{
    use Concerns\Configure, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(CsvFile & Model $file)
    {
        $this->configure($file);
    }

    public function failed(\Throwable $exception): void
    {
        event(new Events\CsvCollectFailed($this->file, $exception));
    }

    public function handle(): void
    {
        $source = $this->file->getSource();
        $target = $this->file;

        event(new Events\CsvCollecting($target));

        $options = [CommaSeparatedValuesInterface::OPTION_WITH_HEADER => false];

        $sourceGenerator = $source->readRow($options);
        $targetGenerator = $target->readRow([...$options, CsvFile::OPTION_WITH_ID => true]);

        $insert = $update = [];

        $builder = $target->rows()->getRelated()->newModelQuery();

        foreach ($sourceGenerator as $no => $row) {
            $existsRow = $this->getExistsRow($targetGenerator, $no);

            $id = $existsRow['__row_id'] ?? null;
            unset($existsRow['__row_id']);

            if ($row === $existsRow) {
                continue;
            }

            $csvRow = [
                'file_type' => $target->getMorphClass(),
                'file_id' => $target->getKey(),
                'no' => $no,
                'content' => $row,
            ];

            if (\is_null($id)) {
                $insert[] = $csvRow;
            } else {
                $csvRow['id'] = $id;
                $update[] = $csvRow;
            }

            \count($insert) === $this->chunkSize && $this->insert($builder, $insert);
            \count($update) === $this->chunkSize && $this->upsert($builder, $update);
        }

        empty($insert) || $this->insert($builder, $insert);
        empty($update) || $this->upsert($builder, $update);

        event(new Events\CsvCollected($target));
    }

    protected function getExistsRow(\Generator $targetGenerator, int $no): array
    {
        $existsRow = [];

        while ($targetGenerator->valid()) {
            switch ($targetGenerator->key() <=> $no) {
                case -1:
                    // $key < $no
                    $targetGenerator->next();

                    continue 2;
                case 0:
                    // $key === $no
                    $existsRow = $targetGenerator->current();
                    $targetGenerator->next();

                    break 2;
                case 1:
                    // $key > $no
                    break 2;
            }
        }

        return $existsRow;
    }

    protected function insert(Builder $builder, array &$rows): void
    {
        $builder->fillAndInsert($rows);

        $rows = [];
    }

    protected function upsert(Builder $builder, array &$rows): void
    {
        // fillForInsert will convert content to JSON string
        $builder->upsert($builder->fillForInsert($rows), ['id'], ['content']);

        $rows = [];
    }
}
