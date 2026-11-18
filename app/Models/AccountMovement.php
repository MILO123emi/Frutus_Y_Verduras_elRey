<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Abono o saldo anterior de un cliente. Nunca se borra: se anula. */
class AccountMovement extends Model
{
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_OPENING = 'opening_balance';

    public const METHODS = ['cash', 'transfer', 'check', 'other'];

    protected $fillable = [
        'customer_id', 'type', 'movement_date', 'amount', 'method', 'reference', 'notes',
        'status', 'void_reason', 'voided_at', 'voided_by', 'user_id',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Los movimientos no se borran; se anulan.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }
}
