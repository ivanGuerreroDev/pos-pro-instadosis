<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashRegisterOpenCloseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_open_register_succeeds_with_valid_balance(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/cash-registers', [
            'opening_balance' => 100,
            'opening_notes' => 'Apertura de turno',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opening_balance', 100);

        $this->assertDatabaseHas('cash_registers', [
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'status' => 'open',
        ]);
    }

    public function test_opening_a_second_register_while_one_is_open_is_rejected(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 50])->assertOk();

        $response = $this->postJson('/api/v1/cash-registers', ['opening_balance' => 20]);

        $response->assertStatus(422);
        $this->assertSame(1, CashRegister::where('user_id', $user->id)->count());
    }

    public function test_a_different_user_in_the_same_business_can_open_concurrently(): void
    {
        $business = $this->createBusiness();
        $userA = $this->createUser($business, 'user-a@example.com');
        $userB = $this->createUser($business, 'user-b@example.com');

        Sanctum::actingAs($userA);
        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 50])->assertOk();

        Sanctum::actingAs($userB);
        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 30])->assertOk();

        $this->assertSame(2, CashRegister::where('business_id', $business->id)->where('status', 'open')->count());
    }

    public function test_close_computes_expected_balance_and_difference_from_cash_only_movements(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 23, 8, 0, 0));

        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 100])->assertOk();
        $cashRegister = CashRegister::where('user_id', $user->id)->where('status', 'open')->first();

        Carbon::setTestNow(Carbon::create(2026, 7, 23, 9, 0, 0));

        // Cash sale/income/expense count towards the arqueo.
        $this->createSale($user, 'Cash', 40);
        $this->createIncome($user, 'Cash', 10);
        $this->createExpense($user, 'Cash', 15);

        // Card sale must NOT inflate the physical cash expected balance.
        $this->createSale($user, 'Card', 500);

        Carbon::setTestNow(Carbon::create(2026, 7, 23, 18, 0, 0));

        $response = $this->postJson("/api/v1/cash-registers/{$cashRegister->id}/close", [
            'closing_counted_balance' => 130,
        ]);

        // expected = 100 (opening) + 40 (cash sale) + 10 (cash income) - 15 (cash expense) = 135
        // difference = counted (130) - expected (135) = -5 (faltante)
        // total_bank_sales = 500 (Card sale) - the non-cash breakdown must not
        // leak into the cash-only expected balance above.
        $response->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.total_sales', 40)
            ->assertJsonPath('data.total_income', 10)
            ->assertJsonPath('data.total_expense', 15)
            ->assertJsonPath('data.closing_expected_balance', 135)
            ->assertJsonPath('data.closing_counted_balance', 130)
            ->assertJsonPath('data.closing_difference', -5)
            ->assertJsonPath('data.total_bank_sales', 500)
            ->assertJsonPath('data.sales_by_payment_type.Cash', 40)
            ->assertJsonPath('data.sales_by_payment_type.Card', 500);
    }

    public function test_closing_an_already_closed_register_fails(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 100])->assertOk();
        $cashRegister = CashRegister::where('user_id', $user->id)->where('status', 'open')->first();

        $this->postJson("/api/v1/cash-registers/{$cashRegister->id}/close", ['closing_counted_balance' => 100])
            ->assertOk();

        $response = $this->postJson("/api/v1/cash-registers/{$cashRegister->id}/close", ['closing_counted_balance' => 100]);

        $response->assertStatus(422);
    }

    public function test_current_returns_null_when_no_register_is_open(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cash-registers/current');

        $response->assertOk()->assertJsonPath('data', null);
    }

    public function test_current_returns_live_totals_when_a_register_is_open(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cash-registers', ['opening_balance' => 100])->assertOk();
        $this->createSale($user, 'Cash', 25);

        $response = $this->getJson('/api/v1/cash-registers/current');

        $response->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.total_sales', 25)
            ->assertJsonPath('data.expected_balance', 125);
    }

    private function createBusiness(): Business
    {
        $category = BusinessCategory::create([
            'name' => 'Categoria Caja '.uniqid(),
            'status' => true,
        ]);

        return Business::create([
            'business_category_id' => $category->id,
            'companyName' => 'Farmacia Caja QA',
            'billing_status' => Business::BILLING_STATUS_ACTIVE,
            'billing_linked_at' => now(),
        ]);
    }

    private function createUser(?Business $business = null, string $email = 'cajero@example.com'): User
    {
        $business = $business ?? $this->createBusiness();

        return User::create([
            'name' => 'Cajero QA',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'status' => Business::BILLING_STATUS_ACTIVE,
            'business_id' => $business->id,
        ]);
    }

    private function createSale(User $user, string $paymentType, float $paidAmount): Sale
    {
        return Sale::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'paymentType' => $paymentType,
            'paidAmount' => $paidAmount,
            'totalAmount' => $paidAmount,
            'invoiceNumber' => 'S-TEST-'.uniqid(),
            'saleDate' => now(),
        ]);
    }

    private function createIncome(User $user, string $paymentType, float $amount): Income
    {
        $category = IncomeCategory::create([
            'categoryName' => 'Categoria Ingreso '.uniqid(),
            'business_id' => $user->business_id,
        ]);

        return Income::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'income_category_id' => $category->id,
            'amount' => $amount,
            'paymentType' => $paymentType,
            'incomeDate' => now(),
        ]);
    }

    private function createExpense(User $user, string $paymentType, float $amount): Expense
    {
        $category = ExpenseCategory::create([
            'categoryName' => 'Categoria Gasto '.uniqid(),
            'business_id' => $user->business_id,
        ]);

        return Expense::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'expense_category_id' => $category->id,
            'amount' => $amount,
            'paymentType' => $paymentType,
            'expenseDate' => now(),
        ]);
    }
}
