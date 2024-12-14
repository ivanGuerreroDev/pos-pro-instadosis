<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Income extends Model
{
    use HasFactory;

    protected $fillable = [
        'income_category_id',
        'business_id',
        'user_id',
        'amount',
        'incomeFor',
        'paymentType',
        'referenceNo',
        'note',
        'incomeDate',
    ];

    public function category() : BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'income_category_id');
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'double',
        'user_id' => 'integer',
        'business_id' => 'integer',
        'income_category_id' => 'integer',
    ];
}
