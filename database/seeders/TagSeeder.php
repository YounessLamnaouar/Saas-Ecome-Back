<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        collect(['featured', 'sale', 'trending', 'new', 'premium', 'limited', 'bestseller', 'eco'])
            ->each(fn ($name) => Tag::firstOrCreate(['name' => $name]));
    }
}
