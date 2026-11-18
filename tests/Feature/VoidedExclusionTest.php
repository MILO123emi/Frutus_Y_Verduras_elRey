<?php

use App\Models\Sale;

beforeEach(fn () => seedFoundations());

// Las anuladas se excluyen de todos los totales y métricas (§3.8).

it('excluye las ventas anuladas de los totales', function () {
    Sale::factory()->count(3)->create(['total' => '100.00']);
    Sale::factory()->count(2)->voided()->create(['total' => '500.00']);

    expect(Sale::count())->toBe(5)                              // todas aparecen en listados
        ->and(Sale::issued()->count())->toBe(3)
        ->and(Sale::voided()->count())->toBe(2)
        ->and((float) Sale::issued()->sum('total'))->toBe(300.0) // solo activas suman
        ->and((float) Sale::sum('total'))->toBe(1300.0);         // el total crudo sí las incluye
});
