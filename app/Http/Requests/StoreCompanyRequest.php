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
            'vat_rates' => $this->normalizedVatRates(),
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
            'vat_rates' => ['nullable', 'array', 'max:20'],
            'vat_rates.*' => ['required', 'numeric', 'between:0,100', 'distinct:strict'],
            'fiscal_year_start' => ['nullable', 'date_format:Y-m-d', 'required_with:fiscal_year_end', 'before_or_equal:fiscal_year_end'],
            'fiscal_year_end' => ['nullable', 'date_format:Y-m-d', 'required_with:fiscal_year_start', 'after_or_equal:fiscal_year_start'],
            'capitalization_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999999999.999', 'decimal:0,3'],
        ];
    }

    private function activityOptions(): array
    {
        $cabinetActivities = $this->user()?->cabinet?->activities()->pluck('name')->all() ?? [];

        return array_values(array_unique([...config('company_activities', []), ...$cabinetActivities]));
    }

    private function normalizedVatRates(): ?array
    {
        $rates = $this->input('vat_rates');

        return is_array($rates) ? array_values(array_map(static function ($rate): string {
            if (! is_numeric($rate)) return (string) $rate;
            return rtrim(rtrim(number_format((float) $rate, 3, '.', ''), '0'), '.');
        }, $rates)) : null;
    }
}
