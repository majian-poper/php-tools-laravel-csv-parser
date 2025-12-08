<?php

namespace PHPTools\LaravelCsvParser\Events;

use Illuminate\Database\Eloquent\Model;
use PHPTools\LaravelCsvParser\Contracts\CsvFile;

class CsvParseFailed
{
    public function __construct(public readonly CsvFile & Model $file, public readonly \Throwable $exception) {}
}
