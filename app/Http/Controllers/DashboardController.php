<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('viewAny', Company::class);

        $companies = $user->isCabinetAdmin()
            ? $user->cabinet->companies()->withCount('users')->orderBy('name')->get()
            : $user->companies()
                ->where('companies.cabinet_id', $user->cabinet_id)
                ->withCount('users')
                ->orderBy('name')
                ->get();

        return Inertia::render('Dashboard', [
            'cabinet' => [
                'name' => $user->cabinet->name,
            ],
            'companies' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'tax_identifier' => $company->tax_identifier,
                'activity' => $company->activity,
                'sector' => $company->sector,
                'currency' => $company->currency,
                'users_count' => $company->users_count,
                'access_role' => $user->companyRole($company),
            ])->values(),
            'canManageCabinet' => $user->isCabinetAdmin(),
        ]);
    }
}
