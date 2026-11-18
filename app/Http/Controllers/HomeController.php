<?php

namespace App\Http\Controllers;

use App\Models\Freight;
use App\Models\Sale;
use App\Services\AccountsService;
use App\Services\FreightService;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(AccountsService $accounts, FreightService $freights): Response
    {
        $today = CarbonImmutable::now(DateRange::TZ)->startOfDay();
        $todaySales = Sale::query()->issued()->whereDate('issue_date', $today->toDateString());

        $freights->materialize($today, $today->addDays(7));
        $nextTrips = Freight::query()->with('driver:id,name')
            ->whereBetween('trip_date', [$today->toDateString(), $today->addDays(7)->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->orderBy('trip_date')->orderBy('departure_time')->limit(4)
            ->get(['id', 'trip_date', 'destination', 'driver_id', 'for_whom', 'departure_time']);

        return Inertia::render('Home', [
            'today' => [
                'date' => $today->toDateString(),
                'count' => (clone $todaySales)->count(),
                'total' => (string) ((clone $todaySales)->sum('total') ?: '0.00'),
                'cash' => (string) ((clone $todaySales)->where('terms', 'cash')->sum('total') ?: '0.00'),
            ],
            'receivable' => $accounts->totalReceivable(),
            'debtors' => $accounts->balances()->count(),
            'nextTrips' => $nextTrips->map(fn ($f) => [
                'id' => $f->id, 'date' => $f->trip_date->toDateString(), 'destination' => $f->destination,
                'driver' => $f->driver?->name, 'for_whom' => $f->for_whom, 'time' => $f->departure_time,
            ]),
        ]);
    }
}
