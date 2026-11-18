<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'username', 'role']),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'undo' => fn () => $request->session()->get('undo'),
            ],
            // Avisos del CAI (pocas filas; consulta barata) para el inicio y la pantalla de facturar.
            'fiscalAlerts' => fn () => $request->user()
                ? \App\Models\DocumentSeries::fiscalAlerts()
                : [],
        ];
    }
}
