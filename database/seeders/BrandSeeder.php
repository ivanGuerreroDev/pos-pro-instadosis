<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = array(
            array('business_id' => 1,'brandName' => 'Samsung','status' => 1,'created_at' => now(),'updated_at' => now())
        );

        Brand::insert($brands);
    }
}
