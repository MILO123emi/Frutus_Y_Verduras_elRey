<?php

use App\Support\Money;

// Dinero con bcmath, nunca float (§3.6).

it('redondea half-up a 2 decimales', function () {
    expect(Money::round('1.005'))->toBe('1.01')
        ->and(Money::round('1.004'))->toBe('1.00')
        ->and(Money::round('2.675'))->toBe('2.68')
        ->and(Money::round('10'))->toBe('10.00');
});

it('calcula el total de línea como round(cantidad × precio, 2)', function () {
    expect(Money::lineTotal('3', '2.50'))->toBe('7.50')
        ->and(Money::lineTotal('2.5', '3.33'))->toBe('8.33')   // 8.325 -> 8.33
        ->and(Money::lineTotal('1.5', '0.333'))->toBe('0.50'); // 0.4995 -> 0.50
});

it('calcula impuesto como round(base × tasa, 2)', function () {
    expect(Money::tax('100.00', '0.15'))->toBe('15.00')
        ->and(Money::tax('100.00', '0.18'))->toBe('18.00')
        ->and(Money::tax('33.33', '0.15'))->toBe('5.00'); // 4.9995 -> 5.00
});

it('suma montos con 2 decimales', function () {
    expect(Money::sum('0.10', '0.20', '0.03'))->toBe('0.33')
        ->and(Money::sum('1000.55', '0.45'))->toBe('1001.00');
});
