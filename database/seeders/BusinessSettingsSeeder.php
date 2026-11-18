<?php

namespace Database\Seeders;

use App\Models\BusinessSettings;
use Illuminate\Database\Seeder;

class BusinessSettingsSeeder extends Seeder
{
    /** Datos del emisor tomados del formulario de papel (§1). */
    public function run(): void
    {
        BusinessSettings::query()->firstOrCreate([], [
            'trade_name' => 'Frutas y Verduras "El Rey"',
            'legal_name' => null, // ⏳ por confirmar: no aparece en el formulario (§1)
            'activity' => 'Venta de frutas, verduras, granos básicos · Venta al por mayor y al detalle',
            'rtn' => '01041959002984',
            'address' => 'Frente a Residencial Génova, Calle de Copena hacia el Muelle de Cabotaje, La Ceiba, Atlántida, Honduras, C.A.',
            'phone' => '9967-2292',
            'print_settings' => [
                'paper_size' => 'letter', // carta | half-letter ⏳ por confirmar (§7.0-0B)
                'copies' => ['original', 'copy'],
            ],
        ]);
    }
}
