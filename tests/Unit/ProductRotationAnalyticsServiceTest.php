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

    public function test_it_calculates_average_projection_and_risk_for_product(): void
    {
        [$business, $product, $user] = $this->createBaseProductContext();

        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->subMonths(2)->startOfMonth()->addDays(3), 30);
        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->subMonths(1)->startOfMonth()->addDays(4), 20);
        $this->createSaleWithUnits($business->id, $user->id, $product->id, Carbon::now()->startOfMonth()->addDays(5), 10);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-A',
            'quantity' => 100,
            'remaining_quantity' => 100,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(60)->toDateString(),
            'status' => 'active',
        ]);

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $business->id,
            'batch_number' => 'LOT-B',
            'quantity' => 20,
            'remaining_quantity' => 20,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);

        $service = app(ProductRotationAnalyticsService::class);
        $result = $service->getProductAnalytics($business->id, $product->id, 3);

        $this->assertSame(3, $result['period_months']);
        $this->assertEqualsWithDelta(20.00, $result['average_monthly_sales'], 0.01);
        $this->assertEqualsWithDelta(10.00, $result['conservative_monthly_sales'], 0.01);
        $this->assertEqualsWithDelta(120.00, $result['total_stock_units'], 0.01);
        $this->assertEqualsWithDelta(12.00, $result['projected_stockout_months'], 0.01);
        $this->assertEquals('high', $result['expiry_risk_level']);
        $this->assertGreaterThan(0, $result['potentially_unsold_units']);
        $this->assertCount(2, $result['batch_risk_simulation']);
    }

    public function test_it_marks_as_not_projectable_when_there_are_no_recent_sales(): void
    {
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
        $this->assertNull($result['projected_stockout_months']);
        $this->assertEqualsWithDelta(50.00, $result['potentially_unsold_units'], 0.01);
        $this->assertEquals('high', $result['expiry_risk_level']);
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
