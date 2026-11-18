<?php

use App\Exceptions\ImmutableSaleException;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;

beforeEach(fn () => seedFoundations());

function freshSale(): Sale
{
    return app(SaleService::class)->issue([
        'lines' => [['description' => 'Repollo', 'quantity' => '1', 'unit_price' => '100.00']],
    ], adminUser());
}

// Una venta emitida es inmutable (§3.3) y nunca se borra (§3.1).

it('lanza excepción al modificar una venta emitida', function () {
    $sale = freshSale();
    $sale->total = '999.00';

    expect(fn () => $sale->save())->toThrow(ImmutableSaleException::class);
});

it('lanza excepción al intentar borrar una venta', function () {
    $sale = freshSale();

    expect(fn () => $sale->delete())->toThrow(ImmutableSaleException::class);
});

it('permite incrementar print_count sin romper la inmutabilidad', function () {
    $sale = freshSale();
    $sale->print_count = $sale->print_count + 1;
    $sale->save();

    expect($sale->fresh()->print_count)->toBe(1);
});

it('anula una venta solo con admin, motivo y bitácora', function () {
    $sale = freshSale();
    $voided = app(SaleService::class)->void($sale, 'Cliente canceló', adminUser());

    expect($voided->status)->toBe(Sale::STATUS_VOIDED)
        ->and($voided->void_reason)->toBe('Cliente canceló')
        ->and($voided->voided_by)->toBe(adminUser()->id)
        ->and($voided->voided_at)->not->toBeNull();
});

it('impide que un no-admin anule', function () {
    $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
    $sale = freshSale();

    expect(fn () => app(SaleService::class)->void($sale, 'motivo', $seller))
        ->toThrow(RuntimeException::class);
});

it('exige un motivo de anulación', function () {
    $sale = freshSale();

    expect(fn () => app(SaleService::class)->void($sale, '   ', adminUser()))
        ->toThrow(InvalidArgumentException::class);
});

it('una venta ya anulada no puede modificarse', function () {
    $sale = freshSale();
    app(SaleService::class)->void($sale, 'motivo', adminUser());

    $sale->print_count = 5;
    expect(fn () => $sale->save())->toThrow(ImmutableSaleException::class);
});
