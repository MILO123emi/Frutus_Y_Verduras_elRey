<?php

use App\Exceptions\SeriesUnavailableException;
use App\Models\DocumentSeries;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Support\Carbon;

beforeEach(fn () => seedFoundations());

it('numera facturas con el formato SAR 000-001-01-00000001 y guarda el snapshot fiscal', function () {
    $series = invoiceSeries();
    $svc = app(SaleService::class);

    $a = $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());
    $b = $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());

    expect($a->full_number)->toBe('000-001-01-00000001')
        ->and($b->full_number)->toBe('000-001-01-00000002')
        ->and($a->isInvoice())->toBeTrue()
        ->and($a->fiscal_snapshot['cai'])->toBe($series->cai)
        ->and($a->fiscal_snapshot['range'])->toBe('000-001-01-00000001 al 000-001-01-00000100')
        ->and($a->issuer_snapshot['legal_name'])->toBe('Juan Pérez (prueba)');
});

it('las órdenes de pedido no se ven afectadas por la serie de facturas', function () {
    invoiceSeries();
    $order = app(SaleService::class)->issue(['lines' => [line()]], adminUser());

    expect($order->full_number)->toBe('OP-100001')->and($order->isInvoice())->toBeFalse();
});

it('bloquea la emisión cuando el rango del CAI se agota y la serie queda agotada', function () {
    $series = invoiceSeries(['number_from' => 1, 'next_number' => 1, 'number_to' => 2]);
    $svc = app(SaleService::class);
    $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());
    $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());

    expect($series->fresh()->status)->toBe('exhausted');
    expect(fn () => $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser()))
        ->toThrow(SeriesUnavailableException::class, 'CAI vigente');
});

it('bloquea la emisión cuando la fecha límite del CAI ya pasó, pero permite el mismo día', function () {
    $series = invoiceSeries(['issue_deadline' => now('America/Tegucigalpa')->toDateString()]);
    $svc = app(SaleService::class);

    expect($svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser())->full_number)->toEndWith('00000001');

    Carbon::setTestNow(now()->addDays(2));
    expect(fn () => $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser()))
        ->toThrow(SeriesUnavailableException::class);
    // Y si se le pasa la serie directamente, el servicio de numeración también la rechaza.
    expect(fn () => $svc->issue(['series' => $series->fresh(), 'lines' => [line()]], adminUser()))
        ->toThrow(SeriesUnavailableException::class, 'venció');
    Carbon::setTestNow();
});

it('pasa automáticamente al siguiente CAI registrado cuando el primero se agota', function () {
    invoiceSeries(['number_from' => 1, 'next_number' => 1, 'number_to' => 1]);
    invoiceSeries(['number_from' => 2, 'next_number' => 2, 'number_to' => 500, 'cai' => 'NUEVO-CAI-0000-0000-0000-00']);
    $svc = app(SaleService::class);

    $first = $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());
    $second = $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());

    expect($first->full_number)->toBe('000-001-01-00000001')
        ->and($second->full_number)->toBe('000-001-01-00000002')
        ->and($second->fiscal_snapshot['cai'])->toBe('NUEVO-CAI-0000-0000-0000-00');
});

it('avisa cuando quedan pocos números o la fecha límite está cerca', function () {
    $series = invoiceSeries(['next_number' => 60, 'number_to' => 100,
        'issue_deadline' => now('America/Tegucigalpa')->addDays(10)->toDateString()]);

    $messages = collect($series->alerts())->pluck('message')->implode(' | ');
    expect($messages)->toContain('Quedan solo 41 números')->toContain('en 10 días');

    $ok = invoiceSeries(['next_number' => 1, 'number_to' => 1000]);
    expect($ok->alerts())->toBe([]);
});

it('exige nombre y RTN en facturas de L 10,000 o más', function () {
    invoiceSeries();
    $svc = app(SaleService::class);

    expect(fn () => $svc->issue(['kind' => 'invoice', 'lines' => [line('Papas', '100', '100.00')]], adminUser()))
        ->toThrow(InvalidArgumentException::class, '10,000');

    $ok = $svc->issue(['kind' => 'invoice', 'customer_name' => 'Hotel Costa', 'customer_rtn' => '08011999123456',
        'lines' => [line('Papas', '100', '100.00')]], adminUser());
    expect($ok->total)->toBe('10000.00');
});

it('no emite facturas si falta la razón social del negocio', function () {
    invoiceSeries();
    \App\Models\BusinessSettings::current()->update(['legal_name' => null]);

    expect(fn () => app(SaleService::class)->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser()))
        ->toThrow(SeriesUnavailableException::class, 'razón social');
});

it('con comprador exonerado lo gravado pasa a exonerado sin ISV', function () {
    invoiceSeries();
    $sale = app(SaleService::class)->issue(['kind' => 'invoice', 'exonerated_record' => 'EXO-123',
        'lines' => [line('Uvas', '1', '200.00', 'g15'), line()]], adminUser());

    expect($sale->subtotal_exonerated)->toBe('200.00')
        ->and($sale->tax_15)->toBe('0.00')
        ->and($sale->total)->toBe('300.00');
});

it('convierte una orden en factura: la orden queda facturada y deja de contar', function () {
    invoiceSeries();
    $svc = app(SaleService::class);
    $order = $svc->issue(['customer_id' => \App\Models\Customer::first()->id, 'terms' => 'credit',
        'lines' => [line('Repollo', '3', '120.00')]], adminUser());

    $invoice = $svc->convertToInvoice($order, adminUser());
    $order->refresh();

    expect($order->status)->toBe(Sale::STATUS_CONVERTED)
        ->and($order->converted_to_sale_id)->toBe($invoice->id)
        ->and($invoice->source_sale_id)->toBe($order->id)
        ->and($invoice->total)->toBe('360.00')
        ->and($invoice->terms)->toBe('credit')
        ->and(Sale::issued()->sum('total'))->toEqual(360);

    expect(fn () => $svc->convertToInvoice($order, adminUser()))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->void($order->fresh(), 'x', adminUser()))->toThrow(InvalidArgumentException::class, 'facturó');
});

it('anula una factura con motivo, sin liberar su número', function () {
    invoiceSeries();
    $svc = app(SaleService::class);
    $inv = $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());
    $svc->void($inv, 'Error en cantidad', adminUser());
    $next = $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());

    expect($inv->fresh()->status)->toBe('voided')
        ->and($next->full_number)->toBe('000-001-01-00000002');
});

it('la factura impresa lleva CAI, rango, fecha límite y nunca dice ORDEN DE PEDIDO; la orden nunca dice FACTURA', function () {
    invoiceSeries();
    $svc = app(SaleService::class);
    $inv = $svc->issue(['kind' => 'invoice', 'lines' => [line()]], adminUser());
    $order = $svc->issue(['lines' => [line()]], adminUser());

    $pdfs = app(\App\Services\DocumentPdfService::class);
    $invHtml = $pdfs->renderHtml($inv, 'original');
    $orderHtml = $pdfs->renderHtml($order, 'original');

    expect($invHtml)->toContain('FACTURA')->toContain('CAI')->toContain('Rango autorizado')
        ->toContain('Fecha límite de emisión')->toContain('000-001-01-00000001')
        ->toContain('ORIGINAL: CLIENTE')->toContain('CIEN LEMPIRAS');
    expect(mb_strtoupper(strip_tags($orderHtml)))->not->toContain('FACTURA');
});
