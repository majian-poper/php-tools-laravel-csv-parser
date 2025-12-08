<?php

use App\Models\CsvFile;

beforeEach(function () {
    $this->file = __DIR__ . '/../fixtures/users.csv';

    $this->csvFile = CsvFile::query()->create(['path' => $this->file]);
});

it('works like csv file', function () {
    expect($this->csvFile->getHeaders())->toBe(['id', 'name', 'email_address']);

    expect($this->csvFile->readRow()->current())
        ->toBe(['id' => '1', 'name' => 'John Doe', 'email_address' => 'john@example.com']);
});

test('deleting csv file also deletes related rows and parsed rows', function () {
    $this->csvFile->parse();

    expect($this->csvFile->rows()->count())->toBeGreaterThan(0);
    expect($this->csvFile->parsed_rows()->count())->toBeGreaterThan(0);

    $this->csvFile->delete();

    expect($this->csvFile->rows()->count())->toBe(0);
    expect($this->csvFile->parsed_rows()->count())->toBe(0);
});

test('readRows chunks rows and yields remainder', function () {
    $chunks = iterator_to_array($this->csvFile->readRows(2));

    expect($chunks)->toHaveCount(3); // 5 data rows -> 2,2,1
    expect(array_keys($chunks))->toBe([0, 1, 2]);
    expect(array_keys($chunks[0]))->toBe([2, 3]);
    expect(array_keys($chunks[1]))->toBe([4, 5]);
    expect(array_keys($chunks[2]))->toBe([6]);

    expect($chunks[2][6])
        ->toBe(['id' => '5', 'name' => 'Michael Brown', 'email_address' => 'michael@example.com']);
});
