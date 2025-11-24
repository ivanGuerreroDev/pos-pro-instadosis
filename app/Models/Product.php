<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'productName',
        'business_id',
        'unit_id',
        'brand_id',
        'category_id',
        'productCode',
        'productPicture',
        'productDealerPrice',
        'productPurchasePrice',
        'productSalePrice',
        'productWholeSalePrice',
        'productStock',
        'size',
        'meta',
        'color',
        'weight',
        'capacity',
        'productManufacturer',
        'track_by_batches',
        'is_medicine',
        'tax_rate',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['computed_stock'];

    public function unit() : BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand() : BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category() : BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the batches for the product.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * Get the active batches for the product.
     */
    public function activeBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class)->where('status', 'active');
    }

    /**
     * Scope a query to only include products tracked by batches.
     */
    public function scopeTrackedByBatches($query)
    {
        return $query->where('track_by_batches', true);
    }

    /**
     * Scope a query to only include medicine products.
     */
    public function scopeMedicines($query)
    {
        return $query->where('is_medicine', true);
    }

    /**
     * Get computed stock from active batches if tracked by batches.
     */
    public function getComputedStockAttribute()
    {
        if ($this->track_by_batches) {
            return $this->activeBatches()
                ->withStock()
                ->sum('remaining_quantity');
        }
        
        return $this->productStock;
    }

    /**
     * Calculate tax amount for a given price.
     */
    public function calculateTaxAmount($price): float
    {
        $taxRate = floatval($this->tax_rate ?? 0);
        return round($price * ($taxRate / 100), 2);
    }

    /**
     * Get price with tax.
     */
    public function getPriceWithTax($price): float
    {
        return $price + $this->calculateTaxAmount($price);
    }

    /**
     * Get available stock considering batches.
     */
    public function getAvailableStock(): int
    {
        if ($this->track_by_batches) {
            return $this->activeBatches()
                ->withStock()
                ->sum('remaining_quantity');
        }
        
        return $this->productStock;
    }

    /**
     * Check if product has sufficient stock.
     */
    public function hasSufficientStock(int $quantity): bool
    {
        return $this->getAvailableStock() >= $quantity;
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'meta' => 'json',
        'track_by_batches' => 'boolean',
        'is_medicine' => 'boolean',
    ];
}
