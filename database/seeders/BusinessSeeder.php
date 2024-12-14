<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = array(
            array('plan_subscribe_id' => '1','business_category_id' => '1','companyName' => 'Acnoo','will_expire' => '2024-09-05','address' => 'Dhaka','phoneNumber' => '1871165401','pictureUrl' => NULL,'subscriptionDate' => '2024-08-29 09:52:37','remainingShopBalance' => '0','shopOpeningBalance' => '100','created_at' => '2024-08-29 09:52:37','updated_at' => '2024-08-29 09:52:37'),
            array('plan_subscribe_id' => '2','business_category_id' => '2','companyName' => 'Maan Theme','will_expire' => '2024-09-28','address' => 'Dhaka','phoneNumber' => '1871165401','pictureUrl' => NULL,'subscriptionDate' => '2024-08-29 09:53:22','remainingShopBalance' => '0','shopOpeningBalance' => '200','created_at' => '2024-08-29 09:53:22','updated_at' => '2024-08-29 09:53:22'),
            array('plan_subscribe_id' => '3','business_category_id' => '3','companyName' => 'Dhaka IT','will_expire' => '2025-02-25','address' => 'Dhaka','phoneNumber' => '1871165401','pictureUrl' => NULL,'subscriptionDate' => '2024-08-29 09:53:55','remainingShopBalance' => '0','shopOpeningBalance' => '500','created_at' => '2024-08-29 09:53:55','updated_at' => '2024-08-29 09:53:55'),
            array('plan_subscribe_id' => '1','business_category_id' => '1','companyName' => 'Trade G','will_expire' => '2024-10-26','address' => 'dhaka','phoneNumber' => '3452534','pictureUrl' => NULL,'subscriptionDate' => '2024-09-26 12:12:22','remainingShopBalance' => '0','shopOpeningBalance' => '10000','created_at' => '2024-09-26 12:12:22','updated_at' => '2024-09-26 12:12:22')
          );

        Business::insert($businesses);
    }
}
