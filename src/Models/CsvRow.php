<?php

namespace PHPTools\LaravelCsvParser\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $file_type
 * @property int $file_id
 * @property int $no
 * @property array $content
 * @property-read \PHPTools\LaravelCsvParser\Contracts\CsvFile & Model $file
 * @property-read \Illuminate\Database\Eloquent\Collection<CsvParsedRow> $parsed_rows
 */
class CsvRow extends Model
{
    protected $casts = [
        'file_type' => 'string',
        'file_id' => 'int',
        'no' => 'int',
        'content' => 'json:unicode',
    ];

    protected $fillable = [
        'file_type',
        'file_id',
        'no',
        'content',
    ];

    public function file(): MorphTo
    {
        return $this->morphTo('file');
    }

    public function parsed_rows(): HasMany
    {
        return $this->hasMany(config('csv-parser.implementations.csv_parsed_row', CsvParsedRow::class), 'row_id')
            ->orderBy('order_number');
    }
}
