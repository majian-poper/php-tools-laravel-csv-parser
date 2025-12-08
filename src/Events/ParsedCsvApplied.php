<?php

namespace PHPTools\LaravelCsvParser\Events;

use Illuminate\Database\Eloquent\Model;
use PHPTools\LaravelCsvParser\Contracts\CsvFile;

class ParsedCsvApplied
{
    public function __construct(public readonly CsvFile & Model $file) {}
}
