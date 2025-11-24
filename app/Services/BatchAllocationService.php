<?php

namespace App\Services;

use App\Models\ProductBatch;
use App\Models\Product;
use Illuminate\Support\Collection;

class BatchAllocationService
{
    /**
     * Allocate batches for a sale using FEFO strategy.
     * Returns array of batches with allocated quantities.
     */
    public function allocateBatchesForSale(
        int $productId,
        int $requestedQuantity,
        ?array $manualBatchSelection = null
    ): array {
        $product = Product::findOrFail($productId);

        // If product is not tracked by batches, return empty
        if (!$product->track_by_batches) {
            return [];
        }

        // If manual selection provided, use it
        if ($manualBatchSelection) {
            return $this->allocateManualSelection($manualBatchSelection, $requestedQuantity);
        }

        // Use FEFO strategy (First Expired, First Out)
        return $this->allocateFEFO($productId, $requestedQuantity);
    }

    /**
     * Allocate batches using FEFO (First Expired, First Out) strategy.
     */
    private function allocateFEFO(int $productId, int $requestedQuantity): array
    {
        $batches = ProductBatch::where('product_id', $productId)
            ->active()
            ->withStock()
            ->orderByExpiry()
            ->get();

        return $this->allocateFromBatches($batches, $requestedQuantity);
    }

    /**
     * Allocate batches from manual selection.
     */
    private function allocateManualSelection(array $selection, int $requestedQuantity): array
    {
        $batchIds = array_column($selection, 'batch_id');
        $batches = ProductBatch::whereIn('id', $batchIds)
            ->active()
            ->withStock()
            ->get();

        // Map quantities from selection
        $allocation = [];
        $remaining = $requestedQuantity;

        foreach ($selection as $item) {
            $batch = $batches->firstWhere('id', $item['batch_id']);
            
            if (!$batch) {
                continue;
            }

            $quantityToAllocate = min(
                $item['quantity'],
                $batch->remaining_quantity,
                $remaining
            );

            if ($quantityToAllocate > 0) {
                $allocation[] = [
                    'batch_id' => $batch->id,
                    'batch' => $batch,
                    'quantity' => $quantityToAllocate,
                ];

                $remaining -= $quantityToAllocate;
            }

            if ($remaining <= 0) {
                break;
            }
        }

        if ($remaining > 0) {
            throw new \Exception('Insufficient stock in selected batches');
        }

        return $allocation;
    }

    /**
     * Allocate from a collection of batches.
     */
    private function allocateFromBatches(Collection $batches, int $requestedQuantity): array
    {
        $allocation = [];
        $remaining = $requestedQuantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            // Skip expired batches
            if ($batch->isExpired()) {
                continue;
            }

            $quantityToAllocate = min($batch->remaining_quantity, $remaining);

            if ($quantityToAllocate > 0) {
                $allocation[] = [
                    'batch_id' => $batch->id,
                    'batch' => $batch,
                    'quantity' => $quantityToAllocate,
                ];

                $remaining -= $quantityToAllocate;
            }
        }

        if ($remaining > 0) {
            throw new \Exception('Insufficient stock in active batches');
        }

        return $allocation;
    }

    /**
     * Check if sufficient stock is available for a product.
     */
    public function checkStockAvailability(int $productId, int $requestedQuantity): bool
    {
        $product = Product::findOrFail($productId);

        if (!$product->track_by_batches) {
            return $product->productStock >= $requestedQuantity;
        }

        $availableStock = ProductBatch::where('product_id', $productId)
            ->active()
            ->withStock()
            ->sum('remaining_quantity');

        return $availableStock >= $requestedQuantity;
    }

    /**
     * Get available batches for a product with details.
     */
    public function getAvailableBatchesForProduct(int $productId): Collection
    {
        return ProductBatch::where('product_id', $productId)
            ->active()
            ->withStock()
            ->orderByExpiry()
            ->with('product')
            ->get()
            ->map(function ($batch) {
                return [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                    'days_until_expiry' => $batch->getDaysUntilExpiry(),
                    'is_near_expiry' => $batch->isNearExpiry(30),
                    'purchase_price' => $batch->purchase_price,
                ];
            });
    }

    /**
     * Validate batch allocation before processing.
     */
    public function validateAllocation(array $allocation): bool
    {
        foreach ($allocation as $item) {
            $batch = ProductBatch::find($item['batch_id']);

            if (!$batch) {
                throw new \Exception("Batch {$item['batch_id']} not found");
            }

            if ($batch->status !== 'active') {
                throw new \Exception("Batch {$batch->batch_number} is not active");
            }

            if ($batch->isExpired()) {
                throw new \Exception("Batch {$batch->batch_number} is expired");
            }

            if ($batch->remaining_quantity < $item['quantity']) {
                throw new \Exception("Insufficient stock in batch {$batch->batch_number}");
            }
        }

        return true;
    }
}
