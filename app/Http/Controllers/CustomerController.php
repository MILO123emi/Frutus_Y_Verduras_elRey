<?php

namespace App\Http\Controllers;

use App\Models\BusinessSettings;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountsService;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly AccountsService $accounts) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $showInactive = $request->boolean('inactive');
        $query = Customer::query()->when(! $showInactive, fn ($q) => $q->where('active', true))
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->whereRaw('unaccent(name) ILIKE unaccent(?)', ['%'.$search.'%'])
                    ->orWhere('phone', 'ILIKE', '%'.$search.'%')->orWhere('rtn', 'ILIKE', '%'.$search.'%');
            }));
        $customers = $query->orderByDesc('active')->orderBy('name')->orderBy('id')
            ->paginate(25, ['id', 'name', 'rtn', 'phone', 'address', 'default_terms', 'credit_limit', 'credit_days', 'active', 'notes']);
        $balances = $this->accounts->balancesQuery(false)->whereIn('c.id', $customers->pluck('id'))->get()->keyBy('id');
        $customers->through(fn (Customer $c) => $c->toArray() + ['balance' => $balances[$c->id]->balance ?? '0.00']);

        return Inertia::render('Clientes/Index', [
            'customers' => $customers->withQueryString(),
            'filters' => ['q' => $search, 'inactive' => $showInactive],
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $open = $this->accounts->openDocuments($customer);

        return Inertia::render('Clientes/Show', [
            'customer' => $customer,
            'balance' => $this->accounts->balance($customer),
            'open' => $open,
            'ledger' => array_reverse(array_slice($this->accounts->ledger($customer, includeVoided: true), -60)),
            'recentSales' => Sale::query()->where('customer_id', $customer->id)
                ->orderByDesc('issued_at')->limit(10)
                ->get(['id', 'full_number', 'issue_date', 'terms', 'total', 'status', 'fiscal_snapshot'])
                ->map(fn (Sale $s) => [
                    'id' => $s->id, 'full_number' => $s->full_number, 'date' => $s->issue_date->toDateString(),
                    'terms' => $s->terms, 'total' => $s->total, 'status' => $s->status, 'is_invoice' => $s->isInvoice(),
                ]),
            'canOperate' => $request->user()->role !== User::ROLE_ACCOUNTANT,
        ]);
    }

    /** Estado de cuenta imprimible (HTML con window.print). */
    public function statement(Request $request, Customer $customer): View
    {
        $range = DateRange::make($request->query('range', 'custom'), $request->query('from', '2000-01-01'), $request->query('to'));

        return view('documents.statement', [
            'customer' => $customer,
            'issuer' => BusinessSettings::current(),
            'ledger' => $this->accounts->ledger($customer, $range->fromDate(), $range->toDate(), includeVoided: true),
            'open' => $this->accounts->openDocuments($customer),
            'balance' => $this->accounts->balance($customer),
            'from' => $range->fromDate() === '2000-01-01' ? null : $range->fromDate(),
            'to' => $range->toDate(),
            'today' => CarbonImmutable::now(DateRange::TZ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->validated($request) + ['active' => true]);

        if ($request->boolean('stay')) {
            return back()->with('status', "Cliente «{$customer->name}» agregado.")->with('createdCustomerId', $customer->id);
        }

        return redirect()->route('clientes.show', $customer)->with('status', "Cliente «{$customer->name}» agregado.");
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));

        return back()->with('status', 'Datos del cliente guardados.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rtn' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'default_terms' => ['required', 'in:cash,credit'],
            'credit_limit' => ['nullable', 'numeric', 'gte:0'],
            'credit_days' => ['nullable', 'integer', 'gte:0', 'lte:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ], ['name.required' => 'Escriba el nombre del cliente.']);
    }
}
