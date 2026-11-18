<?php

namespace Database\Seeders;

use App\Models\DocumentSeries;
use Illuminate\Database\Seeder;

class DocumentSeriesSeeder extends Seeder
{
    /**
     * Dos series (§6):
     *  - OP  (system): orden de pedido del sistema. El número inicial es configurable
     *        (OP_START_NUMBER); el placeholder 100001 no choca con los talonarios (≈012954).
     *  - TAL (paper_book): contingencia. Si se vende en el talonario de papel por un corte
     *        de luz, después se registra en el sistema con el número del papel.
     */
    public function run(): void
    {
        $opStart = (int) env('OP_START_NUMBER', 100001);

        DocumentSeries::query()->firstOrCreate(['code' => 'OP'], [
            'title' => 'ORDEN DE PEDIDO',
            'kind' => DocumentSeries::KIND_ORDER,
            'origin' => DocumentSeries::ORIGIN_SYSTEM,
            'next_number' => $opStart,
            'number_from' => $opStart,
            'number_to' => null,
            'pad_length' => 6,
            'status' => 'active',
            'notes' => 'Serie del sistema para órdenes de pedido en papel bond.',
        ]);

        DocumentSeries::query()->firstOrCreate(['code' => 'TAL'], [
            'title' => 'ORDEN DE PEDIDO',
            'kind' => DocumentSeries::KIND_ORDER,
            'origin' => DocumentSeries::ORIGIN_PAPER_BOOK,
            'next_number' => 1,
            'number_from' => 1,
            'number_to' => null,
            'pad_length' => 6,
            'status' => 'active',
            'notes' => 'Serie de contingencia: ventas registradas con el número del talonario de papel.',
        ]);
    }
}
