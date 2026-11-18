<?php

use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;

beforeEach(fn () => seedFoundations());

function issuedSale(): Sale
{
    return app(SaleService::class)->issue([
        'lines' => [['description' => 'Repollo', 'quantity' => '1', 'unit_price' => '100.00']],
    ], adminUser());
}

it('anula con admin, PIN correcto y motivo', function () {
    $this->actingAs(adminUser());
    $sale = issuedSale();

    $this->post(route('ventas.void', $sale->id), [
        'reason' => 'Cliente canceló el pedido',
        'pin' => '1234',
    ])->assertRedirect();

    expect($sale->fresh()->status)->toBe(Sale::STATUS_VOIDED);
});

it('rechaza un PIN incorrecto y no anula', function () {
    $this->actingAs(adminUser());
    $sale = issuedSale();

    $this->from(route('ventas.show', $sale->id))
        ->post(route('ventas.void', $sale->id), ['reason' => 'motivo válido', 'pin' => '9999'])
        ->assertSessionHasErrors('pin');

    expect($sale->fresh()->status)->toBe(Sale::STATUS_ISSUED);
});

it('exige motivo', function () {
    $this->actingAs(adminUser());
    $sale = issuedSale();

    $this->post(route('ventas.void', $sale->id), ['reason' => '', 'pin' => '1234'])
        ->assertSessionHasErrors('reason');

    expect($sale->fresh()->status)->toBe(Sale::STATUS_ISSUED);
});

it('un vendedor puede anular solo con el PIN de un admin, y queda registrado el admin', function () {
    $seller = User::factory()->create(['role' => User::ROLE_SELLER]);
    $sale = issuedSale();
    $this->actingAs($seller);

    $this->post(route('ventas.void', $sale->id), ['reason' => 'motivo', 'pin' => '0000'])
        ->assertSessionHasErrors('pin');
    expect($sale->fresh()->status)->toBe(Sale::STATUS_ISSUED);

    $this->post(route('ventas.void', $sale->id), ['reason' => 'motivo', 'pin' => '1234']);
    expect($sale->fresh()->status)->toBe(Sale::STATUS_VOIDED)
        ->and($sale->fresh()->voided_by)->toBe(adminUser()->id);
});

it('el contador no puede anular', function () {
    $acc = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
    $this->actingAs($acc)->post(route('ventas.void', issuedSale()->id), ['reason' => 'motivo', 'pin' => '1234'])
        ->assertForbidden();
});

it('bloquea tras demasiados intentos de PIN', function () {
    $this->actingAs(adminUser());
    $sale = issuedSale();

    for ($i = 0; $i < 5; $i++) {
        $this->from(route('ventas.show', $sale->id))
            ->post(route('ventas.void', $sale->id), ['reason' => 'motivo válido', 'pin' => 'malo']);
    }

    $response = $this->from(route('ventas.show', $sale->id))
        ->post(route('ventas.void', $sale->id), ['reason' => 'motivo válido', 'pin' => '1234']);

    $response->assertSessionHasErrors('pin');
    expect($sale->fresh()->status)->toBe(Sale::STATUS_ISSUED); // bloqueado aun con PIN correcto
});
