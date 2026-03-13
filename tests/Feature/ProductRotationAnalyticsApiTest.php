<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleDetails;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductRotationAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_rotation_endpoint_returns_expected_contract(): void
    {
        [$business, $user, $product] = $this->seedAnalyticsData();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/product-analytics/product/{$product->id}?months=3");

        $response
            ->assertOk()
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'product' => ['id', 'name', 'code'],
                    'period_months',
                    'monthly_sales_units',
                    'average_monthly_sales',
                    'conservative_monthly_sales',
                    'total_stock_units',
                    'projected_stockout_months',
                    'expiry_risk_percentage',
                    'expiry_risk_level',
                    'potentially_unsold_units',
                    'batch_risk_simulation',
                    'decision_summary',
                ],
            ]);

        $this->assertSame($business->id, $user->business_id);
    }

    public function test_summary_endpoint_returns_ranked_items(): void
    {
        [, $user] = $this->seedAnalyticsData();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/product-analytics/summary?months=3&limit=10');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'product_id',
                        'product_name',
                        'total_stock_units',
                        'projected_stockout_months',
                        'potentially_unsold_units',
                        'expiry_risk_percentage',
                        'expiry_risk_level',
                    ],
                ],
            ]);
    }

    public function test_product_rotation_endpoint_returns_not_found_for_foreign_business_product(): void
    {
        [$business, $user] = $this->seedAnalyticsData();
        $foreignProduct = $this->createForeignProduct();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/product-analytics/product/{$foreignProduct->id}?months=3");

        $response->assertNotFound();
        $this->assertNotEquals($business->id, $foreignProduct->business_id);
    }

    private function seedAnalyticsData(): array
    {
        $businessCategory = BusinessCategory::query()->create([
            'name' => 'Retail',
            'status' => true,
        ]);

        $business = Business::query()->create([
            'business_category_id' => $businessCategory->id,
            'companyName' => 'Negocio API Test',
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Usuario Feature',
            'email' => 'feature-' . uniqid() . '@example.com',
            'password' => 'secret123',
        ]);

        $category = Category::query()->create([
            'categoryName' => 'General',
            'business_id' => $business->id,
        ]);

        $product = Product::query()->create([
            'productName' => 'Producto API',
            'business_id' => $business->id,
            'category_id' => $category->id,
            'productCode' => 'API-' . uniqid(),
            'track_by_batches' => true,
        ]);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'API-LOT-1',
            'quantity' => 40,
            'remaining_quantity' => 40,
            'expiry_date' => Carbon::now()->addDays(20)->toDateString(),
            'status' => 'active',
        ]);

        $saleId = DB::table('sales')->insertGetId([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'discountAmount' => 0,
            'dueAmount' => 0,
            'isPaid' => 1,
            'vat_amount' => 0,
            'vat_percent' => 0,
            'paidAmount' => 50,
            'totalAmount' => 50,
            'lossProfit' => 10,
            'paymentType' => 'cash',
            'invoiceNumber' => 'TEST-API-' . uniqid(),
            'saleDate' => Carbon::now()->startOfMonth()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SaleDetails::query()->create([
            'sale_id' => $saleId,
            'product_id' => $product->id,
            'price' => 10,
            'quantities' => 5,
        ]);

        return [$business, $user, $product];
    }

    private function createForeignProduct(): Product
    {
        $businessCategory = BusinessCategory::query()->create([
            'name' => 'Foreign Category ' . uniqid(),
            'status' => true,
        ]);

        $business = Business::query()->create([
            'business_category_id' => $businessCategory->id,
            'companyName' => 'Otro Negocio',
        ]);

        $category = Category::query()->create([
            'categoryName' => 'Foreign Product Category',
            'business_id' => $business->id,
        ]);

        return Product::query()->create([
            'productName' => 'Producto Externo',
            'business_id' => $business->id,
            'category_id' => $category->id,
            'productCode' => 'EXT-' . uniqid(),
            'track_by_batches' => true,
        ]);
    }
}
