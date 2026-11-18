<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Unidades del formulario (§1) más algunas por confirmar con el abuelo (§9).
     * Las "por confirmar" quedan inactivas hasta validarlas.
     */
    public function run(): void
    {
        $confirmed = [
            ['name' => 'Matate', 'abbreviation' => 'matate'],
            ['name' => 'Saco', 'abbreviation' => 'saco'],
            ['name' => 'Caja', 'abbreviation' => 'caja'],
            ['name' => 'Unidad', 'abbreviation' => 'u'],
            ['name' => 'Bolsa', 'abbreviation' => 'bolsa'],
            ['name' => 'Maleta', 'abbreviation' => 'maleta'],
        ];
        foreach ($confirmed as $unit) {
            Unit::query()->firstOrCreate(['name' => $unit['name']], $unit + ['active' => true]);
        }

        // Por confirmar (§9): se dejan inactivas hasta validarlas con el abuelo.
        $tentative = [
            ['name' => 'Docena', 'abbreviation' => 'doc'],
            ['name' => 'Libra', 'abbreviation' => 'lb'],
            ['name' => 'Quintal', 'abbreviation' => 'qq'],
            ['name' => 'Manojo', 'abbreviation' => 'manojo'],
        ];
        foreach ($tentative as $unit) {
            Unit::query()->firstOrCreate(['name' => $unit['name']], $unit + ['active' => false]);
        }
    }
}
