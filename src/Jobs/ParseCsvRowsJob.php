<?php

namespace PHPTools\LaravelCsvParser\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\MessageBag;
use PHPTools\LaravelCsvParser\Contracts\CsvFile;
use PHPTools\LaravelCsvParser\Contracts\HasUniqueKey;
use PHPTools\LaravelCsvParser\CsvParser;
use PHPTools\LaravelCsvParser\Events;

class ParseCsvRowsJob implements ShouldQueue
{
    use Concerns\Configure, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(CsvFile & Model $file)
    {
        $this->configure($file);
    }

    public function failed(\Throwable $exception): void
    {
        event(new Events\CsvParseFailed($this->file, $exception));
    }

    public function handle(): void
    {
        $target = $this->file;

        event(new Events\CsvParsing($target));

        $target->parsed_rows()->delete();

        $parser = new CsvParser($target->getRowParser(), $this->chunkSize);
        $options = [CsvFile::OPTION_WITH_ID => true];

        $rows = [];

        $builder = $target->parsed_rows()->getRelated()->newModelQuery();

        foreach ($parser->parse($target, $options) as [$no, $row, $orderNumber, $result]) {
            $parsedRow = match (true) {
                $result instanceof MessageBag => $this->fromErrors($result),
                $result instanceof HasUniqueKey && $result instanceof Model => $this->fromModel($result),
                default => null
            };

            if (\is_null($parsedRow)) {
                continue;
            }

            $rows[] = [
                'file_type' => $target->getMorphClass(),
                'file_id' => $target->getKey(),
                'no' => $no,
                'row_id' => $row['__row_id'],
                'order_number' => $orderNumber,
                ...$parsedRow,
            ];

            \count($rows) === $this->chunkSize && $this->insert($builder, $rows);
        }

        empty($rows) || $this->insert($builder, $rows);

        event(new Events\CsvParsed($target));
    }

    protected function fromErrors(MessageBag $result): array
    {
        return [
            'model_type' => null,
            'model_id' => null,
            'model_unique_key' => null,
            'values' => $this->emptyObject(),
            'errors' => $result->toArray(),
        ];
    }

    protected function fromModel(HasUniqueKey & Model $result): array
    {
        return [
            'model_type' => $result->getMorphClass(),
            'model_id' => $result->getKey(),
            'model_unique_key' => $result->getUniqueKey(),
            'values' => $result->getAttributes(),
            'errors' => $this->emptyObject(),
        ];
    }

    protected function emptyObject(): object
    {
        static $object = (object) [];

        return $object;
    }

    protected function insert(Builder $builder, array &$rows): void
    {
        $builder->fillAndInsert($rows);

        $rows = [];
    }
}
