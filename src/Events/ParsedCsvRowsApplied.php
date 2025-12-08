<?php

namespace PHPTools\LaravelCsvParser\Events;

use Illuminate\Support\Collection;

class ParsedCsvRowsApplied
{
    public function __construct(public readonly Collection $parsedRows, public readonly string $modelType) {}
}
