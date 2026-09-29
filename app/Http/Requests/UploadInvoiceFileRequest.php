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
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.$this->maxFileSizeKilobytes()],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Sélectionnez un fichier à importer.',
            'file.file' => 'Le fichier transmis est invalide.',
            'file.mimes' => 'Formats acceptés : PDF, JPG, JPEG et PNG.',
            'file.max' => 'Chaque fichier doit faire '.$this->maxFileSizeLabel().' maximum.',
        ];
    }

    private function maxFileSizeKilobytes(): int
    {
        return (int) ceil($this->maxFileSizeBytes() / 1024);
    }

    private function maxFileSizeBytes(): int
    {
        return config('services.ocr.provider') === 'ocr_space'
            ? max(1, (int) config('services.ocr_space.max_file_size_bytes', 1024 * 1024))
            : 20 * 1024 * 1024;
    }

    private function maxFileSizeLabel(): string
    {
        $megabytes = $this->maxFileSizeBytes() / (1024 * 1024);

        return rtrim(rtrim(number_format($megabytes, 2, ',', ''), '0'), ',').' Mo';
    }
}
