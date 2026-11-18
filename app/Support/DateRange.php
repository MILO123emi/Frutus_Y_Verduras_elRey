<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Rango de fechas con atajos, reutilizable por el listado (Parte 1), el reporte
 * del contador (Parte 3) y el panel (Parte 4).
 *
 * Reglas §3.9: zona America/Tegucigalpa, rangos INCLUSIVOS (la fecha fin cubre
 * todo ese día), la semana empieza el LUNES.
 */
class DateRange
{
    public const TZ = 'America/Tegucigalpa';

    final public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset,
    ) {
    }

    public static function make(?string $preset, ?string $from = null, ?string $to = null): self
    {
        $preset = $preset ?: 'mes';
        $today = CarbonImmutable::now(self::TZ)->startOfDay();

        return match ($preset) {
            'hoy' => new self($today, $today->endOfDay(), 'hoy'),
            'semana' => new self($today->startOfWeek(), $today->endOfWeek(), 'semana'),
            'mes_anterior' => new self(
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth(),
                'mes_anterior',
            ),
            'custom' => self::custom($from, $to, $today),
            default => new self($today->startOfMonth(), $today->endOfMonth(), 'mes'),
        };
    }

    private static function custom(?string $from, ?string $to, CarbonImmutable $today): self
    {
        $start = $from ? CarbonImmutable::parse($from, self::TZ)->startOfDay() : $today->startOfMonth();
        $end = $to ? CarbonImmutable::parse($to, self::TZ)->endOfDay() : $today->endOfDay();

        // Normaliza si vienen invertidas.
        if ($end->lessThan($start)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return new self($start, $end, 'custom');
    }

    /** Para columnas `date`: strings YYYY-MM-DD inclusivos. */
    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    /** Número de días inclusivos del rango. */
    public function days(): int
    {
        return $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * Período anterior para comparar (Parte 4): con atajo de mes, el mismo tramo
     * del mes anterior; en cualquier otro caso, el mismo número de días justo antes.
     */
    public function previous(): self
    {
        if ($this->preset === 'mes') {
            $prev = $this->from->subMonthNoOverflow();

            return new self($prev->startOfMonth(), $prev->endOfMonth(), 'mes');
        }

        $length = $this->days();
        $end = $this->from->subDay()->endOfDay();
        $start = $end->subDays($length - 1)->startOfDay();

        return new self($start, $end, 'custom');
    }
}
