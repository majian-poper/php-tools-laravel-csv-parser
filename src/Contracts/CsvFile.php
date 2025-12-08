<?php

namespace PHPTools\LaravelCsvParser\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;

interface CsvFile extends CommaSeparatedValuesInterface
{
    public const OPTION_WITH_ID = 'with_id';

    public function getSource(): CommaSeparatedValuesInterface;

    public function getRowParser(): RowParser;

    public function rows(): MorphMany;

    public function parsed_rows(): MorphMany;
}
