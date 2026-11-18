<?php

namespace App\Services;

use App\Exceptions\SeriesUnavailableException;
use App\Models\AuditLog;
use App\Models\BusinessSettings;
use App\Models\Customer;
use App\Models\DocumentSeries;
use App\Models\Sale;
use App\Models\User;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Emisión y anulación de ventas (§7.0-0A). Todo ocurre en una sola transacción,
 * con snapshots del emisor, cliente y cada línea (§3.4) y bitácora de auditoría.
 */
class SaleService
{
    /** Tasas de ISV por tipo de impuesto (§3.7). */
    /** Desde este total la factura exige nombre e identificación del comprador (Art. 11). */
    public const INVOICE_ID_THRESHOLD = '10000.00';

    private const TAX_RATES = [
        'g15' => '0.15',
        'g18' => '0.18',
    ];

    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly DocumentPdfService $pdfs,
    ) {
    }

    /**
     * Emite una venta y devuelve el modelo con sus líneas cargadas.
     *
     * @param  array{
     *     series?: DocumentSeries,
     *     customer_id?: int|null,
     *     customer_name?: string|null,
     *     customer_rtn?: string|null,
     *     terms?: string,
     *     due_date?: mixed,
     *     issued_at?: mixed,
     *     notes?: string|null,
     *     lines: array<int, array{product_id?: int|null, description: string, unit?: string|null, quantity: string|int|float, unit_price: string|int|float, tax_type?: string}>
     * }  $data
     */
    public function issue(array $data, User $user): Sale
    {
        $lines = $data['lines'] ?? [];
        if ($lines === []) {
            throw new InvalidArgumentException('Una venta debe tener al menos una línea.');
        }

        return DB::transaction(function () use ($data, $lines, $user) {
            $isInvoice = ($data['kind'] ?? 'order') === 'invoice'
                || (($data['series'] ?? null)?->isInvoice() ?? false);
            $series = $data['series'] ?? ($isInvoice ? $this->defaultInvoiceSeries() : $this->defaultOrderSeries());
            // Comprador exonerado (solo factura): lo gravado pasa a "exonerado", sin ISV.
            $exonerated = $isInvoice && ! empty($data['exonerated_record']);

            // Snapshots del cliente (§3.4).
            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::query()->findOrFail($data['customer_id']);
            }
            $customerName = ($data['customer_name'] ?? null)
                ?: $customer?->name
                ?: 'CONSUMIDOR FINAL';
            $customerRtn = $data['customer_rtn'] ?? $customer?->rtn;
            $terms = $data['terms'] ?? $customer?->default_terms ?? Sale::TERMS_CASH;

            // Cálculo de líneas y subtotales por tipo de impuesto (§3.6, §3.7).
            $subtotals = ['exempt' => '0', 'g15' => '0', 'g18' => '0', 'exonerated' => '0'];
            $preparedLines = [];
            foreach (array_values($lines) as $i => $line) {
                $taxType = $line['tax_type'] ?? 'exempt';
                if (! in_array($taxType, ['exempt', 'g15', 'g18'], true)) {
                    throw new InvalidArgumentException("Tipo de impuesto inválido: {$taxType}.");
                }
                $quantity = (string) $line['quantity'];
                $unitPrice = (string) $line['unit_price'];
                $lineTotal = Money::lineTotal($quantity, $unitPrice);
                $bucket = ($exonerated && $taxType !== 'exempt') ? 'exonerated' : $taxType;
                $subtotals[$bucket] = Money::sum($subtotals[$bucket], $lineTotal);

                $preparedLines[] = [
                    'position' => $i + 1,
                    'product_id' => $line['product_id'] ?? null,
                    'description_snapshot' => $line['description'],
                    'unit_snapshot' => $line['unit'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'tax_type_snapshot' => $taxType,
                ];
            }

            $tax15 = Money::tax($subtotals['g15'], self::TAX_RATES['g15']);
            $tax18 = Money::tax($subtotals['g18'], self::TAX_RATES['g18']);
            $total = Money::sum(
                $subtotals['exempt'], $subtotals['exonerated'], $subtotals['g15'], $subtotals['g18'], $tax15, $tax18
            );

            $issuer = $this->issuerSnapshot();
            $fiscal = null;
            if ($isInvoice) {
                $this->assertInvoiceRequirements($issuer, $customerName, $customerRtn, $total);
                $fiscal = [
                    'title' => 'FACTURA',
                    'cai' => $series->cai,
                    'range' => $series->rangeLabel(),
                    'issue_deadline' => $series->issue_deadline?->toDateString(),
                    'exempt_purchase_order' => $data['exempt_purchase_order'] ?? null,
                    'exonerated_record' => $data['exonerated_record'] ?? null,
                    'sag_record' => $data['sag_record'] ?? null,
                ];
            }

            // Numeración atómica (§3.2).
            $assigned = $this->numbers->assign($series);

            $issuedAt = (isset($data['issued_at']) ? Carbon::parse($data['issued_at']) : Carbon::now())
                ->timezone(DateRange::TZ);

            $sale = Sale::create([
                'document_series_id' => $series->id,
                'number' => $assigned['number'],
                'full_number' => $assigned['full_number'],
                'issued_at' => $issuedAt,
                'issue_date' => $issuedAt->toDateString(),
                'customer_id' => $customer?->id,
                'customer_name_snapshot' => $customerName,
                'customer_rtn_snapshot' => $customerRtn,
                'issuer_snapshot' => $issuer,
                'fiscal_snapshot' => $fiscal,
                'source_sale_id' => $data['source_sale_id'] ?? null,
                'terms' => $terms,
                'due_date' => $data['due_date'] ?? null,
                'subtotal_exempt' => $subtotals['exempt'],
                'subtotal_exonerated' => $subtotals['exonerated'],
                'subtotal_g15' => $subtotals['g15'],
                'subtotal_g18' => $subtotals['g18'],
                'tax_15' => $tax15,
                'tax_18' => $tax18,
                'total' => $total,
                'status' => Sale::STATUS_ISSUED,
                'print_count' => 0,
                'user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->items()->createMany($preparedLines);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => $isInvoice ? 'invoice.issued' : 'sale.issued',
                'auditable_type' => Sale::class,
                'auditable_id' => $sale->id,
                'data' => [
                    'full_number' => $sale->full_number,
                    'total' => $total,
                    'terms' => $terms,
                    'lines' => count($preparedLines),
                ],
            ]);

            return $sale->load('items');
        });
    }

    /**
     * Anula una venta emitida. Solo admin; el motivo es obligatorio (§7.0-0B).
     * Al anular se borra la caché de PDFs de la venta; se regenera con la marca
     * "ANULADA" la próxima vez que se descargue (§7.2).
     */
    public function void(Sale $sale, string $reason, User $user): Sale
    {
        if (! $user->isAdmin()) {
            throw new SeriesUnavailableException('Solo un administrador puede anular ventas.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('El motivo de anulación es obligatorio.');
        }
        if ($sale->isVoided()) {
            throw new InvalidArgumentException('La venta ya está anulada.');
        }
        if ($sale->status === Sale::STATUS_CONVERTED) {
            throw new InvalidArgumentException('Esta orden ya se facturó: anule la factura si hace falta.');
        }

        $sale = DB::transaction(function () use ($sale, $reason, $user) {
            $sale->status = Sale::STATUS_VOIDED;
            $sale->void_reason = $reason;
            $sale->voided_at = Carbon::now();
            $sale->voided_by = $user->id;
            $sale->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'sale.voided',
                'auditable_type' => Sale::class,
                'auditable_id' => $sale->id,
                'data' => [
                    'full_number' => $sale->full_number,
                    'reason' => $reason,
                ],
            ]);

            return $sale;
        });

        // Fuera de la transacción: la caché es derivada y se regenera sola (§7.2).
        $this->pdfs->forget($sale);

        return $sale;
    }

    /**
     * Convierte una orden de pedido en factura fiscal. La factura copia líneas, cliente y
     * condición; la orden queda "Facturada" y deja de contar en totales y deudas.
     *
     * @param  array{customer_rtn?:string|null, customer_name?:string|null, exempt_purchase_order?:string|null, exonerated_record?:string|null, sag_record?:string|null}  $extra
     */
    public function convertToInvoice(Sale $order, User $user, array $extra = []): Sale
    {
        return DB::transaction(function () use ($order, $user, $extra) {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $locked->load('items');

            if ($locked->isInvoice()) {
                throw new InvalidArgumentException('Este documento ya es una factura.');
            }
            if ($locked->status !== Sale::STATUS_ISSUED) {
                throw new InvalidArgumentException('Solo se puede facturar una orden activa (no anulada ni ya facturada).');
            }

            $invoice = $this->issue([
                'kind' => 'invoice',
                'customer_id' => $locked->customer_id,
                'customer_name' => ($extra['customer_name'] ?? null) ?: $locked->customer_name_snapshot,
                'customer_rtn' => ($extra['customer_rtn'] ?? null) ?: $locked->customer_rtn_snapshot,
                'terms' => $locked->terms,
                'due_date' => $locked->due_date,
                'notes' => trim(($locked->notes ? $locked->notes.' · ' : '').'Según orden '.$locked->full_number),
                'source_sale_id' => $locked->id,
                'exempt_purchase_order' => $extra['exempt_purchase_order'] ?? null,
                'exonerated_record' => $extra['exonerated_record'] ?? null,
                'sag_record' => $extra['sag_record'] ?? null,
                'lines' => $locked->items->sortBy('position')->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'description' => $i->description_snapshot,
                    'unit' => $i->unit_snapshot,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'tax_type' => $i->tax_type_snapshot,
                ])->values()->all(),
            ], $user);

            $locked->status = Sale::STATUS_CONVERTED;
            $locked->converted_to_sale_id = $invoice->id;
            $locked->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'sale.converted',
                'auditable_type' => Sale::class,
                'auditable_id' => $locked->id,
                'data' => ['order' => $locked->full_number, 'invoice' => $invoice->full_number],
            ]);

            return $invoice;
        });
    }

    /** @param array<string, mixed> $issuer */
    private function assertInvoiceRequirements(array $issuer, string $customerName, ?string $customerRtn, string $total): void
    {
        if (empty($issuer['legal_name']) || empty($issuer['rtn'])) {
            throw new SeriesUnavailableException(
                'Para emitir facturas falta la razón social o el RTN del negocio. Complételos en Ajustes.'
            );
        }
        if (bccomp($total, self::INVOICE_ID_THRESHOLD, 2) >= 0
            && (mb_strtoupper(trim($customerName)) === 'CONSUMIDOR FINAL' || trim((string) $customerRtn) === '')) {
            throw new InvalidArgumentException(
                'Las facturas de L 10,000.00 o más necesitan el nombre del cliente y su RTN o identidad.'
            );
        }
    }

    private function defaultInvoiceSeries(): DocumentSeries
    {
        $series = DocumentSeries::usableInvoiceSeries();
        if ($series === null) {
            throw new SeriesUnavailableException(
                'No hay un CAI vigente para facturar (se agotó o venció). Registre uno nuevo en Ajustes → Facturación.'
            );
        }

        return $series;
    }

    /** Serie de orden de pedido del sistema, activa, por defecto. */
    private function defaultOrderSeries(): DocumentSeries
    {
        $series = DocumentSeries::query()
            ->where('kind', DocumentSeries::KIND_ORDER)
            ->where('origin', DocumentSeries::ORIGIN_SYSTEM)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($series === null) {
            throw new SeriesUnavailableException('No hay una serie de orden de pedido activa.');
        }

        return $series;
    }

    /** @return array<string, mixed> */
    private function issuerSnapshot(): array
    {
        $settings = BusinessSettings::current();

        return [
            'trade_name' => $settings?->trade_name,
            'legal_name' => $settings?->legal_name,
            'activity' => $settings?->activity,
            'rtn' => $settings?->rtn,
            'address' => $settings?->address,
            'phone' => $settings?->phone,
            'email' => $settings?->email,
        ];
    }
}
