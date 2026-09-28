<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Company::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => $this->filled('country_code') ? strtoupper((string) $this->input('country_code')) : null,
            'currency' => $this->filled('currency') ? strtoupper((string) $this->input('currency')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('companies', 'name')->where('cabinet_id', $this->user()?->cabinet_id),
            ],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'tax_identifier' => ['nullable', 'string', 'max:80'],
            'activity' => ['nullable', 'string', 'max:190', Rule::in($this->activityOptions())],
            'sector' => ['nullable', 'string', 'max:190'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    private function activityOptions(): array
    {
        $cabinetActivities = $this->user()?->cabinet?->activities()->pluck('name')->all() ?? [];

        return array_values(array_unique([...config('company_activities', []), ...$cabinetActivities]));
    }
}
