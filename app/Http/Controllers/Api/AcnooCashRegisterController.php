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
        if (!(auth()->user()->visibility['cashRegisterPermission'] ?? true)) {
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

        $sales = Sale::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('paymentType', 'Cash')
            ->whereBetween('created_at', $range)
            ->sum('paidAmount');

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
            'data' => $cashRegister->fresh()->load('user:id,name', 'closedByUser:id,name'),
        ]);
    }
}
