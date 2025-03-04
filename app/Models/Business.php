<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Business extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'plan_subscribe_id',
        'business_category_id',
        'companyName',
        'address',
        'phoneNumber',
        'pictureUrl',
        'will_expire',
        'subscriptionDate',
        'remainingShopBalance',
        'shopOpeningBalance'
    ];

    public function invoice_data(): HasOne
    {
        return $this->hasOne(BusinessInvoiceData::class);
    }

    public function enrolled_plan()
    {
        return $this->belongsTo(PlanSubscribe::class, 'plan_subscribe_id');
    }


    public function category()
    {
        return $this->belongsTo(BusinessCategory::class, 'business_category_id');
    }

}
