<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class ProductBatch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id',
        'business_id',
        'batch_number',
        'quantity',
        'remaining_quantity',
        'purchase_price',
        'manufacture_date',
        'expiry_date',
        'purchase_id',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'manufacture_date' => 'date:Y-m-d',
        'expiry_date' => 'date:Y-m-d',
        'purchase_price' => 'double',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'available_quantity',
        'sold_quantity',
        'days_until_expiry',
        'is_expired',
        'is_near_expiry',
        'is_active',
        'expiry_warning',
        'status_display',
    ];

    /**
     * Get the available quantity.
     */
    public function getAvailableQuantityAttribute()
    {
        return $this->remaining_quantity;
    }

    /**
     * Get the sold quantity.
     */
    public function getSoldQuantityAttribute()
    {
        return $this->quantity - $this->remaining_quantity;
    }

    /**
     * Get days until expiry attribute.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->getDaysUntilExpiry();
    }

    /**
     * Get is expired attribute.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    /**
     * Get is near expiry attribute.
     */
    public function getIsNearExpiryAttribute(): bool
    {
        return $this->isNearExpiry();
    }

    /**
     * Get is active attribute.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    /**
     * Get expiry warning message.
     */
    public function getExpiryWarningAttribute(): string
    {
        if ($this->isExpired()) {
            return 'Lote vencido';
        }
        
        $days = $this->getDaysUntilExpiry();
        if ($days !== null && $days <= 30 && $days > 0) {
            return "Vence en $days días";
        }
        
        return '';
    }

    /**
     * Get status display.
     */
    public function getStatusDisplayAttribute(): string
    {
        if ($this->isExpired()) {
            return 'Vencido';
        }
        
        if ($this->isNearExpiry()) {
            return 'Próximo a vencer';
        }
        
        if ($this->remaining_quantity <= 0) {
            return 'Agotado';
        }
        
        return 'Activo';
    }

    /**
     * Get the product that owns the batch.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the business that owns the batch.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the purchase that created the batch.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the transactions for the batch.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BatchTransaction::class, 'batch_id');
    }

    /**
     * Get the notifications for the batch.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(ExpiredBatchNotification::class, 'batch_id');
    }

    /**
     * Get the sale details for the batch.
     */
    public function saleDetails(): HasMany
    {
        return $this->hasMany(BatchSaleDetail::class, 'batch_id');
    }

    /**
     * Scope a query to only include active batches.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include expired batches.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope a query to only include batches with available stock.
     */
    public function scopeWithStock($query)
    {
        return $query->where('remaining_quantity', '>', 0);
    }

    /**
     * Scope a query to order batches by expiry date (FEFO).
     */
    public function scopeOrderByExpiry($query)
    {
        return $query->orderBy('expiry_date', 'asc');
    }

    /**
     * Scope a query to only include near expiry batches.
     */
    public function scopeNearExpiry($query, $days = 30)
    {
        $futureDate = Carbon::now()->addDays($days);
        return $query->where('expiry_date', '<=', $futureDate)
                     ->where('expiry_date', '>', Carbon::now());
    }

    /**
     * Check if the batch is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return Carbon::now()->greaterThan($this->expiry_date);
    }

    /**
     * Get days until expiry.
     */
    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }
        return Carbon::now()->diffInDays($this->expiry_date, false);
    }

    /**
     * Check if the batch is near expiry.
     */
    public function isNearExpiry($days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        $daysUntilExpiry = $this->getDaysUntilExpiry();
        return $daysUntilExpiry !== null && $daysUntilExpiry > 0 && $daysUntilExpiry <= $days;
    }

    /**
     * Update batch status based on expiry date.
     */
    public function updateExpiryStatus(): void
    {
        if ($this->isExpired() && $this->status === 'active') {
            $this->update(['status' => 'expired']);
        }
    }

    /**
     * Decrease remaining quantity.
     */
    public function decreaseQuantity(int $quantity): bool
    {
        if ($this->remaining_quantity < $quantity) {
            return false;
        }

        $this->remaining_quantity -= $quantity;
        $saved = $this->save();

        // Create notification if batch is now out of stock
        if ($saved && $this->remaining_quantity <= 0) {
            ExpiredBatchNotification::createOrUpdate(
                $this->id,
                $this->business_id,
                'out_of_stock',
                0
            );
        }

        return $saved;
    }

    /**
     * Increase remaining quantity.
     */
    public function increaseQuantity(int $quantity): bool
    {
        $this->remaining_quantity += $quantity;
        return $this->save();
    }
}
