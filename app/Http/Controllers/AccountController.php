<?php

namespace App\Http\Controllers;

use App\Models\AccountMovement;
use App\Models\Customer;
use App\Services\AccountsService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** "Quién me debe": saldos, abonos y saldos anteriores. */
class AccountController extends Controller
{
    public function __construct(private readonly AccountsService $accounts)
    {
    }

    public function index(Request $request): Response
    {
        $query = $this->accounts->balancesQuery(true, $request->query('q'));
        $total = DB::query()->fromSub((clone $query)->reorder(), 'debtors')->sum('balance');

        return Inertia::render('Cobros/Index', [
            'debtors' => $query->paginate(25)->withQueryString(),
            'total' => Money::sum((string) $total),
            'q' => $request->query('q', ''),
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:payment,opening_balance'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'date' => ['nullable', 'date'],
            'method' => ['nullable', 'in:cash,transfer,check,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['amount.gt' => 'El monto debe ser mayor que cero.', 'amount.required' => 'Escriba el monto.']);

        $m = $this->accounts->record($customer, $data['type'], $data, $request->user());
        $balance = $this->accounts->balance($customer);
        $what = $data['type'] === 'payment' ? 'Abono' : 'Saldo anterior';

        return back()
            ->with('status', "{$what} de L ".number_format((float) $m->amount, 2)." guardado. Ahora debe L ".number_format((float) $balance, 2).'.')
            ->with('undo', ['url' => route('movimientos.void', $m), 'label' => "Deshacer {$what}"]);
    }

    public function void(Request $request, AccountMovement $movement): RedirectResponse
    {
        try {
            $this->accounts->void($movement, (string) $request->input('reason', ''), $request->user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'Listo, se deshizo. El saldo volvió a como estaba.');
    }
}
