<?php

namespace PHPTools\LaravelCsvParser\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPTools\LaravelCsvParser\Contracts\CsvFile;

class ParsedCsvRowsApplied
{
    public function __construct(
        public readonly CsvFile & Model $file,
        public readonly Collection $parsedRows,
        public readonly string $modelType,
    ) {}
}
