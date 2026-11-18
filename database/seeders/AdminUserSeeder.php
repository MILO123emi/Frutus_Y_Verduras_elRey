<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Usuario admin inicial (§6). Credenciales y PIN configurables por env para
     * desarrollo; en la PC del negocio se cambian en el despliegue (Parte 6).
     */
    public function run(): void
    {
        $users = [
            ['username' => env('ADMIN_USERNAME', 'abuelo'), 'name' => env('ADMIN_NAME', 'Abuelo'),
                'password' => env('ADMIN_PASSWORD', 'rey2026'), 'pin_hash' => (string) env('ADMIN_PIN', '1234'),
                'role' => User::ROLE_ADMIN],
            ['username' => 'empleado', 'name' => 'Empleado', 'password' => 'venta2026',
                'pin_hash' => null, 'role' => User::ROLE_SELLER],
            ['username' => 'contador', 'name' => 'Contador', 'password' => 'contador2026',
                'pin_hash' => null, 'role' => User::ROLE_ACCOUNTANT],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(['username' => $user['username']], $user + [
                'email' => $user['username'].'@elrey.local',
                'active' => true,
                'email_verified_at' => now(),
            ]);
        }
    }
}
