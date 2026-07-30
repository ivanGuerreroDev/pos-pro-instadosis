<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleBlockedWithoutOpenCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_is_blocked_when_user_has_no_open_cash_register(): void
    {
        [$user, $product] = $this->createAuthenticatedContext();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sales', $this->salePayload($product));

        $response->assertStatus(403)
            ->assertJsonPath('cash_register_required', true)
            ->assertJsonPath('message', 'No hay una caja abierta. Debes abrir caja antes de vender.');
    }

    public function test_sale_succeeds_when_user_has_an_open_cash_register(): void
    {
        [$user, $product] = $this->createAuthenticatedContext();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 100])->assertOk();

        $response = $this->postJson('/api/v1/sales', $this->salePayload($product));

        $response->assertOk();
    }

    private function salePayload(Product $product): array
    {
        return [
            'customer_name' => 'Cliente Ocasional',
            'customer_phone' => '60000000',
            'paymentType' => 'Cash',
            'paidAmount' => 50,
            'totalAmount' => 50,
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
                ],
            ],
        ];
    }

    private function createAuthenticatedContext(): array
    {
        $category = BusinessCategory::create([
            'name' => 'Categoria Bloqueo '.uniqid(),
            'status' => true,
        ]);

        $business = Business::create([
            'business_category_id' => $category->id,
            'companyName' => 'Farmacia Bloqueo QA',
            'billing_status' => Business::BILLING_STATUS_ACTIVE,
            'billing_linked_at' => now(),
        ]);

        $user = User::create([
            'name' => 'Cajero QA',
            'email' => 'cajero-bloqueo-'.uniqid().'@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'status' => Business::BILLING_STATUS_ACTIVE,
            'business_id' => $business->id,
        ]);

        $productCategory = Category::create([
            'categoryName' => 'Medicamentos',
            'business_id' => $business->id,
        ]);

        $product = Product::create([
            'productName' => 'Ibuprofeno',
            'business_id' => $business->id,
            'category_id' => $productCategory->id,
            'productCode' => 'IBU-'.uniqid(),
            'productStock' => 20,
            'track_by_batches' => false,
            'tax_rate' => '0',
        ]);

        return [$user, $product];
    }
}
