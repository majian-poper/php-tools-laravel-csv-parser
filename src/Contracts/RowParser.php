<?php

namespace PHPTools\LaravelCsvParser\Contracts;

interface RowParser
{
    /**
     * Parse a single row from CSV and return the resulting models.
     * Yields $orderNumber => $result, the orderNumber SHOULD be an integer.
     * When applying parsed rows, we will order results based on $orderNumber.
     *
     * $result is either a Model implementing HasUniqueKey or a MessageBag of errors.
     *
     * @return \Generator<int, HasUniqueKey & \Illuminate\Database\Eloquent\Model>
     * @return \Generator<int, \Illuminate\Support\MessageBag>
     */
    public function parse(array $row, int $no): \Generator;
}
