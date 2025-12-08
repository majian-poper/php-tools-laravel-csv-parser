<?php

namespace PHPTools\LaravelCsvParser\Contracts\RowParser;

use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;

interface RequiresInitialization
{
    public function initialize(CommaSeparatedValuesInterface $csv): void;
}
