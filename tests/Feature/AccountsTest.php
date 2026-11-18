<?php

use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\Freight;
use App\Models\Sale;
use App\Services\AccountsService;
use App\Services\SaleService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    seedFoundations();
    $this->customer = Customer::query()->where('name', 'Pulpería La Esperanza')->firstOrFail();
    $this->accounts = app(AccountsService::class);
    $this->sales = app(SaleService::class);
});

function creditSale(Customer $c, string $price, string $date): Sale
{
    return app(SaleService::class)->issue([
        'customer_id' => $c->id, 'terms' => 'credit', 'issued_at' => $date.' 10:00:00',
        'lines' => [line('Repollo', '1', $price)],
    ], adminUser());
}

it('suma ventas a crédito, saldo anterior y fletes cargados, y resta abonos', function () {
    creditSale($this->customer, '1000.00', '2026-10-01');
    $this->sales->issue(['customer_id' => $this->customer->id, 'terms' => 'cash', 'lines' => [line('Papas', '1', '999.00')]], adminUser());
    $this->accounts->record($this->customer, AccountMovement::TYPE_OPENING, ['amount' => '500', 'date' => '2026-09-01'], adminUser());
    Freight::create(['trip_date' => '2026-10-05', 'destination' => 'SPS', 'customer_id' => $this->customer->id,
        'charge_amount' => '300.00', 'charge_to_account' => true, 'status' => 'done']);
    $this->accounts->record($this->customer, AccountMovement::TYPE_PAYMENT, ['amount' => '200', 'date' => '2026-10-10'], adminUser());

    // 1000 + 500 + 300 − 200 (la venta de contado no cuenta)
    expect($this->accounts->balance($this->customer))->toBe('1600.00');
    expect($this->accounts->balances()->firstWhere('id', $this->customer->id)->balance)->toBe('1600.00');
});

it('excluye ventas anuladas o facturadas, abonos anulados y fletes cancelados', function () {
    invoiceSeries();
    $voided = creditSale($this->customer, '1000.00', '2026-10-01');
    $this->sales->void($voided, 'error', adminUser());
    $order = creditSale($this->customer, '400.00', '2026-10-02');
    $this->sales->convertToInvoice($order, adminUser()); // la factura reemplaza a la orden: 400 una sola vez
    $p = $this->accounts->record($this->customer, AccountMovement::TYPE_PAYMENT, ['amount' => '100'], adminUser());
    $this->accounts->void($p, 'Deshacer', adminUser());
    Freight::create(['trip_date' => '2026-10-05', 'destination' => 'SPS', 'customer_id' => $this->customer->id,
        'charge_amount' => '300.00', 'charge_to_account' => true, 'status' => 'cancelled']);

    expect($this->accounts->balance($this->customer))->toBe('400.00');
});

it('aplica abonos FIFO y calcula la antigüedad por tramos', function () {
    $today = CarbonImmutable::parse('2026-11-17', 'America/Tegucigalpa');
    creditSale($this->customer, '100.00', '2026-07-01'); // 139 días → 90+
    creditSale($this->customer, '200.00', '2026-09-10'); // 68 días → 61–90
    creditSale($this->customer, '300.00', '2026-11-10'); // 7 días → 0–30
    $this->accounts->record($this->customer, AccountMovement::TYPE_PAYMENT, ['amount' => '150', 'date' => '2026-11-12'], adminUser());

    $open = $this->accounts->openDocuments($this->customer, $today);

    // El abono de 150 paga la primera (100) y 50 de la segunda.
    expect($open['documents'])->toHaveCount(2)
        ->and($open['documents'][0]['pending'])->toBe('150.00')
        ->and($open['aging'])->toBe(['0_30' => '300.00', '31_60' => '0.00', '61_90' => '150.00', '90_plus' => '0.00']);
});

it('un pago mayor a la deuda deja saldo a favor', function () {
    creditSale($this->customer, '100.00', '2026-11-01');
    $this->accounts->record($this->customer, AccountMovement::TYPE_PAYMENT, ['amount' => '130'], adminUser());

    expect($this->accounts->balance($this->customer))->toBe('-30.00')
        ->and($this->accounts->openDocuments($this->customer)['credit_in_favor'])->toBe('30.00');
});

it('los abonos no se pueden borrar ni anular dos veces', function () {
    $p = $this->accounts->record($this->customer, AccountMovement::TYPE_PAYMENT, ['amount' => '50'], adminUser());

    expect(fn () => $p->delete())->toThrow(LogicException::class);
    $this->accounts->void($p, '', adminUser());
    expect(fn () => $this->accounts->void($p->fresh(), 'otra vez', adminUser()))->toThrow(InvalidArgumentException::class);
});

it('el estado de cuenta lleva saldo acumulado y saldo al inicio del rango', function () {
    creditSale($this->customer, '100.00', '2026-09-01');
    creditSale($this->customer, '250.00', '2026-10-15');
    $this->accounts->record($this->customer, AccountMovement::TYPE_PAYMENT, ['amount' => '50', 'date' => '2026-10-20'], adminUser());

    $ledger = $this->accounts->ledger($this->customer, '2026-10-01', '2026-10-31');

    expect(collect($ledger)->pluck('kind')->all())->toBe(['Saldo al inicio', 'Pedido', 'Abono'])
        ->and($ledger[0]['balance'])->toBe('100.00')
        ->and($ledger[2]['balance'])->toBe('300.00');
});

it('shows a voided payment and opening balance without changing balances or FIFO', function () {
    creditSale($this->customer, '100.00', '2026-10-01');
    $payment = $this->accounts->record($this->customer, AccountMovement::TYPE_PAYMENT, ['amount' => '30', 'date' => '2026-10-02'], adminUser());
    expect($this->accounts->balance($this->customer))->toBe('70.00');
    $this->accounts->void($payment, 'Monto equivocado', adminUser());
    $opening = $this->accounts->record($this->customer, AccountMovement::TYPE_OPENING, ['amount' => '20', 'date' => '2026-10-03'], adminUser());
    $this->accounts->void($opening, 'Duplicado', adminUser());
    expect($this->accounts->balance($this->customer))->toBe('100.00')
        ->and($this->accounts->openDocuments($this->customer)['documents'][0]['pending'])->toBe('100.00');
    $this->actingAs(adminUser())->get(route('clientes.show', $this->customer))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('balance', '100.00')->has('ledger', 3)
            ->where('ledger.1.status', 'voided')->where('ledger.1.void_reason', 'Monto equivocado')
            ->where('ledger.1.voided_by', adminUser()->name)
            ->where('ledger.1.debit', '0.00')->where('ledger.1.credit', '0.00')
            ->where('ledger.0.balance', '100.00')->where('ledger.1.balance', '100.00')->where('ledger.2.balance', '100.00'));
    $this->get(route('clientes.statement', $this->customer))->assertOk()
        ->assertSee('Monto equivocado')->assertSee('Duplicado')->assertSee('Anulado');
});
