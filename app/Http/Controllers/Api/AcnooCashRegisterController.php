<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\Income;
use App\Models\Expense;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AcnooCashRegisterController extends Controller
{
    protected function ensureCashRegisterPermissionOrFail()
    {
        $user = auth()->user();
        // Only staff (Pos) accounts run shifts at the till — the business
        // owner manages the account but doesn't open/close cash registers.
        // This mirrors canManageCashRegister() on the Flutter client, which
        // only guards the UI; without this the rule was bypassable by
        // calling the API directly.
        $canManage = $user->role === 'staff' && ($user->visibility['cashRegisterPermission'] ?? true);

        if (!$canManage) {
            return response()->json([
                'message' => __('You do not have permission to manage the cash register.'),
            ], 403);
        }

        return null;
    }

    protected function consolidate(CashRegister $cashRegister, $endAt)
    {
        $businessId = $cashRegister->business_id;
        $userId = $cashRegister->user_id;
        $range = [$cashRegister->opened_at, $endAt];

        // Money actually collected per sale, grouped by how it was paid, so the
        // closing screen can show every payment method instead of only cash.
        $salesByPaymentType = Sale::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->whereBetween('created_at', $range)
            ->selectRaw('paymentType, SUM(paidAmount) as total')
            ->groupBy('paymentType')
            ->pluck('total', 'paymentType')
            ->map(fn ($total) => (float) $total);

        $sales = $salesByPaymentType->get('Cash', 0);

        // Anything not paid in cash (Card, Check, Mobile Pay, ...) settles to
        // the bank, not the physical drawer. "Due" isn't collected money yet,
        // so it's excluded from both the cash and the bank totals.
        $bankSales = $salesByPaymentType->except(['Cash', 'Due'])->sum();

        $income = Income::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('paymentType', 'Cash')
            ->whereBetween('created_at', $range)
            ->sum('amount');

        $expense = Expense::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('paymentType', 'Cash')
            ->whereBetween('created_at', $range)
            ->sum('amount');

        return [
            'total_sales' => (float) $sales,
            'total_income' => (float) $income,
            'total_expense' => (float) $expense,
            'expected_balance' => $cashRegister->opening_balance + $sales + $income - $expense,
            'sales_by_payment_type' => $salesByPaymentType,
            'total_bank_sales' => (float) $bankSales,
        ];
    }

    /**
     * Display the current user's open cash register, if any.
     */
    public function current()
    {
        $cashRegister = CashRegister::with('user:id,name')
            ->where('business_id', auth()->user()->business_id)
            ->where('user_id', auth()->id())
            ->where('status', CashRegister::STATUS_OPEN)
            ->latest('opened_at')
            ->first();

        if (!$cashRegister) {
            return response()->json([
                'message' => __('Data fetched successfully.'),
                'data' => null,
            ]);
        }

        $totals = $this->consolidate($cashRegister, now());

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => array_merge($cashRegister->toArray(), $totals),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = CashRegister::with('user:id,name', 'closedByUser:id,name')
            ->where('business_id', auth()->user()->business_id)
            ->where('status', CashRegister::STATUS_CLOSED)
            ->when($request->user_id, function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when($request->from, function ($query) use ($request) {
                $query->whereDate('opened_at', '>=', $request->from);
            })
            ->when($request->to, function ($query) use ($request) {
                $query->whereDate('closed_at', '<=', $request->to);
            })
            ->latest('closed_at')
            ->get();

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $data,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(CashRegister $cashRegister)
    {
        if ($cashRegister->business_id != auth()->user()->business_id) {
            return response()->json([
                'message' => __('Cash register not found.'),
            ], 404);
        }

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $cashRegister->load('user:id,name', 'closedByUser:id,name'),
        ]);
    }

    /**
     * Store a newly created resource in storage (open a register).
     */
    public function store(Request $request)
    {
        $guardResponse = $this->ensureCashRegisterPermissionOrFail();
        if ($guardResponse) {
            return $guardResponse;
        }

        $alreadyOpen = CashRegister::where('business_id', auth()->user()->business_id)
            ->where('user_id', auth()->id())
            ->where('status', CashRegister::STATUS_OPEN)
            ->exists();

        if ($alreadyOpen) {
            return response()->json([
                'message' => __('Ya tienes una caja abierta.'),
            ], 422);
        }

        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string',
        ]);

        $data = CashRegister::create([
            'business_id' => auth()->user()->business_id,
            'user_id' => auth()->id(),
            'status' => CashRegister::STATUS_OPEN,
            'opening_balance' => $request->opening_balance,
            'opening_notes' => $request->opening_notes,
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => __('Data saved successfully.'),
            'data' => $data,
        ]);
    }

    /**
     * Close the specified cash register with the counted balance (arqueo).
     */
    public function close(Request $request, CashRegister $cashRegister)
    {
        $guardResponse = $this->ensureCashRegisterPermissionOrFail();
        if ($guardResponse) {
            return $guardResponse;
        }

        if ($cashRegister->business_id != auth()->user()->business_id || $cashRegister->user_id != auth()->id()) {
            return response()->json([
                'message' => __('Cash register not found.'),
            ], 404);
        }

        if ($cashRegister->status !== CashRegister::STATUS_OPEN) {
            return response()->json([
                'message' => __('This cash register is already closed.'),
            ], 422);
        }

        $request->validate([
            'closing_counted_balance' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string',
        ]);

        $closedAt = now();
        $totals = $this->consolidate($cashRegister, $closedAt);
        $difference = $request->closing_counted_balance - $totals['expected_balance'];

        $cashRegister->update([
            'status' => CashRegister::STATUS_CLOSED,
            'closed_by_user_id' => auth()->id(),
            'closed_at' => $closedAt,
            'total_sales' => $totals['total_sales'],
            'total_income' => $totals['total_income'],
            'total_expense' => $totals['total_expense'],
            'closing_expected_balance' => $totals['expected_balance'],
            'closing_counted_balance' => $request->closing_counted_balance,
            'closing_difference' => $difference,
            'closing_notes' => $request->closing_notes,
        ]);

        return response()->json([
            'message' => __('Data saved successfully.'),
            // Merge in the payment-method breakdown, which isn't a persisted
            // column, so the close confirmation shows the same figures the
            // pre-close preview (current()) already did.
            'data' => array_merge(
                $cashRegister->fresh()->load('user:id,name', 'closedByUser:id,name')->toArray(),
                [
                    'sales_by_payment_type' => $totals['sales_by_payment_type'],
                    'total_bank_sales' => $totals['total_bank_sales'],
                ]
            ),
        ]);
    }
}
