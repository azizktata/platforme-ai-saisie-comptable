<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_only_companies_assigned_to_the_signed_in_user(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id, 'name' => 'Ma société']);
        Company::factory()->create(['cabinet_id' => $cabinet->id, 'name' => 'Autre société']);
        $user->companies()->attach($company, ['role' => User::COMPANY_ROLE_USER]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('cabinet.name', $cabinet->name)
                ->has('companies', 1)
                ->where('companies.0.name', 'Ma société')
                ->where('companies.0.access_role', User::COMPANY_ROLE_USER)
                ->where('canManageCabinet', false));
    }

    public function test_cabinet_administrator_can_see_all_companies_in_their_cabinet(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        Company::factory()->count(2)->create(['cabinet_id' => $cabinet->id]);
        Company::factory()->create();

        $this->actingAs($admin)
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies', 2)
                ->where('canManageCabinet', true));
    }
}
