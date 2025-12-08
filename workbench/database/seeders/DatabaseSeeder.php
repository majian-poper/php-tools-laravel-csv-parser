<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->create(
            [
                'name' => 'Alice Williams',
                'email' => 'alice@example.com',
                'password' => 'p@ssw0rd',
            ]
        );
    }
}
