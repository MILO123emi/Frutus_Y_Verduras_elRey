<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /** Clientes de ejemplo (mayoristas y al detalle) para arrancar el sistema. */
    public function run(): void
    {
        $customers = [
            ['name' => 'Pulpería La Esperanza', 'default_terms' => Customer::TERMS_CREDIT, 'credit_days' => 15],
            ['name' => 'Restaurante El Muelle', 'default_terms' => Customer::TERMS_CREDIT, 'credit_days' => 30],
            ['name' => 'Comedor Doña Mari', 'default_terms' => Customer::TERMS_CASH],
            ['name' => 'Supermercado Génova', 'default_terms' => Customer::TERMS_CREDIT, 'credit_days' => 30],
            ['name' => 'Hotel Costa Atlántida', 'default_terms' => Customer::TERMS_CREDIT, 'credit_days' => 30],
            ['name' => 'María Fernández', 'default_terms' => Customer::TERMS_CASH],
            ['name' => 'Pulpería Los Ángeles', 'default_terms' => Customer::TERMS_CREDIT, 'credit_days' => 8],
            ['name' => 'Cafetería La Ceiba', 'default_terms' => Customer::TERMS_CASH],
        ];

        foreach ($customers as $customer) {
            Customer::query()->firstOrCreate(
                ['name' => $customer['name']],
                $customer + ['active' => true]
            );
        }
    }
}
