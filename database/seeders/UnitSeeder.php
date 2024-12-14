<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = array(
            array('unitName' => 'Kg','business_id' => 1,'status' => 1,'created_at' => now(),'updated_at' => now())
        );

        Unit::insert($units);
    }
}
