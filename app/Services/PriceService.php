<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;

/**
 * Precios de ayuda para la pantalla de venta (§7.0-0B).
 */
class PriceService
{
    /**
     * Último precio cobrado a un cliente por un producto y unidad. Si no hay historial,
     * devuelve null para que la pantalla caiga al precio de referencia del producto.
     */
    public function lastPriceFor(?int $customerId, int $productId, ?string $unit): ?string
    {
        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_ISSUED)
            ->where('sale_items.product_id', $productId);

        if ($customerId !== null) {
            $query->where('sales.customer_id', $customerId);
        } else {
            $query->whereNull('sales.customer_id');
        }

        if ($unit !== null && $unit !== '') {
            $query->where('sale_items.unit_snapshot', $unit);
        }

        return $query
            ->orderByDesc('sales.issued_at')
            ->orderByDesc('sale_items.id')
            ->value('sale_items.unit_price');
    }

    /**
     * Líneas del último pedido del cliente, para "Repetir último pedido".
     *
     * @return array<int, array{product_id:int|null, description:string, unit:string|null, quantity:string, unit_price:string, tax_type:string}>
     */
    public function lastOrderLines(?int $customerId): array
    {
        $query = Sale::query()->issued()->with('items');
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        } else {
            $query->whereNull('customer_id');
        }

        $sale = $query->orderByDesc('issued_at')->first();
        if ($sale === null) {
            return [];
        }

        return $sale->items
            ->sortBy('position')
            ->map(fn (SaleItem $item) => [
                'product_id' => $item->product_id,
                'description' => $item->description_snapshot,
                'unit' => $item->unit_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_type' => $item->tax_type_snapshot,
            ])
            ->values()
            ->all();
    }
}
