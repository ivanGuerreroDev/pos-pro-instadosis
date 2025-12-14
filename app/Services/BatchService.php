<?php

namespace App\Services;

use App\Models\ProductBatch;
use App\Models\BatchTransaction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchService
{
    /**
     * Add to existing batch or create new one.
     * If batch_number exists for the product, adds quantity to it.
     * Otherwise creates a new batch.
     */
    public function addOrCreateBatch(array $data): ProductBatch
    {
        // Check if we're already in a transaction
        $inTransaction = DB::transactionLevel() > 0;
        
        if (!$inTransaction) {
            DB::beginTransaction();
        }
        
        try {
            // Format dates to avoid timezone issues
            if (!empty($data['manufacture_date'])) {
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $data['manufacture_date'], $matches)) {
                    $data['manufacture_date'] = $matches[1];
                }
            }
            if (!empty($data['expiry_date'])) {
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $data['expiry_date'], $matches)) {
                    $data['expiry_date'] = $matches[1];
                }
            }

            // Generate batch number if not provided
            if (empty($data['batch_number'])) {
                $data['batch_number'] = $this->generateBatchNumber($data['product_id']);
                Log::info('Generated batch number', [
                    'product_id' => $data['product_id'],
                    'batch_number' => $data['batch_number']
                ]);
            }

            // Try to find existing batch by batch_number and product_id (regardless of status)
            // Note: The unique constraint is on (product_id, batch_number), not including business_id
            $existingBatch = ProductBatch::where('product_id', $data['product_id'])
                ->where('batch_number', $data['batch_number'])
                ->first();

            if ($existingBatch) {
                Log::info('Found existing batch, updating', [
                    'batch_id' => $existingBatch->id,
                    'batch_number' => $existingBatch->batch_number,
                    'current_quantity' => $existingBatch->quantity,
                    'adding_quantity' => $data['quantity']
                ]);
                
                // Add to existing batch
                $existingBatch->quantity += $data['quantity'];
                $existingBatch->remaining_quantity += $data['quantity'];
                
                // Update dates if provided
                if (!empty($data['manufacture_date'])) {
                    $existingBatch->manufacture_date = $data['manufacture_date'];
                }
                if (!empty($data['expiry_date'])) {
                    $existingBatch->expiry_date = $data['expiry_date'];
                }
                
                // Update purchase price if provided
                if (isset($data['purchase_price'])) {
                    $existingBatch->purchase_price = $data['purchase_price'];
                }
                
                // Reactivate batch if it was expired or discarded
                if ($existingBatch->status !== 'active') {
                    $existingBatch->status = 'active';
                }
                
                $existingBatch->save();

                // Record transaction
                BatchTransaction::record(
                    $existingBatch->id,
                    'purchase',
                    $data['quantity'],
                    $data['reference_type'] ?? null,
                    $data['reference_id'] ?? null,
                    'Added to existing batch'
                );

                if (!$inTransaction) {
                    DB::commit();
                }
                
                return $existingBatch;
            } else {
                Log::info('No existing batch found, creating new one', [
                    'product_id' => $data['product_id'],
                    'batch_number' => $data['batch_number'],
                    'quantity' => $data['quantity']
                ]);
                
                // Create new batch
                $batch = $this->createBatchInternal($data, $inTransaction);
                
                if (!$inTransaction) {
                    DB::commit();
                }
                
                return $batch;
            }
        } catch (\Exception $e) {
            if (!$inTransaction) {
                DB::rollBack();
            }
            
            Log::error('Error in addOrCreateBatch', [
                'product_id' => $data['product_id'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Create a new batch for a product (public method with transaction management).
     */
    public function createBatch(array $data): ProductBatch
    {
        $inTransaction = DB::transactionLevel() > 0;
        
        if (!$inTransaction) {
            DB::beginTransaction();
        }
        
        try {
            $batch = $this->createBatchInternal($data, $inTransaction);
            
            if (!$inTransaction) {
                DB::commit();
            }

            return $batch;
        } catch (\Exception $e) {
            if (!$inTransaction) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    /**
     * Internal method to create a new batch (does not manage transactions).
     */
    private function createBatchInternal(array $data, bool $inTransaction = false): ProductBatch
    {
        try {
            // Generate batch number if not provided
            if (empty($data['batch_number'])) {
                $data['batch_number'] = $this->generateBatchNumber($data['product_id']);
            }

            // Set remaining quantity equal to quantity initially
            $data['remaining_quantity'] = $data['quantity'];

            // Format dates to avoid timezone issues - extract only the date part
            if (!empty($data['manufacture_date'])) {
                // Extract only date part (YYYY-MM-DD) if it's a valid date string
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $data['manufacture_date'], $matches)) {
                    $data['manufacture_date'] = $matches[1];
                }
            }
            if (!empty($data['expiry_date'])) {
                // Extract only date part (YYYY-MM-DD) if it's a valid date string
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $data['expiry_date'], $matches)) {
                    $data['expiry_date'] = $matches[1];
                }
            }

            Log::info('Creating new batch', [
                'product_id' => $data['product_id'],
                'batch_number' => $data['batch_number'],
                'quantity' => $data['quantity'],
                'business_id' => $data['business_id'] ?? null
            ]);

            // Create batch
            $batch = ProductBatch::create($data);

            Log::info('Batch created successfully', [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number
            ]);

            // Record transaction
            BatchTransaction::record(
                $batch->id,
                'purchase',
                $data['quantity'],
                $data['reference_type'] ?? null,
                $data['reference_id'] ?? null,
                'Initial batch creation'
            );

            return $batch;
        } catch (\Exception $e) {
            Log::error('Error creating batch', [
                'product_id' => $data['product_id'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
