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
}
