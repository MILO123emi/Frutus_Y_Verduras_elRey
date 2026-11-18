<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cualquiera que venda puede pedir la anulación, pero la autoriza el PIN de un admin.
        return $this->user() !== null && $this->user()->role !== \App\Models\User::ROLE_ACCOUNTANT;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'pin' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo de anulación es obligatorio.',
            'reason.min' => 'El motivo es demasiado corto.',
            'pin.required' => 'Ingrese el PIN de administrador.',
        ];
    }
}
