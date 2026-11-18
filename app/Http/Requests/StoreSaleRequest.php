<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El contador es solo lectura (§5); admin y seller pueden registrar ventas.
        return $this->user() !== null && $this->user()->role !== User::ROLE_ACCOUNTANT;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', 'in:order,invoice'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_rtn' => ['nullable', 'string', 'max:30'],
            'exempt_purchase_order' => ['nullable', 'string', 'max:60'],
            'exonerated_record' => ['nullable', 'string', 'max:60'],
            'sag_record' => ['nullable', 'string', 'max:60'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'in:cash,credit'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.tax_type' => ['nullable', 'in:exempt,g15,g18'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'La venta debe tener al menos una línea.',
            'lines.*.description.required' => 'Cada línea necesita una descripción.',
            'lines.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
        ];
    }
}
