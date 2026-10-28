<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TagSeeder::class,
            ProductSeeder::class,
            CollectionSeeder::class,
            AdminUserSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
