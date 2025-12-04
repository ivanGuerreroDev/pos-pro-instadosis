<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchSaleDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sale_detail_id',
        'batch_id',
        'quantity',
    ];

    /**
     * Get the sale detail that owns the batch sale detail.
     */
    public function saleDetail(): BelongsTo
    {
        return $this->belongsTo(SaleDetails::class, 'sale_detail_id');
    }

    /**
     * Get the batch that owns the batch sale detail.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * Scope a query to only include batches for a specific sale detail.
     */
    public function scopeForSaleDetail($query, $saleDetailId)
    {
        return $query->where('sale_detail_id', $saleDetailId);
    }

    /**
     * Scope a query to only include sales for a specific batch.
     */
    public function scopeForBatch($query, $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When a batch sale detail is created, decrement the batch stock
        static::created(function ($batchSaleDetail) {
            $batch = $batchSaleDetail->batch;
            if ($batch) {
                $batch->decreaseQuantity($batchSaleDetail->quantity);
            }
        });

        // When a batch sale detail is deleted, increment the batch stock back
        static::deleted(function ($batchSaleDetail) {
            $batch = $batchSaleDetail->batch;
            if ($batch) {
                $batch->increaseQuantity($batchSaleDetail->quantity);
            }
        });
    }
}
