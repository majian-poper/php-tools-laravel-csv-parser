<?php

use App\Models\CsvFile;
use App\Models\User;
use App\Support\UserRowParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;
use PHPTools\LaravelCsvParser\Contracts\RowParser;
use PHPTools\LaravelCsvParser\Events;

beforeEach(function () {
    Config::set('csv-parser.auto_parse', false);

    $this->file = __DIR__ . '/../fixtures/users.csv';

    $this->csvFile = CsvFile::query()->create(['path' => $this->file]);
});

test('fired CsvCollect event', function () {
    Event::fake();

    $this->csvFile->parse();

    Event::assertDispatched(Events\CsvCollecting::class);
    Event::assertDispatched(Events\CsvCollected::class);
    Event::assertDispatched(Events\CsvParsing::class);
    Event::assertDispatched(Events\CsvParsed::class);
});

test('csv (parsed) rows inserted', function () {
    $this->csvFile->parse();

    expect($this->csvFile->rows()->count())->toBe(6);
    expect($this->csvFile->parsed_rows()->count())->toBe(5);

    expect($this->csvFile->header_row->content)->toBe(['id', 'name', 'email_address']);

    $firstContentRow = $this->csvFile->content_rows->first();

    expect($firstContentRow->no)->toBe(2);
    expect($firstContentRow->content)->toBe(['1', 'John Doe', 'john@example.com']);

    $firstParsedRow = $this->csvFile->parsed_rows()->first();

    expect($firstParsedRow->no)->toBe(2);
    expect($firstParsedRow->model_type)->toBe(App\Models\User::class);
    expect(Arr::only($firstParsedRow->values, ['email', 'name']))->toBe([
        'email' => 'john@example.com',
        'name' => 'John Doe',
    ]);

    expect(Hash::check('password', $firstParsedRow->values['password']))->toBeTrue();

    $aliceParsedRow = $this->csvFile->parsed_rows()->where('values->email', 'alice@example.com')->first();
    $aliceModel = User::query()->where('email', 'alice@example.com')->first();

    expect($aliceParsedRow->no)->toBe(5);
    expect($aliceParsedRow->model->getKey())->toBe($aliceModel->getKey());
});

test('error messages stored when row parser validation failures', function () {
    $this->csvFile = new class extends CsvFile
    {
        protected $table = 'csv_files';

        public function getRowParser(): RowParser
        {
            return new class extends UserRowParser implements RowParser\HasValidationRules
            {
                public function rules(CommaSeparatedValuesInterface $csv): array
                {
                    return [
                        'name' => ['required', 'string', 'max:255'],
                        'email_address' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                    ];
                }
            };
        }
    };

    $this->csvFile->path = __DIR__ . '/../fixtures/users_invalid.csv';
    $this->csvFile->save();

    $this->csvFile->parse();

    $parsedRows = $this->csvFile->parsed_rows()->get();

    expect($parsedRows)->toHaveCount(2);

    $validationErrorRow = $parsedRows->firstWhere('no', 2);

    expect($validationErrorRow->errors)->toHaveKeys(['name', 'email_address']);
    expect($validationErrorRow->values)->toBeEmpty();
    expect($validationErrorRow->order_number)->toBe(0);
});

test('message bag stored when row parser yields errors', function () {
    $this->csvFile = new class extends CsvFile
    {
        protected $table = 'csv_files';

        public function getRowParser(): RowParser
        {
            return new class extends UserRowParser
            {
                public function parse(array $row, int $no): \Generator
                {
                    yield new MessageBag(['error' => 'yield error message']);
                }
            };
        }
    };

    $this->csvFile->path = __DIR__ . '/../fixtures/users.csv';
    $this->csvFile->save();

    $this->csvFile->parse();

    $parsedRows = $this->csvFile->parsed_rows()->get();

    $parsedRows->each(fn($row) => expect($row->errors)->toBe(['error' => ['yield error message']]));
});

test('message bag stored when row parser throw exceptions', function () {
    $this->csvFile = new class extends CsvFile
    {
        protected $table = 'csv_files';

        public function getRowParser(): RowParser
        {
            return new class extends UserRowParser
            {
                public function parse(array $row, int $no): \Generator
                {
                    throw new \Exception('throw error message');
                }
            };
        }
    };

    $this->csvFile->path = __DIR__ . '/../fixtures/users.csv';
    $this->csvFile->save();

    $this->csvFile->parse();

    $parsedRows = $this->csvFile->parsed_rows()->get();

    $parsedRows->each(fn($row) => expect($row->errors)->toBe([$row->no => ['throw error message']]));
});

test('aligns existing target rows when numbers are out of sync', function () {
    $legacy = $this->csvFile->rows()->create(['no' => 0, 'content' => ['legacy']]);
    $header = $this->csvFile->rows()->create(['no' => 1, 'content' => ['id', 'name', 'email_address']]);
    $stale = $this->csvFile->rows()->create(['no' => 3, 'content' => ['2', 'Old Name', 'old@example.com']]);

    $this->csvFile->parse();

    $rows = $this->csvFile->rows()->orderBy('no')->get();

    expect($rows)->toHaveCount(7);

    expect($rows->firstWhere('no', 0)->getKey())->toBe($legacy->getKey()); // key < $no, skips it

    $headerRow = $rows->firstWhere('no', 1);
    expect($headerRow->getKey())->toBe($header->getKey()); // key === $no
    expect($headerRow->content)->toBe(['id', 'name', 'email_address']);

    $secondRow = $rows->firstWhere('no', 2);
    expect($secondRow->content)->toBe(['1', 'John Doe', 'john@example.com']); // insert after key > $no branch

    $updatedRow = $rows->firstWhere('no', 3);
    expect($updatedRow->getKey())->toBe($stale->getKey()); // reused existing id
    expect($updatedRow->content)->toBe(['2', 'Jane Smith', 'jane@example.com']);
});
