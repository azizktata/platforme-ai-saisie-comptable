<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCabinetUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        return $actor !== null
            && $target instanceof User
            && (int) $actor->cabinet_id === (int) $target->cabinet_id
            && $actor->can('manageUsers', $actor->cabinet);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        /** @var User|null $managedUser */
        $managedUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => [
                'required', 'string', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($managedUser?->id),
            ],
            'password' => ['nullable', 'string', 'min:12'],
            'cabinet_role' => ['required', Rule::in([User::CABINET_ROLE_ADMIN, User::CABINET_ROLE_MEMBER])],
            'company_access' => ['present', 'array'],
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

            if ($companyIds->isNotEmpty()) {
                $validCount = Company::query()
                    ->where('cabinet_id', $this->user()->cabinet_id)
                    ->whereIn('id', $companyIds)
                    ->count();

                if ($validCount !== $companyIds->count()) {
                    $validator->errors()->add('company_access', 'Sélectionnez uniquement des sociétés de votre cabinet.');
                }
            }

            $managedUser = $this->route('user');

            if (
                $managedUser instanceof User
                && $managedUser->isCabinetAdmin()
                && $this->input('cabinet_role') === User::CABINET_ROLE_MEMBER
                && ! User::query()
                    ->where('cabinet_id', $managedUser->cabinet_id)
                    ->where('cabinet_role', User::CABINET_ROLE_ADMIN)
                    ->where('id', '!=', $managedUser->getKey())
                    ->exists()
            ) {
                $validator->errors()->add('cabinet_role', 'Le cabinet doit conserver au moins un administrateur.');
            }
        }];
    }
}
