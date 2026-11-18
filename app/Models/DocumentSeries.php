<?php

namespace App\Models;

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSeries extends Model
{
    protected $table = 'document_series';

    // Tipos de serie (§6).
    public const KIND_ORDER = 'order';
    public const KIND_INVOICE = 'invoice';

    // Origen del número (§6).
    public const ORIGIN_SYSTEM = 'system';
    public const ORIGIN_PAPER_BOOK = 'paper_book';

    protected $fillable = [
        'code', 'title', 'kind', 'origin',
        'next_number', 'number_from', 'number_to', 'pad_length',
        'cai', 'establishment', 'emission_point', 'doc_type_code', 'issue_deadline',
        'range_alert_threshold', 'deadline_alert_days',
        'status', 'notes',
    ];

    protected $attributes = [
        'range_alert_threshold' => 50,
        'deadline_alert_days' => 15,
        'status' => 'active',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'number_from' => 'integer',
        'number_to' => 'integer',
        'pad_length' => 'integer',
        'issue_deadline' => 'date',
        'range_alert_threshold' => 'integer',
        'deadline_alert_days' => 'integer',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isInvoice(): bool
    {
        return $this->kind === self::KIND_INVOICE;
    }

    /**
     * Arma el número completo con padding fijo.
     *  - Orden: 'OP-100123'.
     *  - Factura SAR: 'EEE-PPP-TT-NNNNNNNN' (establecimiento, punto de emisión, tipo, correlativo).
     */
    public function formatNumber(int $number): string
    {
        if ($this->isInvoice()) {
            return sprintf(
                '%s-%s-%s-%s',
                str_pad((string) $this->establishment, 3, '0', STR_PAD_LEFT),
                str_pad((string) $this->emission_point, 3, '0', STR_PAD_LEFT),
                str_pad((string) ($this->doc_type_code ?: '01'), 2, '0', STR_PAD_LEFT),
                str_pad((string) $number, 8, '0', STR_PAD_LEFT),
            );
        }

        return $this->code.'-'.str_pad((string) $number, $this->pad_length, '0', STR_PAD_LEFT);
    }

    /** "000-001-01-00000001 al 000-001-01-00000500" (rango autorizado impreso). */
    public function rangeLabel(): ?string
    {
        if ($this->number_to === null) {
            return null;
        }

        return $this->formatNumber($this->number_from).' al '.$this->formatNumber($this->number_to);
    }

    /** Números que quedan por usar en el rango (null = sin tope). */
    public function remaining(): ?int
    {
        if ($this->number_to === null) {
            return null;
        }

        return max(0, $this->number_to - $this->next_number + 1);
    }

    /** Días hasta la fecha límite de emisión (negativo = vencida). */
    public function daysToDeadline(?CarbonImmutable $today = null): ?int
    {
        if ($this->issue_deadline === null) {
            return null;
        }
        $today ??= CarbonImmutable::now(DateRange::TZ)->startOfDay();

        return (int) $today->diffInDays(CarbonImmutable::parse($this->issue_deadline->toDateString(), DateRange::TZ), false);
    }

    /** ¿Ya pasó la fecha límite? La fecha límite misma todavía es válida. */
    public function isPastDeadline(?CarbonImmutable $today = null): bool
    {
        $days = $this->daysToDeadline($today);

        return $days !== null && $days < 0;
    }

    /**
     * Avisos en español sencillo para la pantalla de inicio y la de facturar.
     *
     * @return array<int, array{level:string, message:string}>
     */
    public function alerts(): array
    {
        $alerts = [];
        $label = $this->isInvoice() ? "las facturas (CAI …".substr((string) $this->cai, -6).')' : "la serie {$this->code}";

        $remaining = $this->remaining();
        if ($remaining !== null && $this->status === 'active' && $remaining <= $this->range_alert_threshold) {
            $alerts[] = ['level' => 'warning', 'message' => "Quedan solo {$remaining} números para {$label}. Pida un nuevo CAI pronto."];
        }

        $days = $this->daysToDeadline();
        if ($days !== null && $this->status === 'active') {
            if ($days < 0) {
                $alerts[] = ['level' => 'error', 'message' => "Venció la fecha límite de {$label}. No se pueden emitir más facturas con ese CAI."];
            } elseif ($days <= $this->deadline_alert_days) {
                $txt = $days === 0 ? 'hoy' : ($days === 1 ? 'mañana' : "en {$days} días");
                $alerts[] = ['level' => 'warning', 'message' => "La fecha límite de {$label} vence {$txt}."];
            }
        }

        return $alerts;
    }

    /** ¿Se puede emitir hoy con esta serie? (activa, con números y sin vencer). */
    public function isUsable(): bool
    {
        return $this->status === 'active'
            && ($this->remaining() === null || $this->remaining() > 0)
            && ! $this->isPastDeadline();
    }

    /** Primera serie de facturas utilizable hoy (la más vieja primero), o null. */
    public static function usableInvoiceSeries(): ?self
    {
        return static::query()->activeInvoices()->get()->first(fn (self $s) => $s->isUsable());
    }

    /** Actualiza el estado guardado (agotada / vencida) para que las pantallas lo muestren. */
    public function refreshStatus(): void
    {
        if ($this->status !== 'active') {
            return;
        }
        if ($this->remaining() === 0) {
            $this->update(['status' => 'exhausted']);
        } elseif ($this->isPastDeadline()) {
            $this->update(['status' => 'expired']);
        }
    }

    /**
     * Avisos de facturación para todo el sistema. Marca como agotadas/vencidas las series que
     * correspondan y avisa si ya no queda ningún CAI con el que se pueda facturar.
     *
     * @return array<int, array{level:string, message:string}>
     */
    public static function fiscalAlerts(): array
    {
        $all = static::query()->where('kind', self::KIND_INVOICE)->get();
        if ($all->isEmpty()) {
            return [];
        }
        $all->each->refreshStatus();
        $active = $all->where('status', 'active');
        if ($active->isEmpty()) {
            return [['level' => 'error', 'message' => 'No hay un CAI vigente: no se pueden hacer facturas. Registre el nuevo CAI en Ajustes.']];
        }

        return $active->flatMap(fn (self $s) => $s->alerts())->values()->all();
    }

    public function scopeActiveInvoices(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_INVOICE)->where('status', 'active')->orderBy('number_from')->orderBy('id');
    }
}
