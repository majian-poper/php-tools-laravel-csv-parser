<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPTools\LaravelCsvParser\Contracts\HasUniqueKey;

class User extends Authenticatable implements HasUniqueKey
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getUniqueKeyName(): string
    {
        return 'email';
    }

    public function getUniqueKey(): string
    {
        return $this->email;
    }

    public function getForeignModelKeys(): array
    {
        return [];
    }
}
