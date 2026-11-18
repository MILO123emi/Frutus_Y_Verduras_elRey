<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Freight;
use App\Models\FreightSchedule;
use App\Services\FreightService;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FreightController extends Controller
{
    public function __construct(private readonly FreightService $service)
    {
    }

    /** Calendario: semana (lunes a domingo) o mes. */
    public function index(Request $request): Response
    {
        $view = $request->query('vista') === 'mes' ? 'month' : 'week';
        $today = CarbonImmutable::now(DateRange::TZ)->startOfDay();
        try {
            $anchor = $request->query('fecha') ? CarbonImmutable::parse($request->query('fecha'), DateRange::TZ) : $today;
        } catch (\Throwable) {
            $anchor = $today;
        }

        [$from, $to] = $view === 'month'
            ? [$anchor->startOfMonth()->startOfWeek(), $anchor->endOfMonth()->endOfWeek()]
            : [$anchor->startOfWeek(), $anchor->endOfWeek()];

        $this->service->materialize($from, $to);

        return Inertia::render('Fletes/Index', [
            'view' => $view,
            'anchor' => $anchor->toDateString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'today' => $today->toDateString(),
            'freights' => Freight::query()->with(['driver:id,name', 'customer:id,name'])
                ->whereBetween('trip_date', [$from->toDateString(), $to->toDateString()])
                ->orderBy('trip_date')->orderByRaw('departure_time nulls last')->orderBy('id')
                ->get()->map(fn (Freight $f) => [
                    ...$f->only(['id', 'driver_id', 'destination', 'purpose', 'for_whom', 'customer_id', 'vehicle',
                        'departure_time', 'notes', 'charge_amount', 'charge_to_account', 'status', 'freight_schedule_id']),
                    'trip_date' => $f->trip_date->toDateString(),
                    'driver' => $f->driver?->name,
                    'customer' => $f->customer?->name,
                ]),
            'drivers' => Driver::query()->orderByDesc('active')->orderBy('name')->get(),
            'schedules' => FreightSchedule::query()->with('driver:id,name')->orderBy('weekday')->get(),
            'customers' => Customer::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Freight::create($data + ['user_id' => $request->user()->id, 'customized' => true]);

        return back()->with('status', 'Flete anotado en el calendario.');
    }

    public function update(Request $request, Freight $freight): RedirectResponse
    {
        $freight->update($this->validated($request) + ['customized' => true]);
        $msg = $freight->status === 'cancelled' ? 'Flete cancelado.' : 'Flete guardado.';

        return back()->with('status', $msg);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        FreightSchedule::create($this->validatedSchedule($request));

        return back()->with('status', 'Viaje fijo agregado. Ya aparece en el calendario.');
    }

    public function updateSchedule(Request $request, FreightSchedule $schedule): RedirectResponse
    {
        $schedule->update($this->validatedSchedule($request));
        $this->service->resetFuture($schedule);

        return back()->with('status', 'Viaje fijo guardado. Los próximos viajes se actualizaron.');
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        Driver::create($this->validatedDriver($request) + ['active' => true]);

        return back()->with('status', 'Motorista agregado.');
    }

    public function updateDriver(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validatedDriver($request));

        return back()->with('status', 'Motorista guardado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'trip_date' => ['required', 'date'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'destination' => ['required', 'string', 'max:120'],
            'purpose' => ['required', 'in:business,family,customer,moving,other'],
            'for_whom' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'vehicle' => ['nullable', 'string', 'max:80'],
            'departure_time' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'charge_amount' => ['nullable', 'numeric', 'gte:0'],
            'charge_to_account' => ['boolean'],
            'status' => ['required', 'in:scheduled,done,cancelled'],
        ], ['destination.required' => 'Escriba a dónde va el flete.']);

        // Solo se carga a la cuenta si hay cliente y monto.
        $data['charge_to_account'] = ($data['charge_to_account'] ?? false)
            && ! empty($data['customer_id']) && (float) ($data['charge_amount'] ?? 0) > 0;

        return $data;
    }

    private function validatedSchedule(Request $request): array
    {
        return $request->validate([
            'weekday' => ['required', 'integer', 'between:1,7'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'destination' => ['required', 'string', 'max:120'],
            'vehicle' => ['nullable', 'string', 'max:80'],
            'departure_time' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
        ]);
    }

    private function validatedDriver(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'vehicle' => ['nullable', 'string', 'max:80'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
