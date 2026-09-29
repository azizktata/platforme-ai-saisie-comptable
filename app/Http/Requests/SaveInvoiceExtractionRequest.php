<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class SaveInvoiceExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        $invoice = $this->route('invoice');

        return $company instanceof Company
            && $invoice instanceof Invoice
            && (int) $invoice->company_id === (int) $company->id
            && $this->user()?->can('manageInvoices', $company) === true;
    }

    public function rules(): array
    {
        return [
            'invoice_data' => ['required', 'array'],
        ];
    }
}
