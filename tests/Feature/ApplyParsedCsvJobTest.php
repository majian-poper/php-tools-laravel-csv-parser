<?php

use App\Models\ContactCsvFile;
use App\Models\CsvFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPTools\LaravelCsvParser\Events;
use PHPTools\LaravelCsvParser\Models\CsvParsedRow;

beforeEach(function () {
    Config::set('csv-parser.auto_parse', true);

    $this->file = __DIR__ . '/../fixtures/users.csv';

    $this->csvFile = CsvFile::query()->create(['path' => $this->file]);
});

test('fired ApplyParsedCsv event', function () {
    Event::fake();

    $this->csvFile->apply();

    Event::assertDispatched(Events\ParsedCsvApplying::class);
    Event::assertDispatched(Events\ParsedCsvApplied::class);
    Event::assertDispatched(Events\ParsedCsvRowsApplying::class);
    Event::assertDispatched(Events\ParsedCsvRowsApplied::class);
});

test('dirty parsed rows are upserted with model ids', function () {
    CsvParsedRow::retrieved(fn(CsvParsedRow $row) => $row->file_id === $this->csvFile->getKey() && $row->errors = '{}');

    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $this->csvFile->apply();

    $upserted = collect($queries)->contains(fn(string $sql) => str_contains($sql, 'insert into "csv_parsed_rows"') && str_contains($sql, 'on conflict ("id") do update'));

    expect($upserted)->toBeTrue();

    CsvParsedRow::flushEventListeners();
});

test('model id remain same when unique keys collide across model types', function () {
    $this->csvFile->apply();

    $uniqueKey = 'john@example.com';

    $originalParsedRow = CsvParsedRow::query()
        ->where('file_type', $this->csvFile->getMorphClass())
        ->where('file_id', $this->csvFile->getKey())
        ->where('model_unique_key', $uniqueKey)
        ->first();

    expect($originalParsedRow->model_id)->toBe('2');

    $anotherCsvFile = ContactCsvFile::query()->create([
        'path' => __DIR__ . '/../fixtures/contacts.csv',
    ]);

    $anotherCsvFile->apply();

    $originalParsedRow->refresh();

    expect($originalParsedRow->model_id)->toBe('2');
});
