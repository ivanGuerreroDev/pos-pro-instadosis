<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingLinkingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_sets_business_and_user_as_pending(): void
    {
        $this->createFreePlan();

        $user = User::factory()->create([
            'role' => 'shop-owner',
            'status' => null,
            'business_id' => null,
            'password' => 'secret123',
        ]);

        Sanctum::actingAs($user);

        $category = BusinessCategory::create([
            'name' => 'Categoria Onboarding',
            'status' => true,
        ]);

        $response = $this->postJson('/api/v1/business', [
            'companyName' => 'Farmacia Onboarding',
            'business_category_id' => $category->id,
            'phoneNumber' => '60000000',
            'dtipoRuc' => 'Jurídico',
            'druc' => '155123456-1-2026',
            'ddv' => '12',
            'dnombEm' => 'Farmacia Onboarding SA',
            'dcoordEm' => '8.9833,-79.5167',
            'ddirecEm' => 'Ciudad de Panama',
            'dcorreg' => '8-10',
            'ddistr' => '8',
            'dprov' => '8',
            'dtfnEm' => '60000000',
            'dcorElectEmi' => 'onboarding@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('billing_status', Business::BILLING_STATUS_PENDING);

        $user->refresh();
        $this->assertNotNull($user->business_id);
        $this->assertSame(Business::BILLING_STATUS_PENDING, $user->status);

        $this->assertDatabaseHas('businesses', [
            'id' => $user->business_id,
            'billing_status' => Business::BILLING_STATUS_PENDING,
        ]);
    }

    public function test_login_returns_billing_status_for_shop_owner(): void
    {
        $user = $this->createShopOwnerWithBusiness(
            Business::BILLING_STATUS_PENDING,
            null,
            'pending-owner@example.com',
            'secret123'
        );

        $response = $this->postJson('/api/v1/sign-in', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.billing_status', Business::BILLING_STATUS_PENDING)
            ->assertJsonPath('data.is_setup', true);
    }

    public function test_pending_business_blocks_sales_before_request_validation(): void
    {
        $user = $this->createShopOwnerWithBusiness(Business::BILLING_STATUS_PENDING, null);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sales', []);

        $response->assertForbidden()
            ->assertJsonPath('billing_status', Business::BILLING_STATUS_PENDING)
            ->assertJsonPath(
                'message',
                'Billing integration is pending. Please contact admin to link EMAGIC before invoicing.'
            );
    }

    public function test_pending_business_blocks_dgi_pdf_before_request_validation(): void
    {
        $user = $this->createShopOwnerWithBusiness(Business::BILLING_STATUS_PENDING, null);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/dgi-pdf', []);

        $response->assertForbidden()
            ->assertJsonPath('billing_status', Business::BILLING_STATUS_PENDING)
            ->assertJsonPath(
                'message',
                'Billing integration is pending. Please contact admin to link EMAGIC before invoicing.'
            );
    }

    public function test_active_business_is_not_blocked_by_billing_guard(): void
    {
        $user = $this->createShopOwnerWithBusiness(Business::BILLING_STATUS_ACTIVE, '2423098a70f3496d8e8a9d5f8b582034');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sales', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products']);
    }

    public function test_active_business_dgi_pdf_reaches_validation_layer(): void
    {
        $user = $this->createShopOwnerWithBusiness(Business::BILLING_STATUS_ACTIVE, '2423098a70f3496d8e8a9d5f8b582034');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/dgi-pdf', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sale_id']);
    }

    private function createFreePlan(): Plan
    {
        return Plan::create([
            'subscriptionName' => 'Free Plan QA',
            'duration' => 30,
            'offerPrice' => 0,
            'subscriptionPrice' => 0,
            'status' => true,
        ]);
    }

    private function createShopOwnerWithBusiness(
        string $billingStatus,
        ?string $apiKey,
        string $email = 'shop-owner-test@example.com',
        string $password = 'secret123'
    ): User {
        $category = BusinessCategory::create([
            'name' => 'Farmacia Test '.uniqid(),
            'status' => true,
        ]);

        $business = Business::create([
            'business_category_id' => $category->id,
            'companyName' => 'Business QA',
            'billing_status' => $billingStatus,
            'emagic_api_key' => $apiKey,
            'billing_linked_at' => $billingStatus === Business::BILLING_STATUS_ACTIVE ? now() : null,
        ]);

        return User::create([
            'name' => 'Owner QA',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'shop-owner',
            'status' => $billingStatus,
            'business_id' => $business->id,
        ]);
    }
}
