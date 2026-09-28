<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class UploadInvoiceFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            && ($this->user()?->can('manageInvoices', $company) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Sélectionnez un fichier à importer.',
            'file.file' => 'Le fichier transmis est invalide.',
            'file.mimes' => 'Formats acceptés : PDF, JPG, JPEG et PNG.',
            'file.max' => 'Chaque fichier doit faire 20 Mo maximum.',
        ];
    }
}
