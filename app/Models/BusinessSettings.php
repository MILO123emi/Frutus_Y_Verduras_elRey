<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSettings extends Model
{
    protected $fillable = [
        'trade_name', 'legal_name', 'activity', 'rtn', 'address', 'phone', 'print_settings',
    ];

    protected $casts = [
        'print_settings' => 'array',
    ];

    /** Fila única de configuración del emisor. */
    public static function current(): ?self
    {
        return static::query()->first();
    }
}
