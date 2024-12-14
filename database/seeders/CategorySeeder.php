<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = array(
            array('categoryName' => 'Fashion','business_id' => 1,'variationCapacity' => 1,'variationColor' => 1,'variationSize' => 1,'variationType' => 1,'variationWeight' => 1,'status' => 0,'created_at' => now(),'updated_at' => now())
        );

        Category::insert($categories);
    }
}
