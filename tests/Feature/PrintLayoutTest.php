<?php

use App\Models\BusinessSettings;
use App\Models\Customer;
use App\Services\DocumentPdfService;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;

beforeEach(fn () => seedFoundations());

it('aplica el papel elegido y conserva textos y copias de cada documento', function (string $paper, string $size, string $kind) {
    BusinessSettings::current()->update(['print_settings' => ['paper_size' => $paper]]);
    if ($kind === 'invoice') invoiceSeries();
    $sale = app(SaleService::class)->issue(['kind' => $kind, 'lines' => [line()]], adminUser());
    foreach (['original', 'copy'] as $variant) {
        $html = app(DocumentPdfService::class)->renderHtml($sale, $variant);
        expect($html)->toContain('@page')->toContain('size: '.$size)->toContain($sale->full_number)->toContain('data:image/png;base64,');
        expect(strip_tags($html))->toContain($variant === 'copy' ? 'COPIA' : 'ORIGINAL');
        if ($kind === 'invoice') {
            expect($html)->toContain('CAI')->toContain('Rango autorizado')->toContain('CIEN LEMPIRAS');
        } else {
            expect(mb_strtoupper(strip_tags($html)))->not->toContain('FACTURA');
        }
        $pdf = Pdf::loadHTML($html);
        $pdf->render();
        assertPrintedLogo($pdf, $sale->full_number);
        expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
        expect($pdf->getDomPDF()->getCanvas()->get_width())->toEqual(612)
            ->and($pdf->getDomPDF()->getCanvas()->get_height())->toEqual($paper === 'half-letter' ? 396 : 792);
    }
})->with([
    ['letter', '8.5in 11in', 'order'], ['half-letter', '8.5in 5.5in', 'order'],
    ['letter', '8.5in 11in', 'invoice'], ['half-letter', '8.5in 5.5in', 'invoice'],
]);

it('renderiza estado de cuenta en ambos papeles con saldos y movimientos', function (string $paper, string $size) {
    BusinessSettings::current()->update(['print_settings' => ['paper_size' => $paper]]);
    $customer = Customer::firstOrFail();
    app(SaleService::class)->issue(['customer_id' => $customer->id, 'terms' => 'credit', 'lines' => [line()]], adminUser());
    $response = $this->actingAs(adminUser())->get(route('clientes.statement', $customer));
    $response->assertOk()->assertSee('ESTADO DE CUENTA')->assertSee('TOTAL QUE DEBE')->assertSee('L 100.00')->assertSee('size: '.$size, false);
    $response->assertSee('data:image/png;base64,', false);
    $pdf = Pdf::loadHTML($response->getContent());
    $pdf->render();
    assertPrintedLogo($pdf, 'ESTADO DE CUENTA');
    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1);
    expect($pdf->getDomPDF()->getCanvas()->get_width())->toEqual(612)
        ->and($pdf->getDomPDF()->getCanvas()->get_height())->toEqual($paper === 'half-letter' ? 396 : 792);
})->with([['letter', '8.5in 11in'], ['half-letter', '8.5in 5.5in']]);

/** Inspecciona el PDF sin comprimir: texto UTF-16BE de CPDF e imagen incrustada. */
function assertPrintedLogo(\Barryvdh\DomPDF\PDF $pdf, string $visibleText): void
{
    $bytes = $pdf->output(['compress' => false]);
    $text = str_replace("\0", '', $bytes);
    expect($bytes)->toStartWith('%PDF');
    expect($text)->toContain($visibleText)->not->toContain('Image dimensions')->not->toContain('exceed');
    expect($bytes)->toContain('/Subtype /Image');
}
