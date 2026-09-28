<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCabinetUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cabinet = $this->user()?->cabinet;

        return $cabinet !== null && $this->user()->can('manageUsers', $cabinet);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'cabinet_role' => ['required', Rule::in([User::CABINET_ROLE_ADMIN, User::CABINET_ROLE_MEMBER])],
            'company_access' => ['nullable', 'array'],
            'company_access.*.company_id' => ['required', 'integer', 'distinct'],
            'company_access.*.role' => ['required', Rule::in([
                User::COMPANY_ROLE_INVOICE_MANAGER,
                User::COMPANY_ROLE_USER,
            ])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $accessInput = $this->input('company_access', []);

            if (! is_array($accessInput)) {
                return;
            }

            $companyIds = collect($accessInput)->pluck('company_id')->filter()->unique()->values();

            if ($companyIds->isEmpty()) {
                return;
            }

            $validCount = Company::query()
                ->where('cabinet_id', $this->user()->cabinet_id)
                ->whereIn('id', $companyIds)
                ->count();

            if ($validCount !== $companyIds->count()) {
                $validator->errors()->add('company_access', 'Sélectionnez uniquement des sociétés de votre cabinet.');
            }
        }];
    }
}
