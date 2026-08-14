<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Flat base taxonomy for MVP. Categories are a real table (not an enum)
     * specifically so this can grow into a nested tree later without a migration.
     */
    public function run(): void
    {
        $categories = [
            'Concerts',
            'Festivals',
            'Theatre & Shows',
            'Exhibitions',
            'Family & Kids',
            'Popular & Special Events',
            'Markets & Fairs',
            'Sports',
        ];

        foreach ($categories as $sortOrder => $name) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $sortOrder]
            );
        }
    }
}
