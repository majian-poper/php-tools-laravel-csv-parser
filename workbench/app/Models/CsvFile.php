<?php

namespace App\Models;

use App\Support\UserRowParser;
use Illuminate\Database\Eloquent\Model;
use PHPTools\CommaSeparatedValues\CommaSeparatedValues;
use PHPTools\CommaSeparatedValues\Contracts\CommaSeparatedValuesInterface;
use PHPTools\LaravelCsvParser\Contracts\CsvFile as ContractsCsvFile;
use PHPTools\LaravelCsvParser\Contracts\RowParser;
use PHPTools\LaravelCsvParser\Models\Concerns\HasCsvRows;

class CsvFile extends Model implements ContractsCsvFile
{
    use HasCsvRows;

    protected $fillable = [
        'path',
    ];

    protected $casts = [
        'path' => 'string',
    ];

    public function getBasename(string $suffix = ''): string
    {
        return \basename($this->path, $suffix);
    }

    public function getSource(): CommaSeparatedValuesInterface
    {
        return new CommaSeparatedValues($this->path);
    }

    public function getRowParser(): RowParser
    {
        return new UserRowParser($this);
    }
}
