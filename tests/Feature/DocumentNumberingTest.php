<?php

use App\Models\Sale;
use App\Services\DocumentNumberService;
use Illuminate\Database\QueryException;

beforeEach(fn () => seedFoundations());

// La numeración es correlativa por serie y nunca se repite ni se salta (§3.2).

it('asigna números consecutivos con padding fijo', function () {
    $service = app(DocumentNumberService::class);
    $series = opSeries();

    $first = $service->assign($series);
    $second = $service->assign($series->fresh());

    expect($first['number'])->toBe(100001)
        ->and($first['full_number'])->toBe('OP-100001')
        ->and($second['number'])->toBe(100002)
        ->and($second['full_number'])->toBe('OP-100002')
        ->and((int) opSeries()->next_number)->toBe(100003);
});

it('no produce huecos ni duplicados en una ráfaga de asignaciones', function () {
    $service = app(DocumentNumberService::class);
    $numbers = [];
    for ($i = 0; $i < 50; $i++) {
        $numbers[] = $service->assign(opSeries())['number'];
    }

    expect($numbers)->toHaveCount(50)
        ->and(array_unique($numbers))->toHaveCount(50) // sin duplicados
        ->and($numbers)->toBe(range(100001, 100050)); // consecutivos, sin huecos
});

it('la restricción unique(document_series_id, number) impide duplicar un número', function () {
    $admin = adminUser();
    $series = opSeries();

    Sale::create([
        'document_series_id' => $series->id,
        'number' => 100001,
        'full_number' => 'OP-100001',
        'issued_at' => now(),
        'issue_date' => now()->toDateString(),
        'customer_name_snapshot' => 'CONSUMIDOR FINAL',
        'issuer_snapshot' => [],
        'terms' => 'cash',
        'total' => '0',
        'status' => 'issued',
        'user_id' => $admin->id,
    ]);

    expect(fn () => Sale::create([
        'document_series_id' => $series->id,
        'number' => 100001, // mismo número en la misma serie
        'full_number' => 'OP-100001-dup',
        'issued_at' => now(),
        'issue_date' => now()->toDateString(),
        'customer_name_snapshot' => 'CONSUMIDOR FINAL',
        'issuer_snapshot' => [],
        'terms' => 'cash',
        'total' => '0',
        'status' => 'issued',
        'user_id' => $admin->id,
    ]))->toThrow(QueryException::class);
});
