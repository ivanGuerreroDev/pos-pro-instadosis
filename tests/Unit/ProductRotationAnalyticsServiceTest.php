<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleDetails;
use App\Models\User;
use App\Services\ProductRotationAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductRotationAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_builds_fefo_batch_simulation_like_the_excel_example(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 25, 0, 0, 0));

        [$business, $product, $user] = $this->createBaseProductContext();

        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->subMonths(2)->startOfMonth()->addDays(3), 10);
        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->subMonths(1)->startOfMonth()->addDays(4), 10);
        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->startOfMonth()->addDays(5), 10);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-01',
            'quantity' => 9,
            'remaining_quantity' => 9,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(55)->toDateString(),
            'status' => 'active',
        ]);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-02',
            'quantity' => 100,
            'remaining_quantity' => 100,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(65)->toDateString(),
            'status' => 'active',
        ]);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-03',
            'quantity' => 200,
            'remaining_quantity' => 200,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(75)->toDateString(),
            'status' => 'active',
        ]);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-04',
            'quantity' => 3,
            'remaining_quantity' => 3,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(90)->toDateString(),
            'status' => 'active',
        ]);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-05',
            'quantity' => 4,
            'remaining_quantity' => 4,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(120)->toDateString(),
            'status' => 'active',
        ]);

        $service = app(ProductRotationAnalyticsService::class);
        $result = $service->getProductAnalytics($business->id, $product->id, 3);

        $this->assertSame(3, $result['period_months']);
        $this->assertEqualsWithDelta(10.00, $result['average_monthly_sales'], 0.01);
        $this->assertEqualsWithDelta(10.00, $result['conservative_monthly_sales'], 0.01);
        $this->assertEqualsWithDelta(0.33, $result['daily_consumption_units'], 0.01);
        $this->assertEquals(90, $result['target_period_days']);
        $this->assertEqualsWithDelta(30.00, $result['target_consumption_units'], 0.01);
        $this->assertEqualsWithDelta(316.00, $result['total_stock_units'], 0.01);
        $this->assertEqualsWithDelta(31.75, $result['total_useful_units'], 0.01);
        $this->assertEquals(0, $result['suggested_order_units']);
        $this->assertEqualsWithDelta(31.60, $result['projected_stockout_months'], 0.01);
        $this->assertEquals('high', $result['expiry_risk_level']);
        $this->assertEqualsWithDelta(284.25, $result['potentially_unsold_units'], 0.01);
        $this->assertCount(5, $result['batch_risk_simulation']);

        $this->assertSame('-50', $result['batch_risk_simulation'][0]['excess_display']);
        $this->assertEqualsWithDelta(9.00, $result['batch_risk_simulation'][0]['consumable_units'], 0.01);
        $this->assertEqualsWithDelta(12.45, $result['batch_risk_simulation'][1]['consumable_units'], 0.01);
        $this->assertSame('-88', $result['batch_risk_simulation'][1]['excess_display']);
        $this->assertEqualsWithDelta(3.30, $result['batch_risk_simulation'][2]['consumable_units'], 0.01);
        $this->assertSame('-197', $result['batch_risk_simulation'][2]['excess_display']);
        $this->assertEqualsWithDelta(3.00, $result['batch_risk_simulation'][3]['consumable_units'], 0.01);
        $this->assertEqualsWithDelta(4.00, $result['batch_risk_simulation'][4]['consumable_units'], 0.01);
    }

    public function test_it_marks_as_not_projectable_when_there_are_no_recent_sales(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 25, 0, 0, 0));

        [$business, $product] = $this->createBaseProductContext();

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-NO-SALES',
            'quantity' => 50,
            'remaining_quantity' => 50,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(25)->toDateString(),
            'status' => 'active',
        ]);

        $service = app(ProductRotationAnalyticsService::class);
        $result = $service->getProductAnalytics($business->id, $product->id, 3);

        $this->assertEqualsWithDelta(0.00, $result['average_monthly_sales'], 0.01);
        $this->assertEqualsWithDelta(0.00, $result['conservative_monthly_sales'], 0.01);
        $this->assertEqualsWithDelta(0.00, $result['daily_consumption_units'], 0.01);
        $this->assertEqualsWithDelta(0.00, $result['target_consumption_units'], 0.01);
        $this->assertEqualsWithDelta(0.00, $result['total_useful_units'], 0.01);
        $this->assertSame('--', $result['batch_risk_simulation'][0]['excess_display']);
        $this->assertEquals(0, $result['suggested_order_units']);
        $this->assertNull($result['projected_stockout_months']);
        $this->assertEqualsWithDelta(50.00, $result['potentially_unsold_units'], 0.01);
        $this->assertEquals('high', $result['expiry_risk_level']);
    }

    public function test_it_calculates_positive_suggested_order_when_useful_stock_is_short(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 25, 0, 0, 0));

        [$business, $product, $user] = $this->createBaseProductContext();

        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->subMonths(2)->startOfMonth()->addDays(3), 10);
        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->subMonths(1)->startOfMonth()->addDays(4), 10);
        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->startOfMonth()->addDays(5), 10);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-01',
            'quantity' => 9,
            'remaining_quantity' => 9,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(55)->toDateString(),
            'status' => 'active',
        ]);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-02',
            'quantity' => 100,
            'remaining_quantity' => 100,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(65)->toDateString(),
            'status' => 'active',
        ]);

        $service = app(ProductRotationAnalyticsService::class);
        $result = $service->getProductAnalytics($business->id, $product->id, 3);

        $this->assertEqualsWithDelta(21.45, $result['total_useful_units'], 0.01);
        $this->assertEquals(9, $result['suggested_order_units']);
        $this->assertStringContainsString('Se sugiere pedir 9 unidades adicionales.', $result['decision_summary']);
    }

    private function createBaseProductContext(): array
    {
        $businessCategory = BusinessCategory::query()->create([
            'name' => 'Farmacia',
            'status' => true,
        ]);

        $business = Business::query()->create([
            'business_category_id' => $businessCategory->id,
            'companyName' => 'Negocio Test',
        ]);

        $category = Category::query()->create([
            'categoryName' => 'Medicamentos',
            'business_id' => $business->id,
        ]);

        $product = Product::query()->create([
            'productName' => 'Producto Rotacion',
            'business_id' => $business->id,
            'category_id' => $category->id,
            'productCode' => 'PRD-' . uniqid(),
            'productStock' => 0,
            'track_by_batches' => true,
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Usuario Test',
            'email' => 'unit-' . uniqid() . '@example.com',
            'password' => 'secret123',
        ]);

        return [$business, $product, $user];
    }

    private function createSaleWithUnits(int $businessId, int $userId, int $productId, Carbon $saleDate, int $units): void
    {
        $saleId = DB::table('sales')->insertGetId([
            'business_id' => $businessId,
            'user_id' => $userId,
            'discountAmount' => 0,
            'dueAmount' => 0,
            'isPaid' => 1,
            'vat_amount' => 0,
            'vat_percent' => 0,
            'paidAmount' => 100,
            'totalAmount' => 100,
            'lossProfit' => 20,
            'paymentType' => 'cash',
            'invoiceNumber' => 'TEST-' . uniqid(),
            'saleDate' => $saleDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SaleDetails::query()->create([
            'sale_id' => $saleId,
            'product_id' => $productId,
            'price' => 10,
            'quantities' => $units,
        ]);
    }
}
