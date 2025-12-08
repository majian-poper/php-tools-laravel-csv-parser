<?php

namespace PHPTools\LaravelCsvParser\Contracts\RowParser;

interface RowsHandler
{
    public function handleRows(array &$rows): void;
}
