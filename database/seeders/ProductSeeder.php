<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = array(
            array('productName' => 'Banana','business_id' => 1, 'unit_id' => 1, 'brand_id' => 1, 'category_id' => 1, 'productCode' => 'ABCDEF', 'productPicture' => NULL, 'productDealerPrice' => 10, 'productPurchasePrice' => 10, 'productSalePrice' => 10, 'productWholeSalePrice' => 10, 'productStock' => 0, 'size' => 'small', 'type' => 'type', 'color' => 'Black', 'weight' => 'weight', 'capacity' => 'capacity', 'productManufacturer' => 'productManufacturer', 'created_at' => now(),'updated_at' => now()),

            array('productName' => 'Honey','business_id' => 1, 'unit_id' => 1, 'brand_id' => 1, 'category_id' => 1, 'productCode' => 'ABCDE', 'productPicture' => NULL, 'productDealerPrice' => 10, 'productPurchasePrice' => 10, 'productSalePrice' => 10, 'productWholeSalePrice' => 10, 'productStock' => 0, 'size' => 'small', 'type' => 'type', 'color' => 'Black', 'weight' => 'weight', 'capacity' => 'capacity', 'productManufacturer' => 'productManufacturer', 'created_at' => now(),'updated_at' => now()),

            array('productName' => 'Olive Oil','business_id' => 1, 'unit_id' => 1, 'brand_id' => 1, 'category_id' => 1, 'productCode' => 'ABCD', 'productPicture' => NULL, 'productDealerPrice' => 10, 'productPurchasePrice' => 10, 'productSalePrice' => 10, 'productWholeSalePrice' => 10, 'productStock' => 0, 'size' => 'small', 'type' => 'type', 'color' => 'Black', 'weight' => 'weight', 'capacity' => 'capacity', 'productManufacturer' => 'productManufacturer', 'created_at' => now(),'updated_at' => now())
        );

        Product::insert($products);
    }
}
