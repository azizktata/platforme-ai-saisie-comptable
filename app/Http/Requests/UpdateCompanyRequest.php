<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            && ($this->user()?->can('update', $company) ?? false);
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
        /** @var Company|null $company */
        $company = $this->route('company');

        return [
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('companies', 'name')
                    ->where('cabinet_id', $company?->cabinet_id)
                    ->ignore($company?->id),
            ],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'tax_identifier' => ['nullable', 'string', 'max:80'],
            'activity' => ['nullable', 'string', 'max:190', Rule::in($this->activityOptions($company))],
            'sector' => ['nullable', 'string', 'max:190'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    private function activityOptions(?Company $company): array
    {
        $cabinetActivities = $company?->cabinet?->activities()->pluck('name')->all() ?? [];
        $activities = [...config('company_activities', []), ...$cabinetActivities];

        if (filled($company?->activity)) {
            $activities[] = $company->activity;
        }

        return array_values(array_unique($activities));
    }
}
