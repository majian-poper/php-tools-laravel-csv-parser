<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use PHPTools\LaravelCsvParser\Contracts\RowParser;

class UserRowParser implements RowParser
{
    protected Validator $validator;

    public function __construct()
    {
        $this->validator = ValidatorFacade::make(
            [],
            [
                'name' => ['required', 'string', 'max:255'],
                'email_address' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            ]
        );
    }

    public function parse(array $row, int $no): \Generator
    {
        $user = User::query()->firstOrNew(
            ['email' => $row['email_address']],
            ['name' => $row['name']],
        );

        if (! $user->exists) {
            $user->password = 'password';
        }

        yield $user;
    }
}
