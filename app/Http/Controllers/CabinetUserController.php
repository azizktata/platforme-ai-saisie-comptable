<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCabinetUserRequest;
use App\Http\Requests\UpdateCabinetUserRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CabinetUserController extends Controller
{
    public function index(Request $request): Response
    {
        $cabinet = $request->user()->cabinet;
        $this->authorize('manageUsers', $cabinet);
        $users = $cabinet->users()
            ->with(['companies:id,name'])
            ->orderBy('name')
            ->get();
        $companies = $cabinet->companies()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Cabinet/Users/Index', [
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cabinet_role' => $user->cabinet_role,
                'companies' => $user->companies->map(fn (Company $company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'role' => $company->pivot->role,
                ])->values(),
            ])->values(),
            'companies' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
            ])->values(),
        ]);
    }

    public function store(StoreCabinetUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $companyAccess = $data['company_access'] ?? [];
        unset($data['company_access']);

        DB::transaction(function () use ($request, $data, $companyAccess): void {
            $user = $request->user()->cabinet->users()->create($data);
            $user->companies()->sync(collect($companyAccess)->mapWithKeys(fn (array $access): array => [
                $access['company_id'] => ['role' => $access['role']],
            ])->all());
        });

        return to_route('cabinet.users.index')->with('success', 'Utilisateur ajouté au cabinet.');
    }

    public function update(UpdateCabinetUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $companyAccess = $data['company_access'];
        unset($data['company_access']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $companyAccess): void {
            $user->update($data);
            $user->companies()->sync(collect($companyAccess)->mapWithKeys(fn (array $access): array => [
                $access['company_id'] => ['role' => $access['role']],
            ])->all());
        });

        return to_route('cabinet.users.index')->with('success', 'Accès utilisateur mis à jour.');
    }
}
