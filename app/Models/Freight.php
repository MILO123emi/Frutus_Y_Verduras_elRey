<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Freight extends Model
{
    public const PURPOSES = ['business', 'family', 'customer', 'moving', 'other'];
    public const STATUSES = ['scheduled', 'done', 'cancelled'];

    protected $fillable = [
        'trip_date', 'freight_schedule_id', 'driver_id', 'destination', 'purpose', 'for_whom',
        'customer_id', 'vehicle', 'departure_time', 'notes', 'charge_amount', 'charge_to_account',
        'status', 'customized', 'user_id',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'charge_amount' => 'decimal:2',
        'charge_to_account' => 'boolean',
        'customized' => 'boolean',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FreightSchedule::class, 'freight_schedule_id');
    }

    /** ¿Suma a la deuda del cliente? */
    public function chargesAccount(): bool
    {
        return $this->charge_to_account && $this->customer_id && $this->status !== 'cancelled'
            && (float) $this->charge_amount > 0;
    }
}
