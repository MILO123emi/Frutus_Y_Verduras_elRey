<?php

use App\Models\Sale;
use App\Services\DocumentPdfService;
use App\Services\SaleService;

beforeEach(function () {
    seedFoundations();
    // Caché aislada en un directorio temporal para no tocar la de desarrollo (§7.2).
    $this->cacheDir = sys_get_temp_dir().'/frutverdrey_pdf_'.uniqid();
    config(['documents.cache_path' => $this->cacheDir]);
});

afterEach(function () {
    if (is_dir($this->cacheDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cacheDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->cacheDir);
    }
});

function saleFor(?string $customerName = null): Sale
{
    return app(SaleService::class)->issue([
        'customer_name' => $customerName,
        'lines' => [['description' => 'Repollo', 'quantity' => '2', 'unit_price' => '50.00']],
    ], adminUser());
}

it('genera y cachea el PDF la primera vez y lo reutiliza después', function () {
    $pdfs = app(DocumentPdfService::class);
    $sale = saleFor('Juan Perez');

    $path = $pdfs->get($sale, 'original');

    expect(is_file($path))->toBeTrue()
        ->and(file_get_contents($path))->toStartWith('%PDF');

    // Marca el archivo; si se reutiliza la caché, no se sobrescribe.
    file_put_contents($path, 'SENTINELA');
    $again = $pdfs->get($sale, 'original');

    expect($again)->toBe($path)
        ->and(file_get_contents($path))->toBe('SENTINELA');
});

it('arma el nombre del archivo según la convención (§7.2)', function () {
    $pdfs = app(DocumentPdfService::class);

    $sale = saleFor('José Ñandú Pérez');
    expect($pdfs->filename($sale, 'original'))
        ->toBe("{$sale->full_number}_{$sale->issue_date->format('Y-m-d')}_Jose-Nandu-Perez_ORIGINAL.pdf");
    expect($pdfs->filename($sale, 'copy'))->toEndWith('_COPIA.pdf');

    $anon = saleFor(null);
    expect($pdfs->filename($anon, 'original'))->toContain('_Consumidor-Final_');
});

it('corta el nombre del cliente a 40 caracteres', function () {
    $pdfs = app(DocumentPdfService::class);
    $sale = saleFor(str_repeat('A', 60));

    $slug = explode('_', $pdfs->filename($sale, 'original'))[2];
    expect(mb_strlen($slug))->toBe(40);
});

it('incluye la variante en el contenido del documento', function () {
    $pdfs = app(DocumentPdfService::class);
    $sale = saleFor('Juan Perez');

    expect($pdfs->renderHtml($sale, 'original'))->toContain('ORIGINAL – CLIENTE')
        ->and($pdfs->renderHtml($sale, 'copy'))->toContain('COPIA – EMISOR');
});

it('la descarga responde con el PDF y el nombre correcto', function () {
    $pdfs = app(DocumentPdfService::class);
    $sale = saleFor('Juan Perez');
    $this->actingAs(adminUser());

    $response = $this->get(route('ventas.pdf', ['sale' => $sale->id, 'variant' => 'original']));

    $response->assertOk()
        ->assertDownload($pdfs->filename($sale, 'original'));
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('anular borra la caché y la regenera con la marca ANULADA', function () {
    $pdfs = app(DocumentPdfService::class);
    $sale = saleFor('Juan Perez');

    $path = $pdfs->get($sale, 'original');
    expect(is_file($path))->toBeTrue();

    app(SaleService::class)->void($sale, 'Pedido cancelado', adminUser());

    // La caché de la venta quedó invalidada (§7.2).
    expect(is_file($path))->toBeFalse();

    // Al regenerarse, el documento ya lleva la marca de agua "ANULADA".
    $voided = $sale->fresh();
    expect($pdfs->filename($voided, 'original'))->toContain('_ANULADA_')
        ->and($pdfs->renderHtml($voided, 'original'))->toContain('ANULADA');

    $pdfs->get($voided, 'original');
    expect(is_file($path))->toBeTrue();
});

it('separa la caché por papel e invalida ambos formatos al anular', function () {
    $pdfs = app(DocumentPdfService::class);
    $sale = saleFor('Juan Perez');
    $paths = [];
    foreach (['letter', 'half-letter'] as $paper) {
        \App\Models\BusinessSettings::current()->update(['print_settings' => ['paper_size' => $paper]]);
        foreach (DocumentPdfService::VARIANTS as $variant) {
            $path = $pdfs->get($sale, $variant);
            expect($path)->toContain('/print-v3/'.$paper.'/')->toEndWith('_'.$variant.'.pdf');
            expect(is_file($path))->toBeTrue();
            $paths[] = $path;
        }
    }
    // La caché anterior se conserva, pero nunca se sirve desde print-v3.
    $legacy = str_replace('/print-v3/', '/print-v2/', $paths[0]);
    mkdir(dirname($legacy), 0775, true);
    file_put_contents($legacy, 'CACHE ANTIGUA');
    expect(count(array_unique($paths)))->toBe(4);
    app(SaleService::class)->void($sale, 'Error', adminUser());
    foreach ($paths as $path) {
        expect(is_file($path))->toBeFalse();
    }
    expect(file_get_contents($legacy))->toBe('CACHE ANTIGUA');
});
