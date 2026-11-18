<?php

use App\Models\User;

beforeEach(fn () => seedFoundations());

it('muestra la pantalla de entrada', function () {
    $this->get('/login')->assertOk();
});

it('entra con nombre de usuario sin importar mayúsculas', function () {
    User::factory()->create(['username' => 'pedro', 'password' => 'secreto123']);

    $this->post('/login', ['username' => 'Pedro ', 'password' => 'secreto123'])->assertRedirect('/');
    $this->assertAuthenticated();
});

it('no deja entrar con contraseña equivocada ni a usuarios desactivados', function () {
    User::factory()->create(['username' => 'ana', 'password' => 'secreto123', 'active' => false]);

    $this->post('/login', ['username' => 'ana', 'password' => 'mala'])->assertSessionHasErrors('username');
    $this->post('/login', ['username' => 'ana', 'password' => 'secreto123'])->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('el contador no puede vender ni entrar a ajustes', function () {
    $acc = User::factory()->create(['username' => 'conta', 'role' => User::ROLE_ACCOUNTANT]);

    $this->actingAs($acc)->get('/ventas/nueva')->assertForbidden();
    $this->actingAs($acc)->get('/ajustes')->assertForbidden();
    $this->actingAs($acc)->get('/reportes')->assertOk();
});

it('sale del sistema', function () {
    $this->actingAs(adminUser())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});
