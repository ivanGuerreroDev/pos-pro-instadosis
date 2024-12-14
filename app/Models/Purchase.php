<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use HasFactory;

    protected $appends = ['has_return'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'party_id',
        'business_id',
        'discountAmount',
        'dueAmount',
        'paidAmount',
        'totalAmount',
        'invoiceNumber',
        'isPaid',
        'paymentType',
        'purchaseDate',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseDetails::class);
    }

    public function party() : BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseReturns()
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_id');
    }

    public function getHasReturnAttribute()
    {
        return $this->purchaseReturns()->exists();
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $id = Purchase::where('business_id', auth()->user()->business_id)->count() + 1;
            $model->invoiceNumber = "P-" . str_pad($id, 5, '0', STR_PAD_LEFT);
        });
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'isPaid' => 'boolean',
        'discountAmount' => 'double',
        'dueAmount' => 'double',
        'paidAmount' => 'double',
        'totalAmount' => 'double',
    ];
}
