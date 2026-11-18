<?php

namespace App\Models;

use App\Exceptions\ImmutableSaleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    // Estado del documento (§4).
    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOIDED = 'voided';
    /** Orden de pedido que se convirtió en factura: la factura la reemplaza en totales y deudas. */
    public const STATUS_CONVERTED = 'converted';

    // Condición (§4).
    public const TERMS_CASH = 'cash';
    public const TERMS_CREDIT = 'credit';

    /**
     * Campos que SÍ pueden cambiar en una venta ya emitida: la anulación y el
     * contador de impresiones. Cualquier otro cambio viola la inmutabilidad (§3.3).
     */
    private const MUTABLE_AFTER_ISSUE = [
        'status', 'void_reason', 'voided_at', 'voided_by', 'print_count', 'updated_at',
        'converted_to_sale_id',
    ];

    protected $fillable = [
        'document_series_id', 'number', 'full_number',
        'issued_at', 'issue_date',
        'customer_id', 'customer_name_snapshot', 'customer_rtn_snapshot', 'issuer_snapshot',
        'fiscal_snapshot', 'source_sale_id', 'converted_to_sale_id',
        'terms', 'due_date',
        'subtotal_exempt', 'subtotal_exonerated', 'subtotal_g15', 'subtotal_g18',
        'tax_15', 'tax_18', 'total',
        'status', 'void_reason', 'voided_at', 'voided_by',
        'print_count', 'user_id', 'notes',
    ];

    protected $casts = [
        'number' => 'integer',
        'issued_at' => 'datetime',
        'issue_date' => 'date',
        'due_date' => 'date',
        'issuer_snapshot' => 'array',
        'fiscal_snapshot' => 'array',
        'subtotal_exempt' => 'decimal:2',
        'subtotal_exonerated' => 'decimal:2',
        'subtotal_g15' => 'decimal:2',
        'subtotal_g18' => 'decimal:2',
        'tax_15' => 'decimal:2',
        'tax_18' => 'decimal:2',
        'total' => 'decimal:2',
        'status' => 'string',
        'voided_at' => 'datetime',
        'print_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // Inmutabilidad: una venta emitida no cambia salvo la anulación (§3.3).
        static::updating(function (Sale $sale) {
            $originalStatus = $sale->getOriginal('status');

            if (in_array($originalStatus, [self::STATUS_VOIDED, self::STATUS_CONVERTED], true)) {
                // Única excepción: contar reimpresiones de una orden ya facturada.
                if ($originalStatus === self::STATUS_CONVERTED && array_diff(array_keys($sale->getDirty()), ['print_count', 'updated_at']) === []) {
                    return;
                }
                throw new ImmutableSaleException(
                    'Una venta anulada o facturada es inmutable y no puede modificarse.'
                );
            }

            if ($originalStatus === self::STATUS_ISSUED) {
                $illegal = array_diff(array_keys($sale->getDirty()), self::MUTABLE_AFTER_ISSUE);
                if ($illegal !== []) {
                    throw new ImmutableSaleException(
                        'Una venta emitida es inmutable. Para corregir, anúlela y emita una nueva. '
                        .'Campos no permitidos: '.implode(', ', $illegal).'.'
                    );
                }

                // Cambios de estado permitidos: issued -> voided | converted.
                if ($sale->isDirty('status') && ! in_array($sale->status, [self::STATUS_VOIDED, self::STATUS_CONVERTED], true)) {
                    throw new ImmutableSaleException(
                        'El estado de una venta emitida solo puede cambiar a anulada o facturada.'
                    );
                }
                if ($sale->isDirty('converted_to_sale_id') && $sale->status !== self::STATUS_CONVERTED) {
                    throw new ImmutableSaleException('Solo una orden facturada se liga a su factura.');
                }
            }
        });

        // Nunca se elimina un documento, solo se anula (§3.1).
        static::deleting(function (Sale $sale) {
            throw new ImmutableSaleException(
                'Las ventas no se eliminan; use la anulación.'
            );
        });
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'document_series_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function sourceSale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_sale_id');
    }

    public function convertedTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'converted_to_sale_id');
    }

    /** ¿Es factura fiscal? Se deduce del snapshot fiscal (no requiere cargar la serie). */
    public function isInvoice(): bool
    {
        return ! empty($this->fiscal_snapshot['cai']);
    }

    /** Plantilla Blade: la orden y la factura son documentos distintos (regla 5). */
    public function documentView(): string
    {
        return $this->isInvoice() ? 'documents.invoice' : 'documents.order';
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /** Solo ventas activas (emitidas). Las anuladas quedan fuera de totales/métricas (§3.8). */
    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VOIDED);
    }
}
