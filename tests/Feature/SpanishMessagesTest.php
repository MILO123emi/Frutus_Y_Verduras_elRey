<?php

use Illuminate\Support\Facades\Route;

it('traduce los campos requeridos del acceso y los atributos anidados', function () {
    app()->setLocale('es');
    $this->post(route('login'), [])->assertSessionHasErrors([
        'username' => 'Complete el campo usuario.', 'password' => 'Complete el campo contraseña.',
    ]);
    $validator = validator(['lines' => [['quantity' => 'abc']]], ['lines.*.quantity' => 'numeric']);
    expect($validator->errors()->first())->toBe('Escriba un número válido para cantidad.');
});

it('presenta errores comprensibles con enlace al inicio sin mostrar detalles internos', function (int $code, string $title) {
    config(['app.debug' => false]);
    Route::get('/prueba-error', fn () => abort($code, 'Detalle interno reservado'));
    $this->get('/prueba-error')->assertStatus($code)->assertSee($title)->assertSee('Volver al inicio')->assertDontSee('Detalle interno reservado');
})->with([
    [403, 'No tiene permiso para entrar aquí'], [404, 'No encontramos esta página'],
    [419, 'Su sesión venció'], [500, 'No se pudo completar la operación'],
]);
