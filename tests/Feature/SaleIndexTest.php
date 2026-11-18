<?php

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedFoundations();
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-14 09:00:00', 'America/Tegucigalpa'));

    // Mes actual (octubre): 2 activas + 1 anulada.
    Sale::factory()->create(['issue_date' => '2026-10-05', 'total' => '100.00']);
    Sale::factory()->create([
        'issue_date' => '2026-10-10', 'total' => '200.00',
        'customer_name_snapshot' => 'María Pérez',
    ]);
    Sale::factory()->voided()->create(['issue_date' => '2026-10-12', 'total' => '999.00']);

    // Mes anterior (septiembre): 1 activa.
    Sale::factory()->create(['issue_date' => '2026-09-20', 'total' => '500.00']);
});

afterEach(fn () => Carbon::setTestNow());

it('por defecto muestra este mes y el total solo suma las activas', function () {
    $this->actingAs(adminUser())
        ->get(route('ventas.index'))
        ->assertInertia(fn (Assert $p) => $p
            ->component('Ventas/Index')
            ->where('aggregates.count', 3)
            ->where('aggregates.active_count', 2)
            ->where('aggregates.voided_count', 1)
            ->where('aggregates.active_total', '300.00')
        );
});

it('filtra por estado anuladas', function () {
    $this->actingAs(adminUser())
        ->get(route('ventas.index', ['estado' => 'anuladas']))
        ->assertInertia(fn (Assert $p) => $p->where('sales.total', 1));
});

it('el rango custom es inclusivo en los bordes', function () {
    $this->actingAs(adminUser())
        ->get(route('ventas.index', ['range' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertInertia(fn (Assert $p) => $p
            ->where('aggregates.count', 1)
            ->where('aggregates.active_total', '500.00')
        );
});

it('busca por número aceptando con y sin prefijo OP', function () {
    $sale = Sale::query()->where('issue_date', '2026-10-05')->first();
    $digits = (string) $sale->number;

    foreach ([$digits, 'OP-'.$digits, $sale->full_number] as $term) {
        $this->actingAs(adminUser())
            ->get(route('ventas.index', ['numero' => $term]))
            ->assertInertia(fn (Assert $p) => $p->where('sales.total', 1));
    }
});

it('la búsqueda rápida de cliente ignora acentos', function () {
    $this->actingAs(adminUser())
        ->get(route('ventas.index', ['q' => 'maria perez']))
        ->assertInertia(fn (Assert $p) => $p
            ->where('sales.total', 1)
            ->where('sales.data.0.customer', 'María Pérez')
        );
});

it('respeta la paginación del servidor', function () {
    Sale::factory()->count(30)->create(['issue_date' => '2026-10-08']);

    $this->actingAs(adminUser())
        ->get(route('ventas.index', ['per_page' => 25]))
        ->assertInertia(fn (Assert $p) => $p
            ->where('sales.per_page', 25)
            ->count('sales.data', 25)
            ->where('sales.total', 33) // 3 del beforeEach (octubre) + 30
        );
});
