<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->cabinet_id !== null;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasCompanyAccess($company);
    }

    public function create(User $user): bool
    {
        return $user->isCabinetAdmin() && $user->cabinet_id !== null;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isCabinetAdmin() && $user->cabinet_id === $company->cabinet_id;
    }

    public function manageAccountingData(User $user, Company $company): bool
    {
        if (! $user->hasCompanyAccess($company)) {
            return false;
        }

        return $user->isCabinetAdmin()
            || $user->companyRole($company) === User::COMPANY_ROLE_INVOICE_MANAGER;
    }
}
