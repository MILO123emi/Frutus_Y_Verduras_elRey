<?php

use Illuminate\Support\Facades\Schedule;

// Requiere el programador de tareas del sistema llamando cada minuto a `php artisan schedule:run`
// (cron en Linux/macOS, Programador de tareas en Windows). Ver docs/INSTALACION.md.
Schedule::command('backup:run')->dailyAt('18:00')->timezone('America/Tegucigalpa');
Schedule::command('documents:warm-pdfs', [
    '--from' => now('America/Tegucigalpa')->subDays(7)->toDateString(),
    '--to' => now('America/Tegucigalpa')->toDateString(),
])->dailyAt('02:00')->timezone('America/Tegucigalpa');
