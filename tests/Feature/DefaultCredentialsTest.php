<?php

use App\Models\User;
use App\Support\DefaultCredentials;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedFoundations();
});

it('avisa al admin en Usuarios y Ajustes sin enviar secretos', function (string $route) {
    $this->actingAs(adminUser())->get(route($route))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('default_credentials', ['usernames' => ['abuelo', 'empleado', 'contador'], 'admin_pin' => true]));
})->with(['usuarios.index', 'ajustes.index']);

it('retira del aviso las contraseñas y el PIN cambiados y usuarios inactivos', function () {
    adminUser()->update(['password' => 'otra-clave-segura', 'pin_hash' => '987654']);
    User::where('username', 'empleado')->firstOrFail()->update(['active' => false]);
    expect(DefaultCredentials::inspect())->toBe(['usernames' => ['contador'], 'admin_pin' => false]);
    User::where('username', 'contador')->firstOrFail()->update(['password' => 'clave-nueva-contador']);
    $this->actingAs(adminUser())->get(route('usuarios.index'))->assertInertia(fn (Assert $page) => $page
        ->where('default_credentials', ['usernames' => [], 'admin_pin' => false]));
});

it('conserva el aviso del PIN aunque todas las contraseñas se hayan cambiado', function () {
    foreach (User::all() as $user) {
        $user->update(['password' => 'clave-nueva-segura']);
    }
    expect(DefaultCredentials::inspect())->toBe(['usernames' => [], 'admin_pin' => true]);
});

it('detecta los valores de siembra configurados y el PIN aun con otro username', function () {
    config(['default_credentials.admin_username' => 'encargado',
        'default_credentials.admin_password' => 'clave-configurada', 'default_credentials.admin_pin' => '456789']);
    adminUser()->update(['username' => 'encargado', 'password' => 'clave-configurada', 'pin_hash' => '456789']);
    expect(DefaultCredentials::inspect())->toBe(['usernames' => ['encargado', 'empleado', 'contador'], 'admin_pin' => true]);
    adminUser()->update(['active' => false]);
    expect(DefaultCredentials::inspect())->toBe(['usernames' => ['empleado', 'contador'], 'admin_pin' => false]);
});

it('impide que vendedor y contador reciban el aviso de administración', function (string $role, string $route) {
    $user = User::where('role', $role)->firstOrFail();
    $this->actingAs($user)->get(route($route))->assertForbidden();
})->with([
    ['seller', 'usuarios.index'], ['seller', 'ajustes.index'],
    ['accountant', 'usuarios.index'], ['accountant', 'ajustes.index'],
]);
