<?php

namespace PHPTools\LaravelCsvParser;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CsvParserPackageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-csv-parser')
            ->hasConfigFile()
            ->discoversMigrations();
    }
}
