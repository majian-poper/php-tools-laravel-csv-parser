<?php

namespace PHPTools\LaravelCsvParser\Contracts;

interface HasUniqueKey
{
    public function getUniqueKeyName(): string;

    public function getUniqueKey(): string;
}
