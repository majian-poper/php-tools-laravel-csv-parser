<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PHPTools\LaravelCsvParser\Contracts\HasUniqueKey;

class Contact extends Model implements HasUniqueKey
{
    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    public function getUniqueKeyName(): string
    {
        return 'email';
    }

    public function getUniqueKey(): string
    {
        return $this->email;
    }
}
