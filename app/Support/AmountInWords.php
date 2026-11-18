<?php

namespace App\Support;

/** Monto en letras para la factura: "CIEN LEMPIRAS CON 50/100". */
final class AmountInWords
{
    private const UNITS = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];

    private const TENS = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];

    private const HUNDREDS = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function lempiras(string $amount): string
    {
        [$int, $dec] = array_pad(explode('.', Money::round($amount)), 2, '00');
        $words = self::words((int) $int, true);
        $currency = ((int) $int) === 1 ? 'LEMPIRA' : 'LEMPIRAS';

        return trim("{$words} {$currency} CON ".str_pad($dec, 2, '0').'/100');
    }

    public static function words(int $n, bool $apocopeLast = false): string
    {
        if ($n === 0) {
            return 'CERO';
        }
        $parts = [];
        $millions = intdiv($n, 1_000_000);
        $thousands = intdiv($n % 1_000_000, 1000);
        $rest = $n % 1000;

        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'UN MILLÓN' : self::upTo999($millions, true).' MILLONES';
        }
        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'MIL' : self::upTo999($thousands, true).' MIL';
        }
        if ($rest > 0) {
            $parts[] = self::upTo999($rest, $apocopeLast);
        }

        return implode(' ', $parts);
    }

    /** $apocope: "UN"/"VEINTIÚN" delante de MIL/MILLONES. */
    private static function upTo999(int $n, bool $apocope): string
    {
        if ($n === 100) {
            return 'CIEN';
        }
        $h = intdiv($n, 100);
        $r = $n % 100;
        $out = $h > 0 ? self::HUNDREDS[$h] : '';

        if ($r > 0) {
            if ($r < 30) {
                $w = self::UNITS[$r];
                if ($apocope && $r === 1) {
                    $w = 'UN';
                } elseif ($apocope && $r === 21) {
                    $w = 'VEINTIÚN';
                }
            } else {
                $u = $r % 10;
                $w = self::TENS[intdiv($r, 10)];
                if ($u > 0) {
                    $w .= ' Y '.(($apocope && $u === 1) ? 'UN' : self::UNITS[$u]);
                }
            }
            $out = trim($out.' '.$w);
        }

        return $out;
    }
}
