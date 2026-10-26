<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (Artisan::call('catalog:import-dummyjson') !== 0) {
            throw new \RuntimeException('DummyJSON import failed.');
        }
        $this->command?->info(Artisan::output());
    }
}
