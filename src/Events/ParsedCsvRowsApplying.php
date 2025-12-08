<?php

namespace PHPTools\LaravelCsvParser\Events;

use Illuminate\Support\Collection;

class ParsedCsvRowsApplying
{
    public function __construct(public readonly Collection $parsedRows, public readonly string $modelType) {}
}
