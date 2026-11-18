<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DefaultCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Usuarios: el admin los crea, cambia de rol, desactiva y les restablece la contraseña. */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Usuarios/Index', [
            'default_credentials' => DefaultCredentials::inspect(),
            'users' => User::query()->orderByDesc('active')->orderBy('name')
                ->get(['id', 'name', 'username', 'role', 'active', 'pin_hash'])
                ->map(fn (User $u) => [...$u->only(['id', 'name', 'username', 'role', 'active']), 'has_pin' => $u->pin_hash !== null]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['username'].'@elrey.local',
            'role' => $data['role'],
            'password' => $data['password'],
            'pin_hash' => $data['pin'] ?? null,
            'active' => true,
        ]);

        return back()->with('status', "Usuario «{$user->username}» creado.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        // No dejar el sistema sin administradores activos.
        $losesAdmin = $user->isAdmin() && (($data['role'] ?? 'admin') !== 'admin' || ! ($data['active'] ?? true));
        if ($losesAdmin && User::query()->where('role', 'admin')->where('active', true)->count() <= 1) {
            return back()->with('error', 'Debe quedar al menos un administrador activo.');
        }

        $user->fill([
            'name' => $data['name'], 'username' => $data['username'], 'role' => $data['role'],
            'active' => $data['active'] ?? $user->active,
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if (! empty($data['pin'])) {
            $user->pin_hash = $data['pin'];
        }
        $user->save();

        return back()->with('status', "Usuario «{$user->username}» guardado.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'alpha_dash', 'max:40', Rule::unique('users', 'username')->ignore($user?->id)],
            'role' => ['required', 'in:admin,seller,accountant'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6'],
            'pin' => ['nullable', 'digits_between:4,6'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'username.unique' => 'Ese nombre de usuario ya existe.',
            'username.alpha_dash' => 'El usuario solo puede tener letras, números y guiones (sin espacios).',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'pin.digits_between' => 'El PIN debe tener de 4 a 6 números.',
        ]);
    }
}
