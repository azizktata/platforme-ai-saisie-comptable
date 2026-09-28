<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class PostDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('post', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        $document = $this->route('document');
        if ($document instanceof Document && $document->isPosted()) {
            return [];
        }

        return [
            'supplier_name' => ['required', 'string', 'max:255'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['required', 'in:EUR'],
            'account_code' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:255'],
            'subtotal' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'vat_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'total_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_name.required' => 'Renseignez le fournisseur.',
            'invoice_number.required' => 'Renseignez le numéro de facture.',
            'invoice_date.required' => 'Renseignez la date de facture.',
            'currency.in' => 'La devise EUR est la seule devise disponible dans ce MVP.',
            'account_code.required' => 'Renseignez le compte comptable.',
            'description.required' => 'Renseignez le libellé de l’écriture.',
            'total_amount.gt' => 'Le montant TTC doit être supérieur à zéro.',
        ];
    }
}
