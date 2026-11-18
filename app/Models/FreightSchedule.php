<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Viaje fijo semanal (ej. miércoles y viernes a San Pedro Sula). */
class FreightSchedule extends Model
{
    public const WEEKDAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected $fillable = ['weekday', 'driver_id', 'destination', 'vehicle', 'departure_time', 'notes', 'active'];

    protected $casts = ['weekday' => 'integer', 'active' => 'boolean'];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function freights(): HasMany
    {
        return $this->hasMany(Freight::class);
    }
}
