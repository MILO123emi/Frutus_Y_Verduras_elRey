<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\SaleService;

beforeEach(fn () => seedFoundations());

// Emisión: subtotales, impuestos y snapshots (§3.4, §3.6, §3.7).

it('emite una venta exenta con el total correcto y numera', function () {
    $sale = app(SaleService::class)->issue([
        'lines' => [
            ['description' => 'Repollo', 'unit' => 'Saco', 'quantity' => '3', 'unit_price' => '120.00'],
            ['description' => 'Tomate pera', 'unit' => 'Caja', 'quantity' => '2', 'unit_price' => '85.50'],
        ],
    ], adminUser());

    expect($sale->full_number)->toBe('OP-100001')
        ->and($sale->subtotal_exempt)->toBe('531.00') // 360 + 171
        ->and($sale->tax_15)->toBe('0.00')
        ->and($sale->total)->toBe('531.00')
        ->and($sale->items)->toHaveCount(2)
        ->and($sale->customer_name_snapshot)->toBe('CONSUMIDOR FINAL');
});

it('separa subtotales e ISV por tipo de impuesto', function () {
    $sale = app(SaleService::class)->issue([
        'lines' => [
            ['description' => 'Verdura exenta', 'quantity' => '1', 'unit_price' => '100.00', 'tax_type' => 'exempt'],
            ['description' => 'Bien gravado 15', 'quantity' => '1', 'unit_price' => '200.00', 'tax_type' => 'g15'],
            ['description' => 'Bien gravado 18', 'quantity' => '1', 'unit_price' => '300.00', 'tax_type' => 'g18'],
        ],
    ], adminUser());

    expect($sale->subtotal_exempt)->toBe('100.00')
        ->and($sale->subtotal_g15)->toBe('200.00')
        ->and($sale->subtotal_g18)->toBe('300.00')
        ->and($sale->tax_15)->toBe('30.00')   // 200 * 0.15
        ->and($sale->tax_18)->toBe('54.00')   // 300 * 0.18
        ->and($sale->total)->toBe('684.00');  // 600 + 30 + 54
});

it('redondea cada total de línea a 2 decimales', function () {
    $sale = app(SaleService::class)->issue([
        'lines' => [
            ['description' => 'Fracción', 'quantity' => '2.5', 'unit_price' => '3.33'], // 8.325 -> 8.33
        ],
    ], adminUser());

    expect($sale->items->first()->line_total)->toBe('8.33')
        ->and($sale->total)->toBe('8.33');
});

it('toma los snapshots del cliente y escribe la bitácora', function () {
    $customer = Customer::query()->where('default_terms', 'credit')->first();

    $sale = app(SaleService::class)->issue([
        'customer_id' => $customer->id,
        'lines' => [['description' => 'Papas', 'quantity' => '1', 'unit_price' => '50.00']],
    ], adminUser());

    expect($sale->customer_id)->toBe($customer->id)
        ->and($sale->customer_name_snapshot)->toBe($customer->name)
        ->and($sale->terms)->toBe('credit'); // tomado del default del cliente

    expect(AuditLog::query()->where('action', 'sale.issued')->where('auditable_id', $sale->id)->exists())->toBeTrue();
});

it('rechaza una venta sin líneas', function () {
    expect(fn () => app(SaleService::class)->issue(['lines' => []], adminUser()))
        ->toThrow(InvalidArgumentException::class);
});
