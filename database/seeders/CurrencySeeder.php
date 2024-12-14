<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $currencies = [
            ['name' => 'Bangladeshi Taka','code' => 'BDT','rate' => '100','symbol' => '৳','position' => 'right','status' => '1','is_default' => '0','created_at' => now(),'updated_at' => now()],
            ['name' => 'US Dollar','code' => 'USD','rate' => '1','symbol' => '$','position' => 'left','status' => '1','is_default' => '1','created_at' => now(),'updated_at' => now()],
            ['name' => 'Euro','code' => 'EUR','rate' => '0.98','symbol' => '€','position' => 'left','status' => '1','is_default' => '0','created_at' => now(),'updated_at' => now()],
            ['name' => 'Indian Rupee','code' => 'INR','rate' => '79.37','symbol' => '₹','position' => 'left','status' => '1','is_default' => '0','created_at' => now(),'updated_at' => now()],
            ['name' => 'Nigerian Naira','code' => 'NGN','rate' => '417.57','symbol' => '₦','position' => 'left','status' => '1','is_default' => '0','created_at' => now(),'updated_at' => now()],
            ['name' => 'Malaysian Ringgit','code' => 'MYR','rate' => '4.46','symbol' => 'RM','position' => 'left','status' => '1','is_default' => '0','created_at' => now(),'updated_at' => now()],
            ['name' => 'Omani rial','code' => 'OMR','rate' => '0.39','symbol' => 'ر.ع.','position' => 'right','status' => '1','is_default' => '0','created_at' => now(),'updated_at' => now()]
        ];

        Currency::insert($currencies);
    }
}
