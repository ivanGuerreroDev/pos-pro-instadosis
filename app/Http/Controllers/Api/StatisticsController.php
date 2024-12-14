<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Party;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class StatisticsController extends Controller
{
    public function summary()
    {
        $business_id = auth()->user()->business_id;
        $data = [
            'sales' => Sale::where('business_id', $business_id)->whereDate('created_at', request('date') ?? today())->sum('totalAmount'),
            'income' => Sale::where('business_id', $business_id)->whereDate('created_at', request('date') ?? today())->sum('lossProfit'),
            'expense' => Expense::where('business_id', $business_id)->whereDate('created_at', request('date') ?? today())->sum('amount'),
            'purchase' => Purchase::where('business_id', $business_id)->whereDate('created_at', request('date') ?? today())->sum('totalAmount'),
        ];

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $data,
        ]);
    }

    public function dashboard()
    {
        $currentDate = Carbon::now();
        switch (request('duration')) {
            case 'weekly':
                $start = $currentDate->copy()->startOfWeek(Carbon::SATURDAY);
                $end = $currentDate->copy()->endOfWeek(Carbon::FRIDAY);
                $format = 'D';
                $period = $start->daysUntil($end);
                break;

            case 'monthly':
                $start = $currentDate->copy()->startOfMonth();
                $end = $currentDate->copy()->endOfMonth();
                $format = 'd';
                $period = $start->daysUntil($end);
                break;

            case 'yearly':
                $start = $currentDate->copy()->startOfYear();
                $end = $currentDate->copy()->endOfYear();
                $format = 'M';
                $period = $start->monthsUntil($end);
                break;

            default:
                return response()->json(['error' => 'Invalid duration'], 400);
        }

        $business_id = auth()->user()->business_id;

        $sales_data = DB::table('sales')
                        ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"), DB::raw("SUM(totalAmount) as amount"))
                        ->where('business_id', auth()->user()->business_id)
                        ->whereBetween('created_at', [$start, $end])
                        ->groupBy('date')
                        ->orderBy('date')
                        ->get()
                        ->keyBy('date');

        $purchase_data = DB::table('purchases')
                            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"), DB::raw("SUM(totalAmount) as amount"))
                            ->where('business_id', auth()->user()->business_id)
                            ->whereBetween('created_at', [$start, $end])
                            ->groupBy('date')
                            ->orderBy('date')
                            ->get()
                            ->keyBy('date');

        // Total Income
        $income_amount = Income::where('business_id', $business_id)->whereBetween('created_at', [$start, $end])->sum('amount');
        $sale_profit = Sale::where('business_id', $business_id)->whereBetween('created_at', [$start, $end])->where('lossProfit', '>', 0)->sum('lossProfit');
        // Total Expnese
        $expense_amount = Expense::where('business_id', $business_id)->whereBetween('created_at', [$start, $end])->sum('amount');
        $purchase_amount = Purchase::where('business_id', $business_id)->whereBetween('created_at', [$start, $end])->sum('totalAmount');

        $data = [

            'total_income' => (int) $income_amount + $sale_profit,
            'total_expense' => (int) $expense_amount + $purchase_amount,
            'total_items' => Product::where('business_id', auth()->user()->business_id)->whereBetween('created_at', [$start, $end])->count(),
            'total_categories' => Category::where('business_id', auth()->user()->business_id)->whereBetween('created_at', [$start, $end])->count(),
            'stock_value' => (int) Product::where('business_id', auth()->user()->business_id)->sum(DB::raw('productPurchasePrice * productStock')),
            'total_due' => (int) Sale::where('business_id', auth()->user()->business_id)->whereBetween('saleDate', [$start, $end])->sum('dueAmount'),
            'total_profit' => (int) Sale::where('business_id', auth()->user()->business_id)->whereBetween('created_at', [$start, $end])->sum('lossProfit'),
            'total_loss' => (int) Sale::where('business_id', auth()->user()->business_id)->whereBetween('created_at', [$start, $end])->where('lossProfit', '<', 0)->sum('lossProfit'),
            'sales' => $this->formatData($period, $sales_data, $format),
            'purchases' =>$this->formatData($period, $purchase_data, $format),
        ];

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $data,
        ]);
    }

    private function formatData($period, $datas, $format)
    {
        $rows = [];
        foreach ($period as $date) {
            if (request('duration') == 'yearly') {
                $key = $date->format($format);
                $dateKey = $date->format('Y-m'); // For lookup purposes
                $amount = $datas->filter(function ($value, $key) use ($dateKey) {
                    return strpos($value->date, $dateKey) === 0;
                })->sum('amount');
            } else {
                $key = $date->format($format);
                $amount = $datas->get($date->format('Y-m-d'))?->amount ?? 0;
            }

            $rows[] = [
                'date' => $key,
                'amount' => $amount,
            ];
        }

        return $rows;
    }
}
