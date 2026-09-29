<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class SaveAccountingProposalRequest extends FormRequest
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
            'journal_id' => ['required', 'integer', 'min:1'],
            'entry_description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.id' => ['nullable', 'integer', 'min:1', 'distinct:strict'],
            'lines.*.chart_account_id' => ['required', 'integer', 'min:1'],
            'lines.*.third_party_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.analytical_account_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,3})?$/'],
            'lines.*.credit' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,3})?$/'],
        ];
    }
}
