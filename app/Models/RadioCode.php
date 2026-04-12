<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadioCode extends Model
{
    protected $fillable = [
        'brand',
        'car_make', 
        'prefix',
        'serial',
        'code',
    ];
}

