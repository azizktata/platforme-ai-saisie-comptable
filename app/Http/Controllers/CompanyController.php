<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);
        /** @var User $user */
        $user = $request->user();

        $companies = $user->isCabinetAdmin()
            ? $user->cabinet->companies()->withCount('users')->orderBy('name')->get()
            : $user->companies()
                ->where('companies.cabinet_id', $user->cabinet_id)
                ->withCount('users')
                ->orderBy('name')
                ->get();

        return Inertia::render('Companies/Index', [
            'companies' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'tax_identifier' => $company->tax_identifier,
                'activity' => $company->activity,
                'sector' => $company->sector,
                'country_code' => $company->country_code,
                'currency' => $company->currency,
                'users_count' => $company->users_count,
                'access_role' => $user->companyRole($company),
            ])->values(),
            'canCreateCompany' => $user->can('create', Company::class),
            'canManageActivities' => $user->isCabinetAdmin(),
            'activities' => $this->activityOptions($user),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $request->user()->cabinet->companies()->create($request->validated());

        return to_route('companies.index');
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return to_route('companies.index');
    }

    private function activityOptions(User $user): array
    {
        $options = [];
        $seen = [];
        $activities = [
            ...config('company_activities', []),
            ...$user->cabinet->activities()->orderBy('name')->pluck('name')->all(),
        ];

        foreach ($activities as $activity) {
            $key = mb_strtolower($activity);

            if (! isset($seen[$key])) {
                $options[] = $activity;
                $seen[$key] = true;
            }
        }

        return $options;
    }
}
