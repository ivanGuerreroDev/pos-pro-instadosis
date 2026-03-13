<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\SaleDetails;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductRotationAnalyticsService
{
    public function getProductAnalytics(int $businessId, int $productId, int $months = 3): array
    {
        $months = max(1, min($months, 12));

        $product = Product::query()
            ->where('business_id', $businessId)
            ->where('id', $productId)
            ->first();

        if (!$product) {
            throw new ModelNotFoundException('Producto no encontrado para este negocio.');
        }

        $monthlyUnits = $this->getMonthlyUnits($businessId, $productId, $months);

        $averageMonthlySales = round($monthlyUnits->avg(), 2);
        $conservativeMonthlySales = round($monthlyUnits->min() ?? 0, 2);

        $activeBatches = ProductBatch::query()
            ->where('business_id', $businessId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->get();

        $totalStock = (float) $activeBatches->sum('remaining_quantity');

        $monthsToStockOut = null;
        if ($conservativeMonthlySales > 0) {
            $monthsToStockOut = round($totalStock / $conservativeMonthlySales, 2);
        }

        $batchSimulation = $activeBatches->map(function (ProductBatch $batch) use ($conservativeMonthlySales) {
            $daysUntilExpiry = $batch->getDaysUntilExpiry();
            $monthsUntilExpiry = $daysUntilExpiry === null
                ? null
                : max(0, round($daysUntilExpiry / 30, 2));

            $projectedSellableUnits = 0.0;
            if ($monthsUntilExpiry !== null && $conservativeMonthlySales > 0) {
                $projectedSellableUnits = min(
                    (float) $batch->remaining_quantity,
                    round($conservativeMonthlySales * $monthsUntilExpiry, 2)
                );
            }

            $potentiallyUnsoldUnits = max(0, (float) $batch->remaining_quantity - $projectedSellableUnits);

            return [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'expiry_date' => optional($batch->expiry_date)->format('Y-m-d'),
                'days_until_expiry' => $daysUntilExpiry,
                'remaining_units' => (float) $batch->remaining_quantity,
                'projected_sellable_units' => round($projectedSellableUnits, 2),
                'potentially_unsold_units' => round($potentiallyUnsoldUnits, 2),
                'risk_level' => $this->resolveBatchRiskLevel($daysUntilExpiry, $potentiallyUnsoldUnits),
            ];
        })->values();

        $potentiallyUnsoldUnits = round((float) $batchSimulation->sum('potentially_unsold_units'), 2);
        $riskPercentage = $totalStock > 0
            ? round(($potentiallyUnsoldUnits / $totalStock) * 100, 2)
            : 0.0;

        $riskLevel = $this->resolveRiskLevel($riskPercentage);

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->productName,
                'code' => $product->productCode,
            ],
            'period_months' => $months,
            'monthly_sales_units' => $monthlyUnits->map(function ($units, $month) {
                return [
                    'month' => $month,
                    'units' => round((float) $units, 2),
                ];
            })->values(),
            'average_monthly_sales' => $averageMonthlySales,
            'conservative_monthly_sales' => $conservativeMonthlySales,
            'total_stock_units' => round($totalStock, 2),
            'projected_stockout_months' => $monthsToStockOut,
            'expiry_risk_percentage' => $riskPercentage,
            'expiry_risk_level' => $riskLevel,
            'potentially_unsold_units' => $potentiallyUnsoldUnits,
            'batch_risk_simulation' => $batchSimulation,
            'decision_summary' => $this->buildDecisionSummary($riskLevel, $monthsToStockOut, $conservativeMonthlySales, $totalStock),
        ];
    }

    public function getBusinessRiskSummary(int $businessId, int $months = 3, int $limit = 20): array
    {
        $productIds = ProductBatch::query()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->select('product_id')
            ->groupBy('product_id')
            ->limit(max(1, min($limit, 100)))
            ->pluck('product_id');

        $items = [];
        foreach ($productIds as $productId) {
            $analytics = $this->getProductAnalytics($businessId, (int) $productId, $months);
            $items[] = [
                'product_id' => $analytics['product']['id'],
                'product_name' => $analytics['product']['name'],
                'total_stock_units' => $analytics['total_stock_units'],
                'projected_stockout_months' => $analytics['projected_stockout_months'],
                'potentially_unsold_units' => $analytics['potentially_unsold_units'],
                'expiry_risk_percentage' => $analytics['expiry_risk_percentage'],
                'expiry_risk_level' => $analytics['expiry_risk_level'],
            ];
        }

        usort($items, function (array $a, array $b) {
            return $b['expiry_risk_percentage'] <=> $a['expiry_risk_percentage'];
        });

        return $items;
    }

    private function getMonthlyUnits(int $businessId, int $productId, int $months): Collection
    {
        $startMonth = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $endMonth = Carbon::now()->endOfMonth();

        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', sales.saleDate)"
            : "DATE_FORMAT(sales.saleDate, '%Y-%m')";

        $salesByMonth = SaleDetails::query()
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->where('sales.business_id', $businessId)
            ->where('sale_details.product_id', $productId)
            ->whereBetween('sales.saleDate', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->selectRaw("{$monthExpression} as month_key")
            ->selectRaw('SUM(sale_details.quantities) as total_units')
            ->groupBy('month_key')
            ->pluck('total_units', 'month_key');

        $result = collect();
        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $month = Carbon::now()->startOfMonth()->subMonths($offset)->format('Y-m');
            $result->put($month, (float) ($salesByMonth[$month] ?? 0));
        }

        return $result;
    }

    private function resolveRiskLevel(float $riskPercentage): string
    {
        if ($riskPercentage >= 30) {
            return 'high';
        }

        if ($riskPercentage >= 10) {
            return 'medium';
        }

        return 'low';
    }

    private function resolveBatchRiskLevel(?int $daysUntilExpiry, float $potentiallyUnsoldUnits): string
    {
        if ($daysUntilExpiry !== null && $daysUntilExpiry <= 0 && $potentiallyUnsoldUnits > 0) {
            return 'high';
        }

        if ($potentiallyUnsoldUnits > 0) {
            return 'medium';
        }

        return 'low';
    }

    private function buildDecisionSummary(string $riskLevel, ?float $monthsToStockOut, float $conservativeMonthlySales, float $totalStock): string
    {
        if ($totalStock <= 0) {
            return 'Sin stock activo para analizar.';
        }

        if ($conservativeMonthlySales <= 0) {
            return 'Riesgo alto: sin rotacion reciente, considere promociones o ajustes de compra para evitar vencimientos.';
        }

        if ($riskLevel === 'high') {
            return 'Riesgo alto de vencimiento: priorice salida por promociones, bundles o descuentos selectivos.';
        }

        if ($monthsToStockOut !== null && $monthsToStockOut < 1) {
            return 'Stock de salida rapida: planifique reabastecimiento para evitar quiebres.';
        }

        if ($riskLevel === 'medium') {
            return 'Riesgo moderado: monitoree semanalmente y refuerce ventas de lotes proximos a vencer.';
        }

        return 'Escenario saludable: mantener estrategia actual y monitoreo periodico.';
    }
}
