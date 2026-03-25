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

        $averageMonthlySales = $this->roundUnits((float) ($monthlyUnits->avg() ?? 0));
        $conservativeMonthlySales = $this->roundUnits((float) ($monthlyUnits->min() ?? 0));
        $targetPeriodDays = $months * 30;
        $dailyConsumptionUnits = $averageMonthlySales > 0
            ? $this->roundUnits($averageMonthlySales / 30)
            : 0.0;
        $targetConsumptionUnits = $this->roundUnits($averageMonthlySales * $months);

        $activeBatches = ProductBatch::query()
            ->where('business_id', $businessId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->get()
            ->sortBy(function (ProductBatch $batch) {
                $hasNoExpiry = $batch->expiry_date === null ? 1 : 0;
                $expiryTimestamp = $batch->expiry_date?->getTimestamp() ?? PHP_INT_MAX;

                return [$hasNoExpiry, $expiryTimestamp, $batch->id];
            })
            ->values();

        $totalStock = (float) $activeBatches->sum('remaining_quantity');

        $monthsToStockOut = null;
        if ($averageMonthlySales > 0) {
            $monthsToStockOut = $this->roundUnits($totalStock / $averageMonthlySales);
        }

        $batchSimulation = $this->buildBatchFefoSimulation($activeBatches, $dailyConsumptionUnits);

        $totalUsefulUnits = $this->roundUnits((float) $batchSimulation->sum('consumable_units'));
        $potentiallyUnsoldUnits = $this->roundUnits((float) $batchSimulation->sum('potentially_unsold_units'));
        $totalExcessDisplay = (int) round((float) $batchSimulation->sum(function (array $item) {
            return max(0, (float) $item['excess_display_value']);
        }));
        $suggestedOrderUnits = $this->resolveSuggestedOrderUnits($targetConsumptionUnits, $totalUsefulUnits);
        $riskPercentage = $totalStock > 0
            ? $this->roundUnits(($potentiallyUnsoldUnits / $totalStock) * 100)
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
                    'units' => $this->roundUnits((float) $units),
                ];
            })->values(),
            'average_monthly_sales' => $averageMonthlySales,
            'conservative_monthly_sales' => $conservativeMonthlySales,
            'daily_consumption_units' => $dailyConsumptionUnits,
            'target_period_days' => $targetPeriodDays,
            'target_consumption_units' => $targetConsumptionUnits,
            'total_stock_units' => $this->roundUnits($totalStock),
            'total_useful_units' => $totalUsefulUnits,
            'total_excess_units' => $potentiallyUnsoldUnits,
            'total_excess_display' => -$totalExcessDisplay,
            'suggested_order_units' => $suggestedOrderUnits,
            'projected_stockout_months' => $monthsToStockOut,
            'expiry_risk_percentage' => $riskPercentage,
            'expiry_risk_level' => $riskLevel,
            'potentially_unsold_units' => $potentiallyUnsoldUnits,
            'batch_risk_simulation' => $batchSimulation,
            'decision_summary' => $this->buildDecisionSummary(
                $riskLevel,
                $monthsToStockOut,
                $averageMonthlySales,
                $totalStock,
                $totalUsefulUnits,
                $suggestedOrderUnits,
                $targetPeriodDays
            ),
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

    private function buildBatchFefoSimulation(Collection $batches, float $dailyConsumptionUnits): Collection
    {
        $cumulativeConsumableUnits = 0.0;

        return $batches->map(function (ProductBatch $batch) use ($dailyConsumptionUnits, &$cumulativeConsumableUnits) {
            $daysUntilExpiry = $batch->getDaysUntilExpiry();
            $stockUnits = (float) $batch->remaining_quantity;
            $cumulativeConsumableBefore = $this->roundUnits($cumulativeConsumableUnits);
            $daysConsumedBefore = $dailyConsumptionUnits > 0
                ? $this->roundUnits($cumulativeConsumableBefore / $dailyConsumptionUnits)
                : 0.0;

            $effectiveDaysRemaining = 0.0;
            if ($daysUntilExpiry !== null) {
                $effectiveDaysRemaining = $this->roundUnits(max(0, $daysUntilExpiry - $daysConsumedBefore));
            }

            $projectedConsumableUnits = $dailyConsumptionUnits > 0
                ? $this->roundUnits($effectiveDaysRemaining * $dailyConsumptionUnits)
                : 0.0;

            $consumableUnits = $this->roundUnits(min($stockUnits, $projectedConsumableUnits));
            $potentiallyUnsoldUnits = $this->roundUnits(max(0, $stockUnits - $consumableUnits));
            $excessDisplayValue = $potentiallyUnsoldUnits > 0
                ? (float) round($potentiallyUnsoldUnits, 0)
                : 0.0;

            $cumulativeConsumableUnits = $this->roundUnits($cumulativeConsumableUnits + $consumableUnits);

            return [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'expiry_date' => optional($batch->expiry_date)->format('Y-m-d'),
                'days_until_expiry' => $daysUntilExpiry,
                'stock_units' => $this->roundUnits($stockUnits),
                'remaining_units' => $this->roundUnits($stockUnits),
                'cumulative_consumable_before' => $cumulativeConsumableBefore,
                'days_consumed_before' => $daysConsumedBefore,
                'effective_days_remaining' => $effectiveDaysRemaining,
                'projected_consumable_units' => $projectedConsumableUnits,
                'consumable_units' => $consumableUnits,
                'projected_sellable_units' => $consumableUnits,
                'potentially_unsold_units' => $potentiallyUnsoldUnits,
                'excess_units' => $potentiallyUnsoldUnits,
                'excess_display' => $potentiallyUnsoldUnits > 0 ? '-' . (string) ((int) $excessDisplayValue) : '--',
                'excess_display_value' => $excessDisplayValue,
                'risk_level' => $this->resolveBatchRiskLevel($daysUntilExpiry, $potentiallyUnsoldUnits),
            ];
        })->values();
    }

    private function resolveSuggestedOrderUnits(float $targetConsumptionUnits, float $totalUsefulUnits): int
    {
        if ($targetConsumptionUnits <= $totalUsefulUnits) {
            return 0;
        }

        return (int) round($targetConsumptionUnits - $totalUsefulUnits, 0);
    }

    private function buildDecisionSummary(
        string $riskLevel,
        ?float $monthsToStockOut,
        float $averageMonthlySales,
        float $totalStock,
        float $totalUsefulUnits,
        int $suggestedOrderUnits,
        int $targetPeriodDays
    ): string
    {
        if ($totalStock <= 0) {
            return 'Sin stock activo para analizar.';
        }

        if ($averageMonthlySales <= 0) {
            return 'Riesgo alto: sin rotacion reciente, considere promociones o ajustes de compra para evitar vencimientos.';
        }

        if ($suggestedOrderUnits > 0) {
            return sprintf(
                'La cobertura util proyectada es de %s unidades para %d dias. Se sugiere pedir %d unidades adicionales.',
                number_format($totalUsefulUnits, 2, '.', ''),
                $targetPeriodDays,
                $suggestedOrderUnits
            );
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

    private function roundUnits(float $value): float
    {
        return round($value, 2);
    }
}
