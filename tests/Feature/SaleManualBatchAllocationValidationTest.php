<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleManualBatchAllocationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sales_api_rejects_manual_expired_batch_allocation(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 8, 0, 0, 0));

        [$user, $product, $expiredBatch] = $this->createAuthenticatedContext();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sales', [
            'customer_name' => 'Cliente Ocasional',
            'customer_phone' => '60000000',
            'paymentType' => 'Cash',
            'paidAmount' => 100,
            'totalAmount' => 100,
            'discountAmount' => 0,
            'dueAmount' => 0,
            'vat_amount' => 0,
            'vat_percent' => 0,
            'products' => [
                [
                    'product_id' => $product->id,
                    'price' => 50,
                    'quantities' => 1,
                    'lossProfit' => 10,
                    'batch_allocations' => [
                        [
                            'batch_id' => $expiredBatch->id,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Error processing sale: Batch EXP-001 is expired');
    }

    private function createAuthenticatedContext(): array
    {
        $category = BusinessCategory::create([
            'name' => 'Categoria Ventas',
            'status' => true,
        ]);

        $business = Business::create([
            'business_category_id' => $category->id,
            'companyName' => 'Farmacia QA',
            'billing_status' => Business::BILLING_STATUS_ACTIVE,
            'emagic_api_key' => null,
            'billing_linked_at' => now(),
        ]);

        $user = User::create([
            'name' => 'Owner QA',
            'email' => 'owner-' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'shop-owner',
            'status' => Business::BILLING_STATUS_ACTIVE,
            'business_id' => $business->id,
        ]);

        $productCategory = Category::create([
            'categoryName' => 'Medicamentos',
            'business_id' => $business->id,
        ]);

        $product = Product::create([
            'productName' => 'Amoxicilina',
            'business_id' => $business->id,
            'category_id' => $productCategory->id,
            'productCode' => 'AMX-' . uniqid(),
            'productStock' => 20,
            'track_by_batches' => true,
            'tax_rate' => '0',
        ]);

        $expiredBatch = ProductBatch::create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'EXP-001',
            'quantity' => 5,
            'remaining_quantity' => 5,
            'purchase_price' => 3,
            'expiry_date' => Carbon::now()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        return [$user, $product, $expiredBatch];
    }
}
