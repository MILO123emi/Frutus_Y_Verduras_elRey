<?php

use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Freight;
use App\Models\FreightSchedule;
use App\Models\Product;
use App\Models\User;
use App\Services\AccountsService;
use App\Services\SaleService;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    seedFoundations();
    $customer = Customer::firstOrFail();
    $this->parameters = [
        'sale' => app(SaleService::class)->issue(['lines' => [line()]], adminUser())->id,
        'customer' => $customer->id,
        'movement' => app(AccountsService::class)->record($customer, AccountMovement::TYPE_PAYMENT, ['amount' => '10'], adminUser())->id,
        'product' => Product::firstOrFail()->id,
        'driver' => Driver::create(['name' => 'Motorista de prueba'])->id,
        'freight' => Freight::create(['trip_date' => '2026-11-17', 'destination' => 'La Ceiba', 'status' => 'scheduled'])->id,
        'schedule' => FreightSchedule::create(['weekday' => 3, 'destination' => 'SPS'])->id,
        'series' => opSeries()->id,
        'user' => adminUser()->id,
        'file' => 'elrey_2026-11-17_180000.dump',
    ];
});

it('el contador no puede escribir en ninguna ruta de operación o administración', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ACCOUNTANT]));
    $checked = 0;
    foreach (Route::getRoutes() as $route) {
        if (! in_array('auth', $route->gatherMiddleware(), true) || $route->getName() === 'logout') {
            continue;
        }
        foreach (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) as $method) {
            $parameters = array_intersect_key($this->parameters, array_flip($route->parameterNames()));
            $this->call($method, route($route->getName(), $parameters), [])->assertForbidden();
            $checked++;
        }
    }
    expect($checked)->toBeGreaterThanOrEqual(20);
});

it('el vendedor no entra a ninguna ruta de administración', function () {
    $this->actingAs(sellerUser());
    foreach (Route::getRoutes() as $route) {
        if (! in_array('role:admin', $route->gatherMiddleware(), true)) {
            continue;
        }
        $parameters = array_intersect_key($this->parameters, array_flip($route->parameterNames()));
        $this->call($route->methods()[0], route($route->getName(), $parameters), [])->assertForbidden();
    }
});

it('un invitado es enviado al login en todas las rutas privadas', function () {
    foreach (Route::getRoutes() as $route) {
        if (! in_array('auth', $route->gatherMiddleware(), true)) {
            continue;
        }
        $parameters = array_intersect_key($this->parameters, array_flip($route->parameterNames()));
        $this->call($route->methods()[0], route($route->getName(), $parameters), [])->assertRedirect(route('login'));
    }
});

it('el contador puede consultar las pantallas y documentos de lectura', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ACCOUNTANT]));
    foreach (['home', 'ventas.index', 'ventas.show', 'ventas.documento', 'clientes.index', 'clientes.show', 'clientes.statement', 'cobros.index', 'reportes.index'] as $name) {
        $route = Route::getRoutes()->getByName($name);
        $parameters = array_intersect_key($this->parameters, array_flip($route->parameterNames()));
        $this->get(route($name, $parameters))->assertOk();
    }
});
