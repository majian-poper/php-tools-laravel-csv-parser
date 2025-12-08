<?php

namespace PHPTools\LaravelCsvParser\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $file_type
 * @property int $file_id
 * @property int $no
 * @property int $row_id
 * @property int $order_number
 * @property string $model_type
 * @property int $model_id
 * @property string $model_unique_key
 * @property array $values
 * @property array $errors
 * @property-read \PHPTools\LaravelCsvParser\Contracts\CsvFile & Model $file
 * @property-read CsvRow $row
 * @property-read Model | null $model
 *
 * @method static Builder | CsvParsedRow errors()
 */
class CsvParsedRow extends Model
{
    protected $casts = [
        'file_type' => 'string',
        'file_id' => 'int',
        'no' => 'int',
        'row_id' => 'int',
        'order_number' => 'int',
        'model_type' => 'string',
        'model_id' => 'int',
        'model_unique_key' => 'string',
        'values' => 'json:unicode',
        'errors' => 'json:unicode',
    ];

    protected $fillable = [
        'file_type',
        'file_id',
        'no',
        'row_id',
        'order_number',
        'model_type',
        'model_id',
        'model_unique_key',
        'values',
        'errors',
    ];

    public function file(): MorphTo
    {
        return $this->morphTo('file');
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(config('csv-parser.implementations.csv_row', CsvRow::class), 'row_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo('model')->withTrashed()->withDefault();
    }
}
