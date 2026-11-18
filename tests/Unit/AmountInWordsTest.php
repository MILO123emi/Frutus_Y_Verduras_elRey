<?php

use App\Support\AmountInWords;

it('convierte montos a letras en lempiras', function (string $amount, string $expected) {
    expect(AmountInWords::lempiras($amount))->toBe($expected);
})->with([
    ['100.00', 'CIEN LEMPIRAS CON 00/100'],
    ['1.50', 'UN LEMPIRA CON 50/100'],
    ['531.00', 'QUINIENTOS TREINTA Y UN LEMPIRAS CON 00/100'],
    ['21000.00', 'VEINTIÚN MIL LEMPIRAS CON 00/100'],
    ['1250316.75', 'UN MILLÓN DOSCIENTOS CINCUENTA MIL TRESCIENTOS DIECISÉIS LEMPIRAS CON 75/100'],
    ['0.99', 'CERO LEMPIRAS CON 99/100'],
]);
