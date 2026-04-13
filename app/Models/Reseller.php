<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    protected $fillable = [
        'name',
        'email',
        'credits',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ResellerApiKey::class);
    }

    public function creditLogs(): HasMany
    {
        return $this->hasMany(ResellerCreditLog::class);
    }
}

