<?php

namespace App\Services;

use App\Models\Freight;
use App\Models\FreightSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Fletes: genera los viajes fijos (plantillas semanales) dentro de un rango de fechas.
 * Es idempotente gracias a unique(freight_schedule_id, trip_date): llamar dos veces no duplica,
 * y un viaje cancelado o editado no se vuelve a crear ni se pisa.
 */
class FreightService
{
    /** Crea los viajes fijos que falten entre $from y $to (inclusive). Devuelve cuántos creó. */
    public function materialize(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $schedules = FreightSchedule::query()->where('active', true)->get();
        if ($schedules->isEmpty()) {
            return 0;
        }

        $rows = [];
        $now = now();
        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            foreach ($schedules as $s) {
                // No inventa viajes en semanas anteriores a la creación de la plantilla.
                if ($s->weekday !== $day->isoWeekday() || $day->toDateString() < $s->created_at->toDateString()) {
                    continue;
                }
                $rows[] = [
                    'trip_date' => $day->toDateString(),
                    'freight_schedule_id' => $s->id,
                    'driver_id' => $s->driver_id,
                    'destination' => $s->destination,
                    'purpose' => 'business',
                    'vehicle' => $s->vehicle,
                    'departure_time' => $s->departure_time,
                    'notes' => $s->notes,
                    'status' => 'scheduled',
                    'customized' => false,
                    'charge_to_account' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows === [] ? 0 : DB::table('freights')->insertOrIgnore($rows);
    }

    /**
     * Al cambiar o desactivar una plantilla: quita los viajes FUTUROS que salieron de ella y
     * que nadie tocó (sin editar, sin cobro, sin completar), para regenerarlos con los datos nuevos.
     */
    public function resetFuture(FreightSchedule $schedule, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now('America/Tegucigalpa')->startOfDay();

        return Freight::query()
            ->where('freight_schedule_id', $schedule->id)
            ->where('trip_date', '>', $today->toDateString())
            ->where('customized', false)
            ->where('status', 'scheduled')
            ->whereNull('charge_amount')
            ->delete();
    }
}
