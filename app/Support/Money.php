<?php

namespace App\Support;

/**
 * Cálculos de dinero con bcmath: nunca float (§3.6). Todos los valores entran y
 * salen como strings con 2 decimales, compatibles con las columnas numeric(12,2).
 */
final class Money
{
    public const SCALE = 2;

    /** Redondeo half-up a `scale` decimales (bcmath trunca, por eso sumamos 0.5 ulp). */
    public static function round(string $number, int $scale = self::SCALE): string
    {
        $fix = ($number[0] === '-' ? '-' : '').'0.'.str_repeat('0', $scale).'5';

        return bcadd($number, $fix, $scale);
    }

    /** Total de línea = round(cantidad × precio, 2) (§3.6). */
    public static function lineTotal(string $quantity, string $unitPrice): string
    {
        return self::round(bcmul($quantity, $unitPrice, 6), self::SCALE);
    }

    /** Impuesto = round(base × tasa, 2). La tasa es un string como '0.15'. */
    public static function tax(string $base, string $rate): string
    {
        return self::round(bcmul($base, $rate, 6), self::SCALE);
    }

    /** Suma una lista de montos con 2 decimales. */
    public static function sum(string ...$amounts): string
    {
        $total = '0';
        foreach ($amounts as $amount) {
            $total = bcadd($total, $amount, self::SCALE);
        }

        return bcadd($total, '0', self::SCALE);
    }
}
