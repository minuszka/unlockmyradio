<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'serial',
        'email',
        'stripe_payment_id',
        'stripe_session_id',
        'amount',
        'currency',
        'status',
        'code_revealed',
        'brand',
        'car_make',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}

