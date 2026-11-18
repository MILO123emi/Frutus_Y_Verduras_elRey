<?php

namespace App\Http\Controllers;

use App\Exceptions\SeriesUnavailableException;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\VoidSaleRequest;
use App\Models\BusinessSettings;
use App\Models\Customer;
use App\Models\DocumentSeries;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentPdfService;
use App\Services\PriceService;
use App\Services\SaleService;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly PriceService $prices,
        private readonly DocumentPdfService $pdfs,
    ) {
    }

    /** Listado con filtros y paginación de servidor (§7.1). */
    public function index(Request $request): Response
    {
        $range = DateRange::make($request->query('range'), $request->query('from'), $request->query('to'));
        $estado = in_array($request->query('estado'), ['activas', 'anuladas'], true)
            ? $request->query('estado') : 'todas';
        $customerId = $request->integer('customer_id') ?: null;
        $numero = trim((string) $request->query('numero', ''));
        $q = trim((string) $request->query('q', ''));
        $perPage = in_array((int) $request->integer('per_page', 25), [25, 50, 100], true)
            ? (int) $request->integer('per_page', 25) : 25;

        $base = Sale::query()->whereBetween('issue_date', [$range->fromDate(), $range->toDate()]);

        $tipo = in_array($request->query('tipo'), ['pedidos', 'facturas'], true) ? $request->query('tipo') : 'todos';
        if ($tipo === 'facturas') {
            $base->whereNotNull('fiscal_snapshot');
        } elseif ($tipo === 'pedidos') {
            $base->whereNull('fiscal_snapshot');
        }

        if ($estado === 'activas') {
            $base->where('status', Sale::STATUS_ISSUED);
        } elseif ($estado === 'anuladas') {
            $base->where('status', Sale::STATUS_VOIDED);
        }

        if ($customerId) {
            $base->where('customer_id', $customerId);
        }

        if ($numero !== '') {
            $base->where('full_number', 'ILIKE', '%'.str_replace(' ', '', $numero).'%');
        }

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('full_number', 'ILIKE', '%'.str_replace(' ', '', $q).'%')
                    ->orWhereRaw('unaccent(customer_name_snapshot) ILIKE unaccent(?)', ['%'.$q.'%']);
            });
        }

        // Agregados sobre todo el filtro (no solo la página); total solo de activas (§3.8, §7.1).
        $count = (clone $base)->count();
        $activeCount = (clone $base)->issued()->count();
        $activeTotal = (clone $base)->issued()->sum('total');

        $sales = $base
            ->orderByDesc('issue_date')->orderByDesc('number')
            ->paginate($perPage)
            ->through(fn (Sale $s) => [
                'id' => $s->id,
                'full_number' => $s->full_number,
                'issue_date' => $s->issue_date->toDateString(),
                'customer' => $s->customer_name_snapshot,
                'terms' => $s->terms,
                'total' => $s->total,
                'status' => $s->status,
                'is_invoice' => $s->fiscal_snapshot !== null,
            ])
            ->withQueryString();

        return Inertia::render('Ventas/Index', [
            'sales' => $sales,
            'customers' => Customer::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'aggregates' => [
                'count' => $count,
                'active_count' => $activeCount,
                'voided_count' => $count - $activeCount,
                'active_total' => (string) $activeTotal,
            ],
            'filters' => [
                'range' => $range->preset,
                'from' => $range->fromDate(),
                'to' => $range->toDate(),
                'estado' => $estado,
                'tipo' => $tipo,
                'customer_id' => $customerId,
                'numero' => $numero,
                'q' => $q,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** Pantalla de nueva venta (§7.0-0B). */
    public function create(Request $request): Response
    {
        $kind = $request->query('tipo') === 'factura' ? 'invoice' : 'order';
        $invoiceSeries = DocumentSeries::usableInvoiceSeries();
        $settings = BusinessSettings::current();

        return Inertia::render('Ventas/Nueva', [
            'kind' => $kind,
            'preselectCustomerId' => $request->integer('cliente') ?: null,
            'invoiceReady' => [
                'ok' => $invoiceSeries !== null && $settings?->legal_name && $settings?->rtn,
                'reason' => $invoiceSeries === null
                    ? 'No hay un CAI vigente. Pídale al administrador registrarlo en Ajustes → Facturación.'
                    : (! $settings?->legal_name || ! $settings?->rtn ? 'Falta la razón social o el RTN del negocio (Ajustes).' : null),
                'next_number' => $invoiceSeries?->formatNumber($invoiceSeries->next_number),
            ],
            'products' => Product::query()->where('active', true)
                ->orderByRaw('form_order is null, form_order')
                ->orderBy('name')
                ->get(['id', 'name', 'form_order', 'tax_type', 'default_unit_id', 'reference_price']),
            'units' => Unit::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'customers' => Customer::query()->where('active', true)->orderBy('name')
                ->get(['id', 'name', 'default_terms', 'rtn', 'credit_limit']),
        ]);
    }

    /** Emite la venta y lleva al visor, marcando para impresión automática. */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        try {
            $sale = $this->sales->issue([
                'kind' => $data['kind'] ?? 'order',
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_rtn' => $data['customer_rtn'] ?? null,
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'exempt_purchase_order' => $data['exempt_purchase_order'] ?? null,
                'exonerated_record' => $data['exonerated_record'] ?? null,
                'sag_record' => $data['sag_record'] ?? null,
                'lines' => $data['lines'],
            ], $request->user());
        } catch (SeriesUnavailableException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['general' => $e->getMessage()]);
        }

        $what = $sale->isInvoice() ? 'Factura' : 'Pedido';

        return redirect()
            ->route('ventas.show', ['sale' => $sale->id, 'print' => 1])
            ->with('status', "{$what} {$sale->full_number} guardado.");
    }

    /** Visor de una venta (base de la Parte 1). */
    public function show(Request $request, Sale $sale): Response
    {
        $sale->load(['items' => fn ($q) => $q->orderBy('position'), 'convertedTo:id,full_number', 'sourceSale:id,full_number', 'voidedBy:id,name']);
        $canOperate = $request->user()->role !== User::ROLE_ACCOUNTANT;

        return Inertia::render('Ventas/Show', [
            'sale' => $sale,
            'isInvoice' => $sale->isInvoice(),
            'autoprint' => $request->boolean('print'),
            'canVoid' => $canOperate && $sale->status === Sale::STATUS_ISSUED,
            'canConvert' => $canOperate && $sale->status === Sale::STATUS_ISSUED && ! $sale->isInvoice(),
        ]);
    }

    /** Documento imprimible/visor con la plantilla única (HTML). */
    public function documento(Request $request, Sale $sale): View
    {
        $sale->load(['items' => fn ($q) => $q->orderBy('position')]);

        $variant = $request->query('variant', 'original');
        // Para impresión se emiten las dos copias; para el visor, una sola.
        $copies = $request->boolean('print')
            ? ['original', 'copy']
            : [in_array($variant, ['original', 'copy'], true) ? $variant : 'original'];

        return view($sale->documentView(), [
            'sale' => $sale,
            'copies' => $copies,
            'print_settings' => BusinessSettings::current()?->print_settings ?? [],
        ]);
    }

    /**
     * Descarga individual en PDF, servida desde la caché (§7.2). Es un enlace normal
     * (`<a href>`), no una petición de Inertia. El nombre y la variante los arma el servicio.
     */
    public function pdf(Request $request, Sale $sale): BinaryFileResponse
    {
        $variant = in_array($request->query('variant'), DocumentPdfService::VARIANTS, true)
            ? $request->query('variant') : 'original';

        return response()->download(
            $this->pdfs->get($sale, $variant),
            $this->pdfs->filename($sale, $variant),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** Registra una impresión (incrementa print_count, permitido por la inmutabilidad). */
    public function registerPrint(Sale $sale): \Illuminate\Http\Response
    {
        // Una anulada es totalmente inmutable: se puede reimprimir, pero no se cuenta.
        if (! $sale->isVoided()) {
            $sale->print_count = $sale->print_count + 1;
            $sale->save();
        }

        return response()->noContent();
    }

    /** Anulación con PIN de admin y motivo obligatorio, con bloqueo por intentos (patrón Ayala). */
    public function void(VoidSaleRequest $request, Sale $sale): RedirectResponse
    {
        $key = 'void-pin:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'pin' => "Demasiados intentos. Intente de nuevo en {$seconds} segundos.",
            ]);
        }

        $admin = $this->adminForPin($request->validated('pin'));
        if ($admin === null) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['pin' => 'PIN incorrecto.']);
        }

        RateLimiter::clear($key);
        try {
            // Queda registrado el admin que autorizó con su PIN.
            $this->sales->void($sale, $request->validated('reason'), $admin);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return back()->with('status', "Venta {$sale->full_number} anulada.");
    }

    /** Busca el admin activo dueño del PIN (pocos admins: se recorren). */
    private function adminForPin(string $pin): ?User
    {
        return User::query()->where('role', User::ROLE_ADMIN)->where('active', true)->whereNotNull('pin_hash')
            ->get()->first(fn (User $u) => Hash::check($pin, $u->pin_hash));
    }

    /** Convierte una orden en factura fiscal (la orden queda "Facturada"). */
    public function convert(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_rtn' => ['nullable', 'string', 'max:30'],
            'exempt_purchase_order' => ['nullable', 'string', 'max:60'],
            'exonerated_record' => ['nullable', 'string', 'max:60'],
            'sag_record' => ['nullable', 'string', 'max:60'],
        ]);

        try {
            $invoice = $this->sales->convertToInvoice($sale, $request->user(), $data);
        } catch (SeriesUnavailableException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['general' => $e->getMessage()]);
        }

        return redirect()->route('ventas.show', ['sale' => $invoice->id, 'print' => 1])
            ->with('status', "Listo: se hizo la factura {$invoice->full_number}.");
    }

    /** Último precio cobrado al cliente por un producto/unidad (JSON). */
    public function lastPrice(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'product_id' => ['required', 'integer'],
            'unit' => ['nullable', 'string'],
        ]);

        return response()->json([
            'price' => $this->prices->lastPriceFor(
                $validated['customer_id'] ?? null,
                $validated['product_id'],
                $validated['unit'] ?? null,
            ),
        ]);
    }

    /** Líneas del último pedido del cliente para "Repetir último pedido" (JSON). */
    public function repeatLast(Request $request)
    {
        $validated = $request->validate(['customer_id' => ['nullable', 'integer']]);

        return response()->json([
            'lines' => $this->prices->lastOrderLines($validated['customer_id'] ?? null),
        ]);
    }
}
