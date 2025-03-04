<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessInvoiceData extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'dtipoRuc',
        'druc',
        'ddv', 
        'dnombEm',
        'dcoordEm',
        'ddirecEm',
        'dcodUbi',
        'dcorreg',
        'ddistr',
        'dprov',
        'dtfnEm',
        'dcorElectEmi'
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
} 