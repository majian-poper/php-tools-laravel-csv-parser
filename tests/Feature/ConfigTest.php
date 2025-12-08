<?php

use App\Models\CsvFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use PHPTools\LaravelCsvParser\Jobs;

beforeEach(function () {
    $this->file = __DIR__ . '/../fixtures/users.csv';
});

test('config auto_parse ON', function () {
    Config::set('csv-parser.auto_parse', true);

    Bus::fake();

    CsvFile::query()->create(['path' => $this->file]);

    Bus::assertChained(
        [
            Jobs\CollectCsvRowsJob::class,
            Jobs\ParseCsvRowsJob::class,
        ]
    );
});

test('config auto_parse OFF', function () {
    Config::set('csv-parser.auto_parse', false);

    Bus::fake();

    CsvFile::query()->create(['path' => $this->file]);

    Bus::assertNothingChained();
});
