<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Services\DocumentPdfService;
use App\Support\DateRange;
use Illuminate\Console\Command;

/**
 * Pre-genera ("calienta") la caché de PDFs de las ventas en un rango de fechas (§7.2).
 * En la Parte 6 se programa de noche; aquí solo existe el comando.
 */
class WarmPdfs extends Command
{
    protected $signature = 'documents:warm-pdfs {--from= : Fecha inicio YYYY-MM-DD} {--to= : Fecha fin YYYY-MM-DD}';

    protected $description = 'Pre-genera la caché de PDFs de las ventas en un rango de fechas (ambas variantes).';

    public function handle(DocumentPdfService $pdfs): int
    {
        $range = DateRange::make('custom', $this->option('from'), $this->option('to'));

        $query = Sale::query()
            ->whereBetween('issue_date', [$range->fromDate(), $range->toDate()])
            ->orderBy('issue_date')->orderBy('number');

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info("No hay ventas entre {$range->fromDate()} y {$range->toDate()}.");

            return self::SUCCESS;
        }

        $this->info("Calentando PDFs de {$count} ventas ({$range->fromDate()} a {$range->toDate()})…");
        $bar = $this->output->createProgressBar($count);

        $query->chunkById(200, function ($sales) use ($pdfs, $bar) {
            foreach ($sales as $sale) {
                foreach (DocumentPdfService::VARIANTS as $variant) {
                    $pdfs->get($sale, $variant);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Listo: {$count} ventas × 2 variantes en caché.");

        return self::SUCCESS;
    }
}
