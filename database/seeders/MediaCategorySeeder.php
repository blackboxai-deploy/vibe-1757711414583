<?php

namespace Database\Seeders;

use App\Models\MediaCategory;
use Illuminate\Database\Seeder;

class MediaCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = MediaCategory::getDefaultCategories();

        foreach ($categories as $category) {
            MediaCategory::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}