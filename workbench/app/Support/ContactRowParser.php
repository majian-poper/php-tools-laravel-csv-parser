<?php

namespace App\Support;

use App\Models\Contact;
use PHPTools\LaravelCsvParser\Contracts\RowParser;

class ContactRowParser implements RowParser
{
    public function parse(array $row, int $no): \Generator
    {
        yield new Contact(
            [
                'name' => $row['name'],
                'email' => $row['email_address'],
                'phone' => $row['phone'],
            ]
        );
    }
}
