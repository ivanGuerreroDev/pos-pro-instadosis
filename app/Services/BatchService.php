<?php

namespace App\Services;

use App\Models\ProductBatch;
use App\Models\BatchTransaction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class BatchService
{
    /**
     * Create a new batch for a product.
     */
    public function createBatch(array $data): ProductBatch
    {
        DB::beginTransaction();
        
        try {
            // Generate batch number if not provided
            if (empty($data['batch_number'])) {
                $data['batch_number'] = $this->generateBatchNumber($data['product_id']);
            }

            // Set remaining quantity equal to quantity initially
            $data['remaining_quantity'] = $data['quantity'];

            // Format dates to avoid timezone issues
            if (!empty($data['manufacture_date'])) {
                $data['manufacture_date'] = \Carbon\Carbon::parse($data['manufacture_date'])->format('Y-m-d');
            }
            if (!empty($data['expiry_date'])) {
                $data['expiry_date'] = \Carbon\Carbon::parse($data['expiry_date'])->format('Y-m-d');
            }

            // Create batch
            $batch = ProductBatch::create($data);

            // Record transaction
            BatchTransaction::record(
                $batch->id,
                'purchase',
                $data['quantity'],
                $data['reference_type'] ?? null,
                $data['reference_id'] ?? null,
                'Initial batch creation'
            );

            DB::commit();

            return $batch;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Discard a batch.
     */
    public function discardBatch(ProductBatch $batch, string $reason): bool
    {
        DB::beginTransaction();

        try {
            // Update batch status
            $batch->update(['status' => 'discarded']);

            // Record transaction
            BatchTransaction::record(
                $batch->id,
                'discard',
                $batch->remaining_quantity,
                null,
                null,
                $reason
            );

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Adjust batch quantity.
     */
    public function adjustBatch(ProductBatch $batch, int $newQuantity, string $reason): bool
    {
        DB::beginTransaction();

        try {
            $oldQuantity = $batch->remaining_quantity;
            $difference = $newQuantity - $oldQuantity;

            // Update batch quantity
            $batch->update(['remaining_quantity' => $newQuantity]);

            // Record transaction
            BatchTransaction::record(
                $batch->id,
                'adjustment',
                $difference,
                null,
                null,
                $reason
            );

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Decrease batch quantity (for sales).
     */
    public function decreaseBatchQuantity(
        ProductBatch $batch,
        int $quantity,
        string $referenceType,
        int $referenceId
    ): bool {
        DB::beginTransaction();

        try {
            if (!$batch->decreaseQuantity($quantity)) {
                throw new \Exception('Insufficient stock in batch');
            }

            // Record transaction
            BatchTransaction::record(
                $batch->id,
                'sale',
                -$quantity,
                $referenceType,
                $referenceId,
                'Sale transaction'
            );

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Increase batch quantity (for returns).
     */
    public function increaseBatchQuantity(
        ProductBatch $batch,
        int $quantity,
        string $referenceType,
        int $referenceId
    ): bool {
        DB::beginTransaction();

        try {
            $batch->increaseQuantity($quantity);

            // Record transaction
            BatchTransaction::record(
                $batch->id,
                'return',
                $quantity,
                $referenceType,
                $referenceId,
                'Return transaction'
            );

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate a unique batch number for a product.
     */
    private function generateBatchNumber(int $productId): string
    {
        $product = Product::findOrFail($productId);
        $count = ProductBatch::where('product_id', $productId)->count() + 1;
        
        $prefix = strtoupper(substr($product->productName, 0, 3));
        $date = now()->format('Ymd');
        
        return sprintf('%s-%s-%04d', $prefix, $date, $count);
    }

    /**
     * Get batches summary for a product.
     */
    public function getProductBatchesSummary(int $productId): array
    {
        $batches = ProductBatch::where('product_id', $productId)
            ->with(['transactions'])
            ->get();

        return [
            'total_batches' => $batches->count(),
            'active_batches' => $batches->where('status', 'active')->count(),
            'expired_batches' => $batches->where('status', 'expired')->count(),
            'discarded_batches' => $batches->where('status', 'discarded')->count(),
            'total_stock' => $batches->where('status', 'active')->sum('remaining_quantity'),
            'near_expiry_count' => $batches->filter(fn($b) => $b->isNearExpiry(30))->count(),
        ];
    }
}
