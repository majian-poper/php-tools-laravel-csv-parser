<?php

namespace PHPTools\LaravelCsvParser;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator;
use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;
use PHPTools\LaravelCsvParser\Contracts\RowParser;

class CsvParser implements Contracts\CsvParser
{
    protected bool $shouldBeInitialized = false;

    protected bool $shouldBeValidated = false;

    protected bool $supportsChunking = false;

    protected Validator | null $validator = null;

    /**
     * @param  RowParser | RowParser\RequiresInitialization | RowParser\HasValidationRules | RowParser\RowsHandler  $rowParser
     */
    public function __construct(protected readonly RowParser $rowParser, protected int $chunkSize = 100)
    {
        $this->shouldBeInitialized = $rowParser instanceof RowParser\RequiresInitialization;
        $this->shouldBeValidated = $rowParser instanceof RowParser\HasValidationRules;
        $this->supportsChunking = $rowParser instanceof RowParser\RowsHandler;
    }

    public function parse(CommaSeparatedValuesInterface $csv, array $readRowOptions = []): \Generator
    {
        $this->initializeRowParser($csv);

        $this->validator = $this->createValidator($csv);

        $rows = [];

        foreach ($csv->readRow($readRowOptions) as $no => $row) {
            yield from $validating = $this->validateRow($row, $no);

            if (! $validating->getReturn()) {
                continue;
            }

            $rows[$no] = $row;

            if (\count($rows) === $this->chunkSize) {
                yield from $this->parseRows($rows);
            }
        }

        if (! empty($rows)) {
            yield from $this->parseRows($rows);
        }
    }

    protected function initializeRowParser(CommaSeparatedValuesInterface $csv): void
    {
        if ($this->shouldBeInitialized) {
            $this->rowParser->initialize($csv);
        }
    }

    protected function createValidator(CommaSeparatedValuesInterface $csv): ?Validator
    {
        if (! $this->shouldBeValidated) {
            return null;
        }

        $rules = $this->rowParser->rules($csv);
        $keys = \array_keys($rules);

        return ValidatorFacade::make([], $rules)->setAttributeNames(\array_combine($keys, $keys));
    }

    protected function validateRow(array $row, int $no): \Generator
    {
        if ($this->shouldBeValidated && $this->validator->setData($row)->fails()) {
            yield [$no, $row, 0, $this->validator->errors()];

            return false;
        }

        return true;
    }

    protected function parseRows(array &$rows): \Generator
    {
        if ($this->supportsChunking) {
            $this->rowParser->handleRows($rows);
        }

        foreach ($rows as $no => $row) {
            yield from $this->parseRow($row, $no);
        }

        $rows = [];
    }

    protected function parseRow(array $row, int $no): \Generator
    {
        $defaultOrderNumber = 0;

        try {
            foreach ($this->rowParser->parse($row, $no) as $orderNumber => $result) {
                if ($result instanceof MessageBag) {
                    yield [$no, $row, 0, $result];

                    break;
                }

                $defaultOrderNumber++;

                if (! \is_int($orderNumber)) {
                    $orderNumber = $defaultOrderNumber;
                }

                if ($result instanceof Contracts\HasUniqueKey && $result instanceof Model) {
                    yield [$no, $row, $orderNumber, $result];
                }
            }
        } catch (\Throwable $e) {
            yield [$no, $row, 0, new MessageBag([$no => $e->getMessage()])];
        }
    }
}
