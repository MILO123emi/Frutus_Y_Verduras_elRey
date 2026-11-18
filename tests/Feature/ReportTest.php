<?php

use App\Models\AccountMovement;
use App\Models\Customer;
use App\Services\AccountsService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Support\DateRange;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(fn () => seedFoundations());

it('el resumen excluye anuladas y órdenes facturadas, y separa contado y crédito', function () {
    invoiceSeries();
    $svc = app(SaleService::class);
    $c = Customer::first();
    $svc->issue(['issued_at' => '2026-10-01 09:00', 'lines' => [line('Repollo', '2', '100.00')]], adminUser());
    $svc->issue(['issued_at' => '2026-10-31 18:00', 'customer_id' => $c->id, 'terms' => 'credit', 'lines' => [line('Papas', '1', '300.00')]], adminUser());
    $v = $svc->issue(['issued_at' => '2026-10-15 09:00', 'lines' => [line('Yuca', '1', '999.00')]], adminUser());
    $svc->void($v, 'error', adminUser());
    $o = $svc->issue(['issued_at' => '2026-10-20 09:00', 'lines' => [line('Ajos', '1', '50.00')]], adminUser());
    $svc->convertToInvoice($o, adminUser()); // la factura sale hoy (fuera del rango)
    $svc->issue(['issued_at' => '2026-11-01 00:10', 'lines' => [line('Fuera', '1', '1000.00')]], adminUser());
    app(AccountsService::class)->record($c, AccountMovement::TYPE_PAYMENT, ['amount' => '100', 'date' => '2026-10-31'], adminUser());

    $s = app(ReportService::class)->summary(DateRange::make('custom', '2026-10-01', '2026-10-31'));

    expect($s['count'])->toBe(2)->and($s['total'])->toBe('500.00')
        ->and($s['cash'])->toBe('200.00')->and($s['credit'])->toBe('300.00')
        ->and($s['voided'])->toBe(1)->and($s['collected'])->toBe('100.00')
        ->and($s['top_products'][0]['quantities'])->toBe('1 Saco');
});

it('genera el Excel con las hojas y los totales solo de activas', function () {
    $svc = app(SaleService::class);
    $svc->issue(['issued_at' => '2026-10-02 09:00', 'lines' => [line('Repollo', '1', '100.00')]], adminUser());
    $v = $svc->issue(['issued_at' => '2026-10-03 09:00', 'lines' => [line('Yuca', '1', '40.00')]], adminUser());
    $svc->void($v, 'mal', adminUser());

    $path = app(ReportService::class)->excel(DateRange::make('custom', '2026-10-01', '2026-10-31'));
    $book = IOFactory::load($path);

    expect($book->getSheetNames())->toBe(['Ventas', 'Libro de ventas (facturas)', 'Por día', 'Abonos', 'Saldos al día de hoy']);
    $sheet = $book->getSheet(0);
    expect($sheet->getCell('M3')->getValue())->toBe('ANULADA')
        ->and($sheet->getCell('L5')->getValue())->toEqual(100.0);
    @unlink($path);
});

it('el ZIP de un rango vacío responde 422 y con ventas descarga', function () {
    $this->actingAs(adminUser());
    $this->get('/reportes/zip?range=custom&from=2020-01-01&to=2020-01-31')->assertStatus(422);

    app(SaleService::class)->issue(['issued_at' => '2026-10-02 09:00', 'lines' => [line()]], adminUser());
    $res = $this->get('/reportes/zip?range=custom&from=2026-10-01&to=2026-10-31');
    $res->assertOk();
    expect($res->headers->get('Content-Disposition'))->toContain('ElRey_Ventas_2026-10-01_a_2026-10-31.zip');
});
