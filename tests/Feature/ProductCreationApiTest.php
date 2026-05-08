<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCreationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_defaults_empty_product_stock_to_zero(): void
    {
        [$business, $user, $category] = $this->createAuthenticatedContext();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/products', [
            'productName' => 'Panadol 500mg',
            'category_id' => $category->id,
            'productCode' => 'PAN-' . uniqid(),
            'productStock' => null,
            'productSalePrice' => 7,
            'productPurchasePrice' => 5,
            'track_by_batches' => true,
            'is_medicine' => true,
            'tax_rate' => '0',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.business_id', $business->id)
            ->assertJsonPath('data.productStock', 0)
            ->assertJsonPath('data.track_by_batches', true);

        $this->assertDatabaseHas('products', [
            'business_id' => $business->id,
            'productName' => 'Panadol 500mg',
            'productStock' => 0,
            'track_by_batches' => 1,
        ]);
    }

    private function createAuthenticatedContext(): array
    {
        $businessCategory = BusinessCategory::query()->create([
            'name' => 'Retail',
            'status' => true,
        ]);

        $business = Business::query()->create([
            'business_category_id' => $businessCategory->id,
            'companyName' => 'Negocio Producto Test',
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Usuario Producto',
            'email' => 'product-' . uniqid() . '@example.com',
            'password' => 'secret123',
        ]);

        $category = Category::query()->create([
            'categoryName' => 'Medicamentos',
            'business_id' => $business->id,
        ]);

        return [$business, $user, $category];
    }
}