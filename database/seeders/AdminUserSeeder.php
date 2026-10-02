<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@saas-ecome.test'],
            [
                'name' => 'Store Admin',
                'password' => Hash::make('password'), // change immediately outside local dev
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
