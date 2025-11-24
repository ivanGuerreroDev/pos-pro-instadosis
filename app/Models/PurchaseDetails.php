<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseDetails extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'purchase_id',
        'product_id',
        'productDealerPrice',
        'productPurchasePrice',
        'productSalePrice',
        'productWholeSalePrice',
        'quantities',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function product() : BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productBatches() : HasMany
    {
        return $this->hasMany(ProductBatch::class, 'purchase_id', 'purchase_id')
            ->whereColumn('product_batches.product_id', 'purchase_details.product_id');
    }
}
