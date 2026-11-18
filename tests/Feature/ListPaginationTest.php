<?php

use App\Models\Customer;
use App\Services\AccountsService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => seedFoundations());

it('pagina clientes y busca por nombre sin acentos sin perder el filtro', function () {
    for ($i = 1; $i <= 30; $i++) {
        Customer::create(['name' => sprintf('José %02d', $i), 'active' => true, 'default_terms' => 'cash']);
    }
    Customer::create(['name' => 'José inactivo', 'active' => false, 'default_terms' => 'cash']);
    $this->actingAs(adminUser())->get(route('clientes.index', ['q' => 'jose', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('Clientes/Index')
            ->where('customers.total', 30)->where('customers.current_page', 2)->has('customers.data', 5)
            ->where('filters.q', 'jose')->where('filters.inactive', false));
});

it('pagina deudores pero mantiene el total de todo el resultado filtrado', function () {
    for ($i = 1; $i <= 30; $i++) {
        $customer = Customer::create(['name' => sprintf('José %02d', $i), 'active' => true, 'default_terms' => 'credit']);
        app(AccountsService::class)->record($customer, 'opening_balance', ['amount' => '10.00'], adminUser());
    }
    $this->actingAs(adminUser())->get(route('cobros.index', ['q' => 'jose', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('Cobros/Index')
            ->where('debtors.total', 30)->where('debtors.current_page', 2)->has('debtors.data', 5)
            ->where('total', '300.00')->where('q', 'jose'));
});
