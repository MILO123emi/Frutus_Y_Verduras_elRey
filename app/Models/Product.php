<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    // Tipo de impuesto (§3.7).
    public const TAX_EXEMPT = 'exempt';
    public const TAX_G15 = 'g15';
    public const TAX_G18 = 'g18';

    protected $fillable = [
        'name', 'default_unit_id', 'reference_price', 'tax_type', 'form_order', 'active',
    ];

    protected $casts = [
        'reference_price' => 'decimal:2',
        'form_order' => 'integer',
        'active' => 'boolean',
    ];

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_unit_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
