<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Cimientos del sistema (§6). No genera las ~20,000 ventas de prueba: para eso
     * se corre aparte `php artisan db:seed --class=PerformanceSeeder`.
     */
    public function run(): void
    {
        $this->call([
            BusinessSettingsSeeder::class,
            UnitSeeder::class,
            DocumentSeriesSeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            AdminUserSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
