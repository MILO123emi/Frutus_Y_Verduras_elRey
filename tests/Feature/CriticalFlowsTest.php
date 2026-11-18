<?php

use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\AccountsService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Support\DateRange;

beforeEach(fn () => seedFoundations());

it('rechaza anular sin PIN y registra motivo usuario y fecha al autorizar', function () {
    $sale = app(SaleService::class)->issue(['lines' => [line()]], adminUser());
    $this->actingAs(sellerUser())->post(route('ventas.void', $sale), ['reason' => 'Error de cantidad'])
        ->assertSessionHasErrors('pin');
    expect($sale->fresh()->status)->toBe('issued');
    $this->post(route('ventas.void', $sale), ['reason' => 'Error de cantidad', 'pin' => '1234'])->assertRedirect();
    $sale->refresh();
    expect($sale->void_reason)->toBe('Error de cantidad')
        ->and($sale->voided_by)->toBe(adminUser()->id)
        ->and($sale->voided_at)->not->toBeNull();
});

it('devuelve validación clara al emitir sin CAI utilizable o sin identidad desde diez mil', function () {
    $this->actingAs(adminUser())->post(route('ventas.store'), ['kind' => 'invoice', 'lines' => [line()]])
        ->assertSessionHasErrors(['general' => 'No hay un CAI vigente para facturar (se agotó o venció). Registre uno nuevo en Ajustes → Facturación.']);
    invoiceSeries();
    $this->post(route('ventas.store'), ['kind' => 'invoice', 'customer_name' => 'Hotel', 'lines' => [line('Papas', '100', '100')]])
        ->assertSessionHasErrors('general');
    expect(Sale::count())->toBe(0);
});

it('salta un CAI vencido y uno agotado para usar el siguiente', function () {
    invoiceSeries(['issue_deadline' => now(DateRange::TZ)->subDay()->toDateString()]);
    invoiceSeries(['number_from' => 101, 'number_to' => 101, 'next_number' => 102]);
    $usable = invoiceSeries(['number_from' => 102, 'number_to' => 200, 'next_number' => 102]);
    $sale = app(SaleService::class)->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());
    expect($sale->document_series_id)->toBe($usable->id)->and($sale->number)->toBe(102);
});

it('convertir dos veces por HTTP no duplica factura ni deuda', function () {
    invoiceSeries();
    $customer = Customer::firstOrFail();
    $order = app(SaleService::class)->issue(['customer_id' => $customer->id, 'terms' => 'credit', 'lines' => [line()]], adminUser());
    $this->actingAs(adminUser())->post(route('ventas.convert', $order))->assertRedirect();
    $this->post(route('ventas.convert', $order))->assertRedirect();
    expect(Sale::where('source_sale_id', $order->id)->count())->toBe(1)
        ->and(app(AccountsService::class)->balance($customer))->toBe('100.00');
});

it('deshace el abono por HTTP conservando su registro y auditoría', function () {
    $customer = Customer::firstOrFail();
    $accounts = app(AccountsService::class);
    $accounts->record($customer, AccountMovement::TYPE_OPENING, ['amount' => '100'], adminUser());
    $this->actingAs(sellerUser())->post(route('movimientos.store', $customer), ['type' => 'payment', 'amount' => '130', 'date' => '2026-11-17'])
        ->assertRedirect()->assertSessionHas('undo');
    $payment = AccountMovement::where('type', AccountMovement::TYPE_PAYMENT)->firstOrFail();
    expect($accounts->balance($customer))->toBe('-30.00');
    $this->post(route('movimientos.void', $payment), ['reason' => 'Monto equivocado'])->assertRedirect();
    $payment->refresh();
    expect($accounts->balance($customer))->toBe('100.00')
        ->and($payment->status)->toBe('voided')
        ->and($payment->void_reason)->toBe('Monto equivocado')
        ->and($payment->voided_by)->toBe(sellerUser()->id)
        ->and($payment->voided_at)->not->toBeNull();
});

it('respeta medianoche de Honduras y excluye anuladas y convertidas del reporte', function () {
    invoiceSeries();
    $sales = app(SaleService::class);
    // 05:59 UTC es aún el día anterior en Honduras; 06:00 UTC ya es medianoche.
    foreach (['2026-11-17T05:59:59Z' => '10', '2026-11-17T06:00:00Z' => '100', '2026-11-18T05:59:59Z' => '200', '2026-11-18T06:00:00Z' => '1000'] as $instant => $amount) {
        $sale = $sales->issue(['issued_at' => $instant, 'lines' => [line('Papas', '1', $amount)]], adminUser());
        expect($sale->fresh()->issued_at->utc()->format('Y-m-d\\TH:i:s\\Z'))->toBe($instant);
    }
    $voided = $sales->issue(['issued_at' => '2026-11-17T12:00:00Z', 'lines' => [line()]], adminUser());
    $sales->void($voided, 'Error', adminUser());
    $order = $sales->issue(['issued_at' => '2026-11-17T12:00:00Z', 'lines' => [line()]], adminUser());
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-17T18:00:00Z'));
    $sales->convertToInvoice($order, adminUser());
    $summary = app(ReportService::class)->summary(DateRange::make('custom', '2026-11-17', '2026-11-17'));
    expect($summary['count'])->toBe(3)->and($summary['total'])->toBe('400.00');
    $this->travelBack();
});

it('mantiene errores de conversión disponibles para corregir el formulario', function () {
    $order = app(SaleService::class)->issue(['lines' => [line()]], adminUser());
    $this->actingAs(adminUser())->from(route('ventas.show', $order))
        ->post(route('ventas.convert', $order))->assertSessionHasErrors('general');
    expect($order->fresh()->status)->toBe('issued')->and(Sale::count())->toBe(1);
});
