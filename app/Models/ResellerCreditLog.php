<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerCreditLog extends Model
{
    protected $fillable = [
        'reseller_id',
        'delta',
        'balance_after',
        'reason',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }
}

