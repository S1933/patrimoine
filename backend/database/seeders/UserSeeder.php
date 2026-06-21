<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@patrimoine.local');
        $password = env('ADMIN_PASSWORD', 'changeme-admin');
        $name = env('ADMIN_NAME', 'Admin');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'base_currency' => User::DEFAULT_CURRENCY,
                'email_verified_at' => now(),
            ]
        );
    }
}
