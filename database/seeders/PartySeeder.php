<?php

namespace Database\Seeders;

use App\Models\Party;
use Illuminate\Database\Seeder;

class PartySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parties = array(
            array('name' => 'Alif Khan','business_id' => 1,'email' => 'test@test.com','type' => 'retailer','phone' => '217346120736','due' => 0,'address' => 'Dhaka, Bangladesh','image' => NULL,'status' => 1,'created_at' =>now(),'updated_at' =>now()),

            array('name' => 'Barishal Khan','business_id' => 1,'email' => 'test1@test.com','type' => 'retailer','phone' => '217346736','due' => 0,'address' => 'Dhaka, Bangladesh','image' => NULL,'status' => 1,'created_at' =>now(),'updated_at' =>now())
        );

        Party::insert($parties);
    }
}
