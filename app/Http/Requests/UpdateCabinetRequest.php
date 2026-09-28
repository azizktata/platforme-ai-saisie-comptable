<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCabinetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cabinet = $this->user()?->cabinet;

        return $cabinet !== null && $this->user()->can('update', $cabinet);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => strtolower(trim((string) $this->input('slug'))),
        ]);
    }

    public function rules(): array
    {
        $cabinet = $this->user()?->cabinet;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:190',
                'alpha_dash:ascii',
                Rule::unique('cabinets', 'slug')->ignore($cabinet?->id),
            ],
        ];
    }
}
