<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\BatchAllocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manual_allocation_rejects_expired_batches(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 8, 0, 0, 0));

        [$product] = $this->createProductContext();

        $expiredBatch = ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $product->business_id,
            'batch_number' => 'EXP-001',
            'quantity' => 10,
            'remaining_quantity' => 10,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        $service = app(BatchAllocationService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('is expired');

        $service->allocateBatchesForSale(
            $product->id,
            2,
            [
                ['batch_id' => $expiredBatch->id, 'quantity' => 2],
            ]
        );
    }

    public function test_fefo_allocation_skips_expired_and_uses_next_batch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 8, 0, 0, 0));

        [$product] = $this->createProductContext();

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $product->business_id,
            'batch_number' => 'EXP-001',
            'quantity' => 10,
            'remaining_quantity' => 10,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        $validBatch = ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $product->business_id,
            'batch_number' => 'VAL-001',
            'quantity' => 10,
            'remaining_quantity' => 10,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(15)->toDateString(),
            'status' => 'active',
        ]);

        $service = app(BatchAllocationService::class);

        $allocation = $service->allocateBatchesForSale($product->id, 3);

        $this->assertCount(1, $allocation);
        $this->assertSame($validBatch->id, $allocation[0]['batch_id']);
        $this->assertSame(3, (int) $allocation[0]['quantity']);
    }

    public function test_fefo_allocation_prioritizes_closest_non_expired_batch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 8, 0, 0, 0));

        [$product] = $this->createProductContext();

        ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $product->business_id,
            'batch_number' => 'EXP-001',
            'quantity' => 10,
            'remaining_quantity' => 10,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        $nearBatch = ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $product->business_id,
            'batch_number' => 'NEAR-001',
            'quantity' => 5,
            'remaining_quantity' => 5,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);

        $nextBatch = ProductBatch::query()->create([
            'product_id' => $product->id,
            'business_id' => $product->business_id,
            'batch_number' => 'NEXT-001',
            'quantity' => 10,
            'remaining_quantity' => 10,
            'purchase_price' => 5,
            'expiry_date' => Carbon::now()->addDays(20)->toDateString(),
            'status' => 'active',
        ]);

        $service = app(BatchAllocationService::class);

        $allocation = $service->allocateBatchesForSale($product->id, 8);

        $this->assertCount(2, $allocation);
        $this->assertSame($nearBatch->id, $allocation[0]['batch_id']);
        $this->assertSame(5, (int) $allocation[0]['quantity']);
        $this->assertSame($nextBatch->id, $allocation[1]['batch_id']);
        $this->assertSame(3, (int) $allocation[1]['quantity']);
    }

    private function createProductContext(): array
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
            'productName' => 'Producto Lotes',
            'business_id' => $business->id,
            'category_id' => $category->id,
            'productCode' => 'PRD-' . uniqid(),
            'productStock' => 0,
            'track_by_batches' => true,
        ]);

        return [$product, $business];
    }
}
