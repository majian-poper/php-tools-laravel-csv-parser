<?php

namespace PHPTools\LaravelCsvParser\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Bus;
use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;
use PHPTools\LaravelCsvParser\Contracts\CsvFile;
use PHPTools\LaravelCsvParser\Jobs;
use PHPTools\LaravelCsvParser\Models\CsvParsedRow;
use PHPTools\LaravelCsvParser\Models\CsvRow;

/**
 * @property-read array $headers
 * @property-read CsvRow | null $header_row
 * @property-read \Illuminate\Database\Eloquent\Collection<CsvRow> $content_rows
 * @property-read \Illuminate\Database\Eloquent\Collection<CsvRow> $rows
 * @property-read \Illuminate\Database\Eloquent\Collection<CsvParsedRow> $parsed_rows
 */
trait HasCsvRows
{
    protected static function bootHasCsvRows()
    {
        static::created(
            static function (self $file): void {
                if (config('csv-parser.auto_parse', true)) {
                    $file->parse();
                }
            }
        );

        static::deleting(
            static function (self $model): void {
                $model->rows()->delete();
                $model->parsed_rows()->delete();
            }
        );
    }

    public function parse(): void
    {
        Bus::chain([new Jobs\CollectCsvRowsJob($this), new Jobs\ParseCsvRowsJob($this)])->dispatch();
    }

    public function apply(): void
    {
        Jobs\ApplyParsedCsvRowsJob::dispatch($this);
    }

    // --- Attributes ---

    public function getHeadersAttribute(): array
    {
        return $this->header_row?->content ?? [];
    }

    // --- Eloquent relationships ---

    public function header_row(): MorphOne
    {
        return $this->rows()->one()->where('no', '=', 1);
    }

    public function content_rows(): MorphMany
    {
        return $this->rows()->where('no', '>', 1);
    }

    public function rows(): MorphMany
    {
        return $this->morphMany(config('csv-parser.implementations.csv_row', CsvRow::class), 'file')
            ->orderBy('no');
    }

    public function parsed_rows(): MorphMany
    {
        return $this->morphMany(config('csv-parser.implementations.csv_parsed_row', CsvParsedRow::class), 'file')
            ->orderBy('no')
            ->orderBy('order_number');
    }

    // --- CommaSeparatedValuesInterface implementation ---

    public function withBom(): bool
    {
        return false;
    }

    public function getEncoding(): string
    {
        return CommaSeparatedValuesInterface::DEFAULT_ENCODING;
    }

    public function getHeaders(): array
    {
        return $this->makeHeadersUnique($this->header_row?->content ?? []);
    }

    public function readRow(array $options = []): \Generator
    {
        $withHeader = $options[CommaSeparatedValuesInterface::OPTION_WITH_HEADER] ?? true;
        $withId = $options[CsvFile::OPTION_WITH_ID] ?? false;

        $headers = [];

        /** @var CsvRow $row */
        foreach ($this->rows()->reorder()->lazyById(column: 'no') as $row) {
            $content = $row->content;

            if ($withId) {
                $content['__row_id'] = $row->getKey();
            }

            if (! $withHeader) {
                yield $row->no => $content;

                continue;
            }

            if ($row->no === 1) {
                $headers = $this->makeHeadersUnique($row->content);

                continue;
            }

            $mappedRow = [];

            foreach ($content as $index => $value) {
                $mappedRow[$headers[$index] ?? $index] = $value;
            }

            yield $row->no => $mappedRow;
        }
    }

    public function readRows(int $size, array $options = []): \Generator
    {
        $chunkIndex = 0;
        $chunk = [];

        foreach ($this->readRow($options) as $no => $row) {
            $chunk[$no] = $row;

            if (\count($chunk) === $size) {
                yield $chunkIndex++ => $chunk;

                $chunk = [];
            }
        }

        if (! empty($chunk)) {
            yield $chunkIndex => $chunk;
        }
    }

    protected function makeHeadersUnique(array $headers): array
    {
        $repeated = [];

        foreach ($headers as &$header) {
            $repeated[$header] ??= 0;

            if (++$repeated[$header] > 1) {
                $header = \sprintf('%s (%d)', $header, $repeated[$header]);
            }
        }

        return $headers;
    }
}
