<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FreightController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// URLs visibles en español; nombres de ruta en inglés.
Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    // --- Lectura (todos los roles, incluido el contador) ---
    Route::get('/ventas', [SaleController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/{sale}', [SaleController::class, 'show'])->whereNumber('sale')->name('ventas.show');
    Route::get('/ventas/{sale}/documento', [SaleController::class, 'documento'])->name('ventas.documento');
    Route::get('/ventas/{sale}/pdf', [SaleController::class, 'pdf'])->name('ventas.pdf');
    Route::get('/clientes', [CustomerController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/{customer}', [CustomerController::class, 'show'])->name('clientes.show');
    Route::get('/clientes/{customer}/estado-de-cuenta', [CustomerController::class, 'statement'])->name('clientes.statement');
    Route::get('/cobros', [AccountController::class, 'index'])->name('cobros.index');
    Route::get('/reportes', [ReportController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/excel', [ReportController::class, 'excel'])->name('reportes.excel');
    Route::get('/reportes/zip', [ReportController::class, 'zip'])->name('reportes.zip');

    // --- Operación diaria (admin y vendedor) ---
    Route::middleware('role:admin,seller')->group(function () {
        Route::get('/ventas/nueva', [SaleController::class, 'create'])->name('ventas.create');
        Route::post('/ventas', [SaleController::class, 'store'])->name('ventas.store');
        Route::get('/ventas/ultimo-precio', [SaleController::class, 'lastPrice'])->name('ventas.last-price');
        Route::get('/ventas/repetir', [SaleController::class, 'repeatLast'])->name('ventas.repeat');
        Route::post('/ventas/{sale}/imprimir', [SaleController::class, 'registerPrint'])->name('ventas.print');
        Route::post('/ventas/{sale}/facturar', [SaleController::class, 'convert'])->name('ventas.convert');
        Route::post('/ventas/{sale}/anular', [SaleController::class, 'void'])->name('ventas.void'); // pide PIN de admin

        Route::post('/clientes', [CustomerController::class, 'store'])->name('clientes.store');
        Route::put('/clientes/{customer}', [CustomerController::class, 'update'])->name('clientes.update');
        Route::post('/clientes/{customer}/movimientos', [AccountController::class, 'store'])->name('movimientos.store');
        Route::post('/movimientos/{movement}/anular', [AccountController::class, 'void'])->name('movimientos.void');

        Route::get('/productos', [ProductController::class, 'index'])->name('productos.index');
        Route::post('/productos', [ProductController::class, 'store'])->name('productos.store');
        Route::put('/productos/{product}', [ProductController::class, 'update'])->name('productos.update');

        Route::get('/fletes', [FreightController::class, 'index'])->name('fletes.index');
        Route::post('/fletes', [FreightController::class, 'store'])->name('fletes.store');
        Route::put('/fletes/{freight}', [FreightController::class, 'update'])->name('fletes.update');
        Route::post('/fletes/viajes-fijos', [FreightController::class, 'storeSchedule'])->name('fletes.schedules.store');
        Route::put('/fletes/viajes-fijos/{schedule}', [FreightController::class, 'updateSchedule'])->name('fletes.schedules.update');
        Route::post('/fletes/motoristas', [FreightController::class, 'storeDriver'])->name('fletes.drivers.store');
        Route::put('/fletes/motoristas/{driver}', [FreightController::class, 'updateDriver'])->name('fletes.drivers.update');
    });

    // --- Administración ---
    Route::middleware('role:admin')->group(function () {
        Route::get('/ajustes', [SettingsController::class, 'index'])->name('ajustes.index');
        Route::put('/ajustes/negocio', [SettingsController::class, 'updateBusiness'])->name('ajustes.business');
        Route::post('/ajustes/cai', [SettingsController::class, 'storeCai'])->name('ajustes.cai.store');
        Route::put('/ajustes/series/{series}', [SettingsController::class, 'updateSeries'])->name('ajustes.series.update');
        Route::post('/ajustes/respaldo', [SettingsController::class, 'backup'])->name('ajustes.backup');
        Route::get('/ajustes/respaldo/{file}', [SettingsController::class, 'downloadBackup'])->name('ajustes.backup.download');

        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
    });
});

require __DIR__.'/auth.php';
