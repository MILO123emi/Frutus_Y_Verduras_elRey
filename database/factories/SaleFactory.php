<?php

namespace Database\Factories;

use App\Models\DocumentSeries;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Sale>
 *
 * Factory de ventas para pruebas. La numeración se asigna con un contador estático
 * para no chocar con la restricción unique(document_series_id, number). Para volumen
 * de rendimiento (~20k) usar `PerformanceSeeder`, que inserta por lotes.
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    private static int $counter = 0;

    public function definition(): array
    {
        $seriesId = DocumentSeries::query()->where('code', 'OP')->value('id')
            ?? DocumentSeries::factory()->create(['code' => 'OP'])->id;

        $number = 900000 + (++self::$counter);
        $issuedAt = Carbon::now()->subDays(fake()->numberBetween(0, 365));
        $subtotal = (string) fake()->numberBetween(50, 5000);

        return [
            'document_series_id' => $seriesId,
            'number' => $number,
            'full_number' => 'OP-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'issued_at' => $issuedAt,
            'issue_date' => $issuedAt->toDateString(),
            'customer_id' => null,
            'customer_name_snapshot' => 'CONSUMIDOR FINAL',
            'customer_rtn_snapshot' => null,
            'issuer_snapshot' => ['trade_name' => 'Frutas y Verduras "El Rey"'],
            'terms' => Sale::TERMS_CASH,
            'due_date' => null,
            'subtotal_exempt' => Money::round($subtotal),
            'subtotal_exonerated' => '0',
            'subtotal_g15' => '0',
            'subtotal_g18' => '0',
            'tax_15' => '0',
            'tax_18' => '0',
            'total' => Money::round($subtotal),
            'status' => Sale::STATUS_ISSUED,
            'print_count' => 0,
            'user_id' => User::factory(),
            'notes' => null,
        ];
    }

    /** Estado anulado (§3.8): no debe sumar en totales ni métricas. */
    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => Sale::STATUS_VOIDED,
            'void_reason' => 'Prueba de anulación',
            'voided_at' => Carbon::now(),
        ]);
    }
}
