<?php

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Miércoles 14 de octubre de 2026 en Tegucigalpa.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-14 09:00:00', DateRange::TZ));
});

afterEach(fn () => Carbon::setTestNow());

it('este mes cubre el mes completo, inclusivo', function () {
    $r = DateRange::make('mes');
    expect($r->fromDate())->toBe('2026-10-01')
        ->and($r->toDate())->toBe('2026-10-31');
});

it('hoy es solo el día de hoy', function () {
    $r = DateRange::make('hoy');
    expect($r->fromDate())->toBe('2026-10-14')->and($r->toDate())->toBe('2026-10-14');
});

it('la semana empieza el lunes', function () {
    $r = DateRange::make('semana');
    expect($r->fromDate())->toBe('2026-10-12') // lunes
        ->and($r->toDate())->toBe('2026-10-18'); // domingo
});

it('mes anterior', function () {
    $r = DateRange::make('mes_anterior');
    expect($r->fromDate())->toBe('2026-09-01')->and($r->toDate())->toBe('2026-09-30');
});

it('custom es inclusivo y normaliza si viene invertido', function () {
    $r = DateRange::make('custom', '2026-10-10', '2026-10-05');
    expect($r->fromDate())->toBe('2026-10-05')->and($r->toDate())->toBe('2026-10-10')
        ->and($r->days())->toBe(6);
});

it('período anterior de un mes es el mes anterior completo', function () {
    $prev = DateRange::make('mes')->previous();
    expect($prev->fromDate())->toBe('2026-09-01')->and($prev->toDate())->toBe('2026-09-30');
});

it('período anterior de un rango custom son los mismos días justo antes', function () {
    $prev = DateRange::make('custom', '2026-10-08', '2026-10-14')->previous(); // 7 días
    expect($prev->fromDate())->toBe('2026-10-01')->and($prev->toDate())->toBe('2026-10-07');
});
