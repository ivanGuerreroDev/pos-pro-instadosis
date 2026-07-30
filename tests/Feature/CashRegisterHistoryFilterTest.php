<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashRegisterHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_history_filters_by_date_range_and_scopes_by_business(): void
    {
        $businessA = $this->createBusiness('A');
        $userA = $this->createUser($businessA, 'history-a@example.com');
        $businessB = $this->createBusiness('B');
        $userB = $this->createUser($businessB, 'history-b@example.com');

        // Register 1: closed in January, belongs to business A.
        Carbon::setTestNow(Carbon::create(2026, 1, 10, 8, 0, 0));
        Sanctum::actingAs($userA);
        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 50])->assertOk();
        $reg1 = \App\Models\CashRegister::where('user_id', $userA->id)->first();
        $this->postJson("/api/v1/cash-registers/{$reg1->id}/close", ['closing_counted_balance' => 50])->assertOk();

        // Register 2: closed in July, belongs to business A.
        Carbon::setTestNow(Carbon::create(2026, 7, 20, 8, 0, 0));
        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 60])->assertOk();
        $reg2 = \App\Models\CashRegister::where('user_id', $userA->id)->where('status', 'open')->first();
        $this->postJson("/api/v1/cash-registers/{$reg2->id}/close", ['closing_counted_balance' => 60])->assertOk();

        // Register 3: closed in July, belongs to business B (must never leak into A's history).
        Sanctum::actingAs($userB);
        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 10])->assertOk();
        $reg3 = \App\Models\CashRegister::where('user_id', $userB->id)->first();
        $this->postJson("/api/v1/cash-registers/{$reg3->id}/close", ['closing_counted_balance' => 10])->assertOk();

        Sanctum::actingAs($userA);
        $response = $this->getJson('/api/v1/cash-registers?from=2026-07-01&to=2026-07-31');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($reg2->id, $ids);
        $this->assertNotContains($reg1->id, $ids, 'January register must be excluded by the date filter.');
        $this->assertNotContains($reg3->id, $ids, 'Other business register must never appear.');
    }

    private function createBusiness(string $label): Business
    {
        $category = BusinessCategory::create([
            'name' => 'Categoria Historial '.$label.' '.uniqid(),
            'status' => true,
        ]);

        return Business::create([
            'business_category_id' => $category->id,
            'companyName' => 'Farmacia Historial '.$label,
            'billing_status' => Business::BILLING_STATUS_ACTIVE,
            'billing_linked_at' => now(),
        ]);
    }

    private function createUser(Business $business, string $email): User
    {
        return User::create([
            'name' => 'Cajero Historial',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'status' => Business::BILLING_STATUS_ACTIVE,
            'business_id' => $business->id,
        ]);
    }
}
