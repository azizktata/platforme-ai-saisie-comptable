<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Sélectionnez un document à importer.',
            'file.mimes' => 'Le document doit être un PDF, un JPG ou un PNG.',
            'file.max' => 'Le document ne peut pas dépasser 20 Mo.',
        ];
    }
}
