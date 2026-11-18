<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Los 29 productos pre-impresos del formulario, en su orden (§6). Todos exentos y
     * sin precio; la unidad por defecto y el precio de referencia se editan después (§9).
     */
    public function run(): void
    {
        $products = [
            'Repollo', 'Tomate pera', 'Tomate manzano', 'Papas', 'Cebolla roja',
            'Cebolla amarilla', 'Chiles rellenos', 'Ajos', 'Lechuga chapina', 'Zanahoria',
            'Patastillos', 'Apio chapín', 'Habichuelas', 'Remolacha', 'Pepinos',
            'Jilotes', 'Ayotes tiernos', 'Melón', 'Piñas', 'Bananos maduros',
            'Yuca', 'Perejil', 'Limones', 'Coliflor', 'Camote',
            'Aguacate', 'Brócoli', 'Cilantro', 'Sandías',
        ];

        foreach ($products as $i => $name) {
            Product::query()->firstOrCreate(['name' => $name], [
                'tax_type' => Product::TAX_EXEMPT,
                'form_order' => $i + 1,
                'reference_price' => null,
                'default_unit_id' => null,
                'active' => true,
            ]);
        }
    }
}
