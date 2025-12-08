<?php

namespace PHPTools\LaravelCsvParser\Contracts;

use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;

interface CsvParser
{
    /**
     * Parse a CSV file and return the resulting models or errors.
     *
     * returns array [$no, $row, $orderNumber, $result]
     *
     * @return \Generator<array{int, array, int, HasUniqueKey & \Illuminate\Database\Eloquent\Model}>
     * @return \Generator<array{int, array, int, \Illuminate\Support\MessageBag}>
     */
    public function parse(CommaSeparatedValuesInterface $csv, array $readRowOptions = []): \Generator;
}
