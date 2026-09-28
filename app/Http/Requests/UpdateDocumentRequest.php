<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'invoice_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'currency' => ['sometimes', 'in:EUR'],
            'account_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtotal' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'vat_amount' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'total_amount' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency.in' => 'La devise EUR est la seule devise disponible dans ce MVP.',
        ];
    }
}
