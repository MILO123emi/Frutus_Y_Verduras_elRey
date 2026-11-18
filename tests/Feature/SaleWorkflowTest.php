<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;

beforeEach(fn () => seedFoundations());

function sampleLines(): array
{
    return [
        ['description' => 'Repollo', 'unit' => 'Saco', 'quantity' => '3', 'unit_price' => '120.00'],
        ['description' => 'Papas', 'unit' => 'Saco', 'quantity' => '2', 'unit_price' => '80.00'],
    ];
}

it('registra una venta y redirige al visor para imprimir', function () {
    $this->actingAs(adminUser());

    $response = $this->post(route('ventas.store'), [
        'customer_id' => null,
        'terms' => 'cash',
        'lines' => sampleLines(),
    ]);

    $sale = Sale::query()->latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and($sale->full_number)->toBe('OP-100001')
        ->and((float) $sale->total)->toBe(520.0);

    $response->assertRedirect(route('ventas.show', ['sale' => $sale->id, 'print' => 1]));
});

it('impide al contador registrar ventas', function () {
    $accountant = User::factory()->create(['role' => User::ROLE_ACCOUNTANT]);
    $this->actingAs($accountant);

    $this->post(route('ventas.store'), ['lines' => sampleLines()])->assertForbidden();
});

it('valida que haya al menos una línea', function () {
    $this->actingAs(adminUser());
    $this->post(route('ventas.store'), ['lines' => []])->assertSessionHasErrors('lines');
});

it('el documento se renderiza con las dos copias al imprimir', function () {
    $this->actingAs(adminUser());
    $sale = app(SaleService::class)->issue(['lines' => sampleLines()], adminUser());

    $html = $this->get(route('ventas.documento', ['sale' => $sale->id, 'print' => 1]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('OP-100001')
        ->toContain('ORIGINAL – CLIENTE')
        ->toContain('COPIA – EMISOR')
        ->toContain('ORDEN DE PEDIDO');
});

it('el documento de una venta anulada lleva la marca ANULADA', function () {
    $sale = app(SaleService::class)->issue(['lines' => sampleLines()], adminUser());
    app(SaleService::class)->void($sale, 'Prueba', adminUser());
    $this->actingAs(adminUser());

    $html = $this->get(route('ventas.documento', ['sale' => $sale->id]))->assertOk()->getContent();
    expect($html)->toContain('ANULADA');
});

it('registrar impresión incrementa print_count', function () {
    $this->actingAs(adminUser());
    $sale = app(SaleService::class)->issue(['lines' => sampleLines()], adminUser());

    $this->post(route('ventas.print', $sale->id))->assertNoContent();
    expect($sale->fresh()->print_count)->toBe(1);
});

it('devuelve el último precio cobrado al cliente por producto y unidad', function () {
    $this->actingAs(adminUser());
    $customer = Customer::query()->first();
    $product = Product::query()->where('name', 'Repollo')->first();

    app(SaleService::class)->issue([
        'customer_id' => $customer->id,
        'lines' => [[
            'product_id' => $product->id, 'description' => 'Repollo',
            'unit' => 'Saco', 'quantity' => '1', 'unit_price' => '135.00',
        ]],
    ], adminUser());

    $response = $this->getJson(route('ventas.last-price', [
        'customer_id' => $customer->id, 'product_id' => $product->id, 'unit' => 'Saco',
    ]));

    expect((float) $response->json('price'))->toBe(135.0);
});

it('repite el último pedido del cliente', function () {
    $this->actingAs(adminUser());
    $customer = Customer::query()->first();
    app(SaleService::class)->issue([
        'customer_id' => $customer->id,
        'lines' => sampleLines(),
    ], adminUser());

    $lines = $this->getJson(route('ventas.repeat', ['customer_id' => $customer->id]))->json('lines');
    expect($lines)->toHaveCount(2)
        ->and($lines[0]['description'])->toBe('Repollo');
});

it('da de alta un producto desde la venta', function () {
    $this->actingAs(adminUser());
    $response = $this->postJson(route('productos.store'), ['name' => 'Yuca morada']);

    $response->assertOk();
    expect(Product::query()->where('name', 'Yuca morada')->exists())->toBeTrue()
        ->and($response->json('product.tax_type'))->toBe('exempt');
});

it('rejects an invalid quantity at its original line index', function ($quantity) {
    $this->actingAs(User::factory()->create(['role' => 'seller']))
        ->postJson(route('ventas.store'), [
            'kind' => 'order', 'terms' => 'cash',
            'lines' => [sampleLines()[0], ['description' => 'Papas', 'quantity' => $quantity, 'unit_price' => '10.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('lines.1.quantity');
})->with(['abc', '0', '-1']);
