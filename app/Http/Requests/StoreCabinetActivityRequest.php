<?php

namespace App\Http\Requests;

use App\Models\Cabinet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCabinetActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cabinet = $this->user()?->cabinet;

        return $cabinet !== null && $this->user()->can('manageUsers', $cabinet);
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $this->merge(['name' => preg_replace('/\s+/u', ' ', $name) ?? $name]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $name = trim((string) $this->input('name'));

            if ($name === '') {
                return;
            }

            $normalizedName = mb_strtolower($name);
            $isPredefined = collect(config('company_activities', []))
                ->contains(fn (string $activity): bool => mb_strtolower($activity) === $normalizedName);

            if ($isPredefined) {
                $validator->errors()->add('name', 'Cette activité fait déjà partie des choix proposés.');
                return;
            }

            /** @var Cabinet|null $cabinet */
            $cabinet = $this->user()?->cabinet;
            $exists = $cabinet?->activities()
                ->get(['name'])
                ->contains(fn ($activity): bool => mb_strtolower($activity->name) === $normalizedName) ?? false;

            if ($exists) {
                $validator->errors()->add('name', 'Cette activité existe déjà dans le catalogue du cabinet.');
            }
        }];
    }
}
