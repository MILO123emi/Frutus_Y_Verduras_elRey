<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    // Condición de pago (§4).
    public const TERMS_CASH = 'cash';
    public const TERMS_CREDIT = 'credit';

    protected $fillable = [
        'name', 'rtn', 'phone', 'address',
        'default_terms', 'credit_limit', 'credit_days', 'active', 'notes',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_days' => 'integer',
        'active' => 'boolean',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AccountMovement::class);
    }

    public function freights(): HasMany
    {
        return $this->hasMany(Freight::class);
    }
}
