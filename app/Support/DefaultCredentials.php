<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class DefaultCredentials
{
    /** Only names and a flag leave this check; never return credentials or hashes. */
    public static function inspect(): array
    {
        $defaults = [
            'abuelo' => ['rey2026'],
            'empleado' => ['venta2026'],
            'contador' => ['contador2026'],
        ];
        $defaults[config('default_credentials.admin_username')][] = config('default_credentials.admin_password');
        $pins = array_unique(['1234', config('default_credentials.admin_pin')]);
        $usernames = [];
        $adminPin = false;

        foreach (User::query()->where('active', true)->orderBy('id')->get() as $user) {
            foreach (array_unique($defaults[$user->username] ?? []) as $password) {
                if (Hash::check($password, $user->password)) {
                    $usernames[] = $user->username;
                    break;
                }
            }
            if ($user->isAdmin() && $user->pin_hash) {
                foreach ($pins as $pin) {
                    if (Hash::check($pin, $user->pin_hash)) {
                        $adminPin = true;
                        break;
                    }
                }
            }
        }

        return ['usernames' => $usernames, 'admin_pin' => $adminPin];
    }
}
