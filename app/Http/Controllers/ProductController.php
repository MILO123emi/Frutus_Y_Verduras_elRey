<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Productos/Index', [
            'products' => Product::query()->with('defaultUnit:id,name')
                ->orderByDesc('active')->orderByRaw('form_order is null, form_order')->orderBy('name')
                ->get(['id', 'name', 'default_unit_id', 'reference_price', 'tax_type', 'form_order', 'active']),
            'units' => Unit::query()->orderByDesc('active')->orderBy('name')->get(['id', 'name', 'active']),
        ]);
    }

    /** Alta desde la pantalla de productos (redirige) o rápida desde la venta (JSON). */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);

        $product = Product::query()->firstOrCreate(
            ['name' => $data['name']],
            [
                'tax_type' => $data['tax_type'] ?? Product::TAX_EXEMPT,
                'default_unit_id' => $data['default_unit_id'] ?? $request->input('unit_id'),
                'reference_price' => $data['reference_price'] ?? null,
                'active' => true,
            ]
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'product' => $product->only(['id', 'name', 'tax_type', 'default_unit_id', 'reference_price']),
            ]);
        }

        return back()->with('status', "Producto «{$product->name}» agregado.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return back()->with('status', "Producto «{$product->name}» guardado.");
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($product?->id)],
            'default_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'reference_price' => ['nullable', 'numeric', 'gte:0'],
            'tax_type' => ['nullable', 'in:exempt,g15,g18'],
            'active' => ['sometimes', 'boolean'],
        ], ['name.unique' => 'Ya existe un producto con ese nombre.']);
    }
}
