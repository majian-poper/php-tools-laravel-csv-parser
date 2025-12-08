<?php

namespace App\Models;

use App\Support\ContactRowParser;
use PHPTools\LaravelCsvParser\Contracts\RowParser;

class ContactCsvFile extends CsvFile
{
    protected $table = 'csv_files';

    public function getRowParser(): RowParser
    {
        return new ContactRowParser($this);
    }
}
