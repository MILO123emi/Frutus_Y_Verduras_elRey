<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DocumentSeries;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Genera ~20,000 ventas de prueba realistas para medir rendimiento (§6).
 * Inserta por lotes (sin pasar por SaleService) para que sea rápido; la numeración
 * es correlativa desde el `next_number` de la serie OP y al final la actualiza.
 *
 * Uso: php artisan db:seed --class=PerformanceSeeder   (cantidad: env PERF_SALES)
 */
class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        @set_time_limit(0);

        $count = (int) env('PERF_SALES', 20000);
        $series = DocumentSeries::query()->where('code', 'OP')->firstOrFail();
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();

        $products = Product::query()->get(['id', 'name', 'tax_type'])->all();
        $units = Unit::query()->pluck('name')->all() ?: ['Unidad'];
        $customerIds = Customer::query()->pluck('id')->all();
        $issuerSnapshot = json_encode([
            'trade_name' => 'Frutas y Verduras "El Rey"',
            'rtn' => '01041959002984',
        ]);

        $number = (int) $series->next_number;
        $startNumber = $number;
        $chunkSize = 1000;
        $generated = 0;

        $this->command?->info("Generando {$count} ventas desde el número {$startNumber}...");

        while ($generated < $count) {
            $batch = min($chunkSize, $count - $generated);
            $salesRows = [];
            $itemsByNumber = [];

            for ($i = 0; $i < $batch; $i++) {
                $issuedAt = Carbon::now()
                    ->subDays(random_int(0, 364))
                    ->setTime(random_int(6, 18), random_int(0, 59));

                $customerId = (! empty($customerIds) && random_int(1, 100) <= 70)
                    ? $customerIds[array_rand($customerIds)]
                    : null;

                // 1 a 8 líneas por venta.
                $lineCount = random_int(1, 8);
                $subtotal = '0';
                $lines = [];
                for ($p = 0; $p < $lineCount; $p++) {
                    $product = $products[array_rand($products)];
                    $quantity = (string) random_int(1, 25);
                    $unitPrice = number_format(random_int(500, 40000) / 100, 2, '.', '');
                    $lineTotal = Money::lineTotal($quantity, $unitPrice);
                    $subtotal = Money::sum($subtotal, $lineTotal);

                    $lines[] = [
                        'position' => $p + 1,
                        'product_id' => $product->id,
                        'description_snapshot' => $product->name,
                        'unit_snapshot' => $units[array_rand($units)],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'tax_type_snapshot' => 'exempt',
                    ];
                }

                $voided = random_int(1, 100) <= 2; // ~2% anuladas
                $terms = random_int(1, 100) <= 60 ? 'cash' : 'credit';

                $salesRows[] = [
                    'document_series_id' => $series->id,
                    'number' => $number,
                    'full_number' => $series->formatNumber($number),
                    'issued_at' => $issuedAt,
                    'issue_date' => $issuedAt->toDateString(),
                    'customer_id' => $customerId,
                    'customer_name_snapshot' => $customerId
                        ? 'Cliente '.$customerId
                        : 'CONSUMIDOR FINAL',
                    'customer_rtn_snapshot' => null,
                    'issuer_snapshot' => $issuerSnapshot,
                    'terms' => $terms,
                    'due_date' => null,
                    'subtotal_exempt' => $subtotal,
                    'subtotal_exonerated' => '0',
                    'subtotal_g15' => '0',
                    'subtotal_g18' => '0',
                    'tax_15' => '0',
                    'tax_18' => '0',
                    'total' => $subtotal,
                    'status' => $voided ? 'voided' : 'issued',
                    'void_reason' => $voided ? 'Error de digitación' : null,
                    'voided_at' => $voided ? $issuedAt->copy()->addHours(1) : null,
                    'voided_by' => $voided ? $admin->id : null,
                    'print_count' => random_int(0, 2),
                    'user_id' => $admin->id,
                    'notes' => null,
                    'created_at' => $issuedAt,
                    'updated_at' => $issuedAt,
                ];
                $itemsByNumber[$number] = $lines;
                $number++;
            }

            DB::transaction(function () use ($salesRows, $itemsByNumber, $series) {
                DB::table('sales')->insert($salesRows);

                // Recuperar ids recién insertados por rango de número para enlazar las líneas.
                $firstNumber = array_key_first($itemsByNumber);
                $lastNumber = array_key_last($itemsByNumber);
                $idByNumber = DB::table('sales')
                    ->where('document_series_id', $series->id)
                    ->whereBetween('number', [$firstNumber, $lastNumber])
                    ->pluck('id', 'number');

                $itemRows = [];
                $now = Carbon::now();
                foreach ($itemsByNumber as $num => $lines) {
                    $saleId = $idByNumber[$num];
                    foreach ($lines as $line) {
                        $itemRows[] = $line + [
                            'sale_id' => $saleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                foreach (array_chunk($itemRows, 2000) as $itemChunk) {
                    DB::table('sale_items')->insert($itemChunk);
                }
            });

            $generated += $batch;
            $this->command?->info("  {$generated} / {$count}");
        }

        $series->next_number = $number;
        $series->save();

        $this->command?->info("Listo. Ventas {$startNumber}..".($number - 1).'.');
    }
}
