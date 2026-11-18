<?php

namespace App\Services;

use App\Models\BusinessSettings;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Generación y caché de los PDF de ventas (§7.2). La caché es DERIVADA: la fuente
 * de verdad es la BD con sus snapshots, así que se puede borrar y regenerar.
 * Ruta: storage/app/documents/print-v3/{paper}/{yyyy}/{mm}/{full_number}_{variant}.pdf
 */
class DocumentPdfService
{
    /** Variantes válidas (ORIGINAL – CLIENTE / COPIA – EMISOR). */
    public const VARIANTS = ['original', 'copy'];

    /** Ruta absoluta del PDF en caché para una venta y variante. */
    public function path(Sale $sale, string $variant, ?string $paper = null): string
    {
        $variant = $this->normalizeVariant($variant);
        $date = $sale->issue_date;

        return sprintf(
            '%s/print-v3/%s/%s/%s/%s_%s.pdf',
            rtrim((string) config('documents.cache_path'), '/'),
            $paper ?? $this->paper(),
            $date->format('Y'),
            $date->format('m'),
            $sale->full_number,
            $variant,
        );
    }

    /**
     * Devuelve la ruta del PDF, generándolo y cacheándolo la primera vez que se pide (§7.2).
     */
    public function get(Sale $sale, string $variant): string
    {
        $variant = $this->normalizeVariant($variant);
        $path = $this->path($sale, $variant);

        if (! is_file($path)) {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($path, $this->render($sale, $variant));
        }

        return $path;
    }

    /** Genera los bytes del PDF con la plantilla única. */
    public function render(Sale $sale, string $variant): string
    {
        return Pdf::loadHTML($this->renderHtml($sale, $this->normalizeVariant($variant)))->output();
    }

    /** HTML de la plantilla única para una sola variante (expuesto para pruebas). */
    public function renderHtml(Sale $sale, string $variant): string
    {
        $variant = $this->normalizeVariant($variant);
        $sale->loadMissing(['items' => fn ($q) => $q->orderBy('position')]);

        return view($sale->documentView(), [
            'sale' => $sale,
            'copies' => [$variant],
            'print_settings' => BusinessSettings::current()?->print_settings ?? [],
        ])->render();
    }

    /**
     * Nombre del archivo de descarga (§7.2), ej.
     * `OP-100123_2026-10-01_Juan-Perez_ORIGINAL.pdf` (o `_COPIA`);
     * si está anulada lleva `_ANULADA` antes de la variante.
     * El prefijo sale de la serie (via full_number), nunca está fijo como "FAC".
     */
    public function filename(Sale $sale, string $variant): string
    {
        $variant = $this->normalizeVariant($variant);
        $date = $sale->issue_date->format('Y-m-d');
        $customer = $this->customerSlug($sale->customer_name_snapshot);
        $variantLabel = $variant === 'copy' ? 'COPIA' : 'ORIGINAL';
        $voided = $sale->isVoided() ? '_ANULADA' : '';

        return "{$sale->full_number}_{$date}_{$customer}{$voided}_{$variantLabel}.pdf";
    }

    /** Borra la caché de PDFs de una venta (al anular, §7.2). Derivada: se regenera sola. */
    public function forget(Sale $sale): void
    {
        foreach (['letter', 'half-letter'] as $paper) {
            foreach (self::VARIANTS as $variant) {
                $path = $this->path($sale, $variant, $paper);
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * Cliente a ASCII, espacios a `-`, sin caracteres inválidos, cortado a 40.
     * Sin cliente (o "Consumidor final") queda `Consumidor-Final`.
     */
    private function customerSlug(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '' || mb_strtoupper($name) === 'CONSUMIDOR FINAL') {
            return 'Consumidor-Final';
        }

        $ascii = Str::ascii($name);
        $ascii = preg_replace('/\s+/', '-', $ascii);           // espacios -> guion
        $ascii = preg_replace('/[^A-Za-z0-9\-]/', '', $ascii); // quita inválidos
        $ascii = trim(preg_replace('/-+/', '-', $ascii), '-'); // colapsa guiones
        $ascii = mb_substr($ascii, 0, 40);

        return $ascii !== '' ? $ascii : 'Consumidor-Final';
    }

    private function paper(): string
    {
        return (BusinessSettings::current()?->print_settings['paper_size'] ?? 'letter') === 'half-letter'
            ? 'half-letter' : 'letter';
    }

    private function normalizeVariant(string $variant): string
    {
        return in_array($variant, self::VARIANTS, true) ? $variant : 'original';
    }
}
