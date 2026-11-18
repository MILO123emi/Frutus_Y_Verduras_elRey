<?php

namespace App\Services;

use App\Models\AccountMovement;
use App\Models\Sale;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipStream\ZipStream;

/**
 * Reportes del período para el abuelo y el contador. Las agregaciones van en SQL;
 * las anuladas y las órdenes ya facturadas no suman en ningún total (regla 8).
 */
class ReportService
{
    private const MONEY_FMT = '"L "#,##0.00';

    public function __construct(
        private readonly AccountsService $accounts,
        private readonly DocumentPdfService $pdfs,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(DateRange $range): array
    {
        $base = fn () => Sale::query()->whereBetween('issue_date', [$range->fromDate(), $range->toDate()]);

        $agg = $base()->issued()->selectRaw("
            COUNT(*) as count, COALESCE(SUM(total),0) as total,
            COALESCE(SUM(total) FILTER (WHERE terms = 'cash'),0) as cash,
            COALESCE(SUM(total) FILTER (WHERE terms = 'credit'),0) as credit,
            COUNT(*) FILTER (WHERE fiscal_snapshot IS NOT NULL) as invoices,
            COALESCE(SUM(total) FILTER (WHERE fiscal_snapshot IS NOT NULL),0) as invoices_total,
            COALESCE(SUM(tax_15 + tax_18),0) as tax")->first();

        $collected = AccountMovement::query()->active()->where('type', AccountMovement::TYPE_PAYMENT)
            ->whereBetween('movement_date', [$range->fromDate(), $range->toDate()])->sum('amount');

        $topProducts = DB::table('sale_items as i')->join('sales as s', 's.id', '=', 'i.sale_id')
            ->where('s.status', Sale::STATUS_ISSUED)
            ->whereBetween('s.issue_date', [$range->fromDate(), $range->toDate()])
            ->selectRaw("lower(unaccent(trim(i.description_snapshot))) as k, MIN(i.description_snapshot) as name,
                COALESCE(i.unit_snapshot, '') as unit, SUM(i.quantity) as qty, SUM(i.line_total) as amount")
            ->groupBy('k', 'unit')->get();

        // Agrupar por producto y listar cantidades por unidad (nunca sumar unidades distintas).
        $products = $topProducts->groupBy('k')->map(fn ($rows) => [
            'name' => $rows->first()->name,
            'amount' => Money::sum('0', ...$rows->pluck('amount')->map(fn ($a) => (string) $a)->all()),
            'quantities' => $rows->map(fn ($r) => rtrim(rtrim(number_format((float) $r->qty, 2, '.', ','), '0'), '.').' '.($r->unit ?: 'sin unidad'))->implode(' · '),
        ])->sortByDesc(fn ($p) => (float) $p['amount'])->take(10)->values();

        $topCustomers = $base()->issued()->whereNotNull('customer_id')
            ->selectRaw('customer_id, MIN(customer_name_snapshot) as name, COUNT(*) as count, SUM(total) as total')
            ->groupBy('customer_id')->orderByDesc('total')->limit(10)->get();

        return [
            'count' => (int) $agg->count,
            'total' => Money::sum((string) $agg->total),
            'cash' => Money::sum((string) $agg->cash),
            'credit' => Money::sum((string) $agg->credit),
            'invoices' => (int) $agg->invoices,
            'invoices_total' => Money::sum((string) $agg->invoices_total),
            'tax' => Money::sum((string) $agg->tax),
            'voided' => $base()->voided()->count(),
            'collected' => Money::sum((string) $collected),
            'receivable' => $this->accounts->totalReceivable(),
            'top_products' => $products,
            'top_customers' => $topCustomers->map(fn ($c) => ['name' => $c->name, 'count' => (int) $c->count, 'total' => Money::sum((string) $c->total)]),
        ];
    }

    public function baseName(DateRange $range): string
    {
        return "ElRey_Ventas_{$range->fromDate()}_a_{$range->toDate()}";
    }

    /** Escribe el Excel del período y devuelve la ruta del archivo temporal. */
    public function excel(DateRange $range): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('Frutas y Verduras El Rey')->setTitle('Reporte '.$this->baseName($range));

        $this->salesSheet($book->getActiveSheet(), $range);
        $this->ledgerSheet($book->createSheet(), $range);
        $this->dailySheet($book->createSheet(), $range);
        $this->paymentsSheet($book->createSheet(), $range);
        $this->balancesSheet($book->createSheet());
        $book->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'elrey').'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /** ZIP en streaming: Excel + un PDF por documento (copia del emisor), ordenados por número. */
    public function streamZip(DateRange $range, string $variant = 'copy'): void
    {
        $zip = new ZipStream(outputName: $this->baseName($range).'.zip', sendHttpHeaders: true, defaultEnableZeroHeader: true);
        $xlsx = $this->excel($range);
        $zip->addFileFromPath('00_RESUMEN_'.$range->fromDate().'_a_'.$range->toDate().'.xlsx', $xlsx);
        @unlink($xlsx);

        Sale::query()->whereBetween('issue_date', [$range->fromDate(), $range->toDate()])
            ->orderBy('document_series_id')->orderBy('number')
            ->chunk(200, function ($sales) use ($zip, $variant) {
                foreach ($sales as $sale) {
                    $suffix = match ($sale->status) {
                        Sale::STATUS_VOIDED => '_ANULADA',
                        Sale::STATUS_CONVERTED => '_FACTURADA',
                        default => '',
                    };
                    $name = $sale->full_number.'_'.$sale->issue_date->toDateString().'_'
                        .$this->slug($sale->customer_name_snapshot).$suffix.'.pdf';
                    $zip->addFileFromPath($name, $this->pdfs->get($sale, $variant));
                }
            });

        $zip->finish();
    }

    private function salesSheet(Worksheet $sheet, DateRange $range): void
    {
        $sheet->setTitle('Ventas');
        $headers = ['Número', 'Tipo', 'Fecha', 'Cliente', 'RTN', 'Condición', 'Exento', 'Exonerado', 'Gravado 15%', 'Gravado 18%', 'ISV', 'Total', 'Estado', 'Motivo / nota'];
        $sheet->fromArray($headers, null, 'A1');
        $row = 2;
        $totals = ['exempt' => '0', 'exo' => '0', 'g15' => '0', 'g18' => '0', 'tax' => '0', 'total' => '0'];
        $active = 0;
        $inactive = 0;

        Sale::query()->whereBetween('issue_date', [$range->fromDate(), $range->toDate()])
            ->orderBy('issue_date')->orderBy('document_series_id')->orderBy('number')
            ->chunk(500, function ($sales) use ($sheet, &$row, &$totals, &$active, &$inactive) {
                foreach ($sales as $s) {
                    $isActive = $s->status === Sale::STATUS_ISSUED;
                    $tax = Money::sum((string) $s->tax_15, (string) $s->tax_18);
                    $sheet->fromArray([
                        $s->full_number, $s->isInvoice() ? 'Factura' : 'Pedido',
                        \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($s->issue_date->toDateString()),
                        $s->customer_name_snapshot, $s->customer_rtn_snapshot, $s->terms === 'credit' ? 'Crédito' : 'Contado',
                        (float) $s->subtotal_exempt, (float) $s->subtotal_exonerated, (float) $s->subtotal_g15, (float) $s->subtotal_g18,
                        (float) $tax, (float) $s->total,
                        match ($s->status) { Sale::STATUS_VOIDED => 'ANULADA', Sale::STATUS_CONVERTED => 'FACTURADA', default => 'Activa' },
                        $s->status === Sale::STATUS_VOIDED ? $s->void_reason : ($s->status === Sale::STATUS_CONVERTED ? 'Reemplazada por factura (no suma)' : null),
                    ], null, "A{$row}");

                    if ($isActive) {
                        $active++;
                        $totals['exempt'] = Money::sum($totals['exempt'], (string) $s->subtotal_exempt);
                        $totals['exo'] = Money::sum($totals['exo'], (string) $s->subtotal_exonerated);
                        $totals['g15'] = Money::sum($totals['g15'], (string) $s->subtotal_g15);
                        $totals['g18'] = Money::sum($totals['g18'], (string) $s->subtotal_g18);
                        $totals['tax'] = Money::sum($totals['tax'], $tax);
                        $totals['total'] = Money::sum($totals['total'], (string) $s->total);
                    } else {
                        $inactive++;
                        $sheet->getStyle("A{$row}:N{$row}")->getFont()->getColor()->setRGB('8A8A8A');
                        $sheet->getStyle("A{$row}:N{$row}")->getFont()->setItalic(true);
                    }
                    $row++;
                }
            });

        $last = $row - 1;
        $row++;
        $sheet->fromArray(['TOTAL DEL PERÍODO (solo activas)', null, null, null, null, null,
            (float) $totals['exempt'], (float) $totals['exo'], (float) $totals['g15'], (float) $totals['g18'], (float) $totals['tax'], (float) $totals['total']], null, "A{$row}");
        $sheet->getStyle("A{$row}:N{$row}")->getFont()->setBold(true);
        $sheet->setCellValue('A'.($row + 1), "Documentos activos: {$active}");
        $sheet->setCellValue('A'.($row + 2), "Anulados o facturados (no suman): {$inactive}");

        $this->formatTable($sheet, 'N', $last, ['G', 'H', 'I', 'J', 'K', 'L'], 'C', $row);
    }

    /** Libro de ventas fiscal: solo facturas (incluidas anuladas, marcadas). */
    private function ledgerSheet(Worksheet $sheet, DateRange $range): void
    {
        $sheet->setTitle('Libro de ventas (facturas)');
        $sheet->fromArray(['Fecha', 'Número de factura', 'CAI', 'Cliente', 'RTN cliente', 'Exento', 'Exonerado', 'Gravado 15%', 'ISV 15%', 'Gravado 18%', 'ISV 18%', 'Total', 'Estado'], null, 'A1');
        $row = 2;
        Sale::query()->whereNotNull('fiscal_snapshot')
            ->whereBetween('issue_date', [$range->fromDate(), $range->toDate()])
            ->orderBy('number')->each(function (Sale $s) use ($sheet, &$row) {
                $voided = $s->status === Sale::STATUS_VOIDED;
                $z = fn ($v) => $voided ? 0.0 : (float) $v;
                $sheet->fromArray([
                    \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($s->issue_date->toDateString()),
                    $s->full_number, $s->fiscal_snapshot['cai'] ?? '', $s->customer_name_snapshot, $s->customer_rtn_snapshot,
                    $z($s->subtotal_exempt), $z($s->subtotal_exonerated), $z($s->subtotal_g15), $z($s->tax_15),
                    $z($s->subtotal_g18), $z($s->tax_18), $z($s->total), $voided ? 'ANULADA' : 'Válida',
                ], null, "A{$row}");
                if ($voided) {
                    $sheet->getStyle("A{$row}:M{$row}")->getFont()->getColor()->setRGB('8A8A8A');
                }
                $row++;
            });
        if ($row === 2) {
            $sheet->setCellValue('A2', 'No hay facturas en este período.');
        }
        $this->formatTable($sheet, 'M', max(2, $row - 1), ['F', 'G', 'H', 'I', 'J', 'K', 'L'], 'A');
    }

    private function dailySheet(Worksheet $sheet, DateRange $range): void
    {
        $sheet->setTitle('Por día');
        $sheet->fromArray(['Fecha', 'Ventas', 'Contado', 'Crédito', 'Total'], null, 'A1');
        $rows = Sale::query()->issued()->whereBetween('issue_date', [$range->fromDate(), $range->toDate()])
            ->selectRaw("issue_date, COUNT(*) as n, SUM(total) FILTER (WHERE terms='cash') as cash, SUM(total) FILTER (WHERE terms='credit') as credit, SUM(total) as total")
            ->groupBy('issue_date')->orderBy('issue_date')->get();
        $r = 2;
        foreach ($rows as $d) {
            $sheet->fromArray([\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($d->issue_date->toDateString()), (int) $d->n,
                (float) $d->cash, (float) $d->credit, (float) $d->total], null, "A{$r}");
            $r++;
        }
        $this->formatTable($sheet, 'E', max(2, $r - 1), ['C', 'D', 'E'], 'A');
    }

    private function paymentsSheet(Worksheet $sheet, DateRange $range): void
    {
        $sheet->setTitle('Abonos');
        $sheet->fromArray(['Fecha', 'Cliente', 'Forma de pago', 'Referencia', 'Monto', 'Estado'], null, 'A1');
        $r = 2;
        AccountMovement::query()->with('customer:id,name')->where('type', AccountMovement::TYPE_PAYMENT)
            ->whereBetween('movement_date', [$range->fromDate(), $range->toDate()])->orderBy('movement_date')
            ->each(function (AccountMovement $m) use ($sheet, &$r) {
                $sheet->fromArray([\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($m->movement_date->toDateString()),
                    $m->customer?->name, AccountsService::methodLabel($m->method), $m->reference, (float) $m->amount,
                    $m->status === 'active' ? 'Válido' : 'ANULADO (no suma)'], null, "A{$r}");
                $r++;
            });
        $this->formatTable($sheet, 'F', max(2, $r - 1), ['E'], 'A');
    }

    private function balancesSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Saldos al día de hoy');
        $sheet->fromArray(['Cliente', 'Teléfono', 'Debe'], null, 'A1');
        $r = 2;
        foreach ($this->accounts->balances() as $b) {
            $sheet->fromArray([$b->name, $b->phone, (float) $b->balance], null, "A{$r}");
            $r++;
        }
        $this->formatTable($sheet, 'C', max(2, $r - 1), ['C'], null);
    }

    /** Encabezado verde congelado, autofiltro, formatos de fecha/moneda y anchos. */
    private function formatTable(Worksheet $sheet, string $lastCol, int $lastRow, array $moneyCols, ?string $dateCol, ?int $extraRow = null): void
    {
        $head = $sheet->getStyle("A1:{$lastCol}1");
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E5B3A');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");
        $end = $extraRow ?? $lastRow;
        foreach ($moneyCols as $c) {
            $sheet->getStyle("{$c}2:{$c}{$end}")->getNumberFormat()->setFormatCode(self::MONEY_FMT);
        }
        if ($dateCol) {
            $sheet->getStyle("{$dateCol}2:{$dateCol}{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        }
        foreach (range('A', $lastCol) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function slug(?string $name): string
    {
        $slug = trim(preg_replace('/[^A-Za-z0-9-]+/', '', str_replace(' ', '-', Str::ascii((string) $name))), '-');

        return Str::limit($slug ?: 'Consumidor-Final', 40, '');
    }
}
