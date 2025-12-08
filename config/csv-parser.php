<?php

return [

    'auto_parse' => env('CSV_PARSER_AUTO_PARSE', true),

    'implementations' => [
        'csv_row' => \PHPTools\LaravelCsvParser\Models\CsvRow::class,
        'csv_parsed_row' => \PHPTools\LaravelCsvParser\Models\CsvParsedRow::class,
    ],

    'chunk_size' => env('CSV_PARSER_CHUNK_SIZE', 100),

    'timeout' => env('CSV_PARSER_JOB_TIMEOUT', 60),
];
