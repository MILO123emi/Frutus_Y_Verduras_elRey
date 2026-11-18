<?php

namespace App\Services;

use App\Exceptions\SeriesUnavailableException;
use App\Models\DocumentSeries;
use Illuminate\Support\Facades\DB;

/**
 * Asigna el siguiente número de una serie de forma atómica (§3.2, §7.0-0A).
 *
 * La fila de la serie se bloquea con SELECT ... FOR UPDATE dentro de una
 * transacción, así dos emisiones en paralelo no pueden tomar el mismo número.
 * El bloqueo de fila en PostgreSQL se mantiene hasta el commit de la transacción
 * de nivel superior, por lo que es seguro llamar a esto dentro de SaleService::issue.
 * La restricción unique(document_series_id, number) es la última línea de defensa.
 */
class DocumentNumberService
{
    /**
     * @return array{number:int, full_number:string}
     */
    public function assign(DocumentSeries $series): array
    {
        return DB::transaction(function () use ($series) {
            /** @var DocumentSeries $locked */
            $locked = DocumentSeries::query()
                ->whereKey($series->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'active') {
                throw new SeriesUnavailableException(
                    "La serie {$locked->code} no está activa (estado: {$locked->status})."
                );
            }

            // Factura SAR: no se emite después de la fecha límite del CAI (§3.1 del prompt).
            if ($locked->isPastDeadline()) {
                throw new SeriesUnavailableException(
                    'El CAI de estas facturas venció el '.$locked->issue_deadline->format('d/m/Y')
                    .'. Registre un CAI nuevo en Ajustes → Facturación.'
                );
            }

            $number = (int) $locked->next_number;

            if ($locked->number_to !== null && $number > (int) $locked->number_to) {
                throw new SeriesUnavailableException(
                    $locked->isInvoice()
                        ? 'Se terminaron los números autorizados de este CAI. Registre un CAI nuevo en Ajustes → Facturación.'
                        : "La serie {$locked->code} está agotada (rango hasta {$locked->number_to})."
                );
            }

            $locked->next_number = $number + 1;
            if ($locked->number_to !== null && $locked->next_number > (int) $locked->number_to) {
                $locked->status = 'exhausted';
            }
            $locked->save();

            // Mantener en memoria el número reservado para quien tenga la instancia original.
            $series->next_number = $locked->next_number;
            $series->status = $locked->status;

            return [
                'number' => $number,
                'full_number' => $locked->formatNumber($number),
            ];
        });
    }
}
