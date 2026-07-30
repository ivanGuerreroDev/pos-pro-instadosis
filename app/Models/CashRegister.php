<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashRegister extends Model
{
    use HasFactory;

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'business_id',
        'user_id',
        'closed_by_user_id',
        'status',
        'opening_balance',
        'opening_notes',
        'opened_at',
        'closed_at',
        'total_sales',
        'total_income',
        'total_expense',
        'closing_expected_balance',
        'closing_counted_balance',
        'closing_difference',
        'closing_notes',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'opening_balance' => 'double',
        'total_sales' => 'double',
        'total_income' => 'double',
        'total_expense' => 'double',
        'closing_expected_balance' => 'double',
        'closing_counted_balance' => 'double',
        'closing_difference' => 'double',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function business() : BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedByUser() : BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
