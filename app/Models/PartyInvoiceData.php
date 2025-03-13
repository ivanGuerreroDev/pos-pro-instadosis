<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PartyInvoiceData extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_id',
        'dtipoRuc',
        'druc',
        'ddv',
        'itipoRec',
        'dnombRec',
        'ddirecRec',
        'dcodUbi',
        'dcorreg',
        'ddistr',
        'dprov',
        'dcorElectRec',
        'didExt',
        'dpaisExt'
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}