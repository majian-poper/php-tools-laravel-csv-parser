<?php

namespace PHPTools\LaravelCsvParser\Contracts\RowParser;

use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;

interface HasValidationRules
{
    public function rules(CommaSeparatedValuesInterface $csv): array;
}
