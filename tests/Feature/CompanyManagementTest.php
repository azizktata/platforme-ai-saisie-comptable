<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cabinet_administrator_can_create_a_company_in_their_cabinet(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($admin)->post('/companies', [
            'name' => 'Entreprise Exemple',
            'legal_name' => 'Entreprise Exemple SARL',
            'tax_identifier' => '1234567A',
            'activity' => 'Services informatiques et télécommunications',
            'country_code' => 'tn',
            'currency' => 'tnd',
        ])->assertRedirect('/companies');

        $this->assertDatabaseHas('companies', [
            'cabinet_id' => $cabinet->id,
            'name' => 'Entreprise Exemple',
            'country_code' => 'TN',
            'currency' => 'TND',
        ]);
    }

    public function test_company_activity_must_come_from_the_predefined_or_cabinet_catalog(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $cabinet->activities()->create(['name' => 'Services numériques']);

        $this->actingAs($admin)->post('/companies', [
            'name' => 'Société catalogue',
            'activity' => 'Services numériques',
            'country_code' => 'TN',
            'currency' => 'TND',
        ])->assertRedirect('/companies');

        $this->assertDatabaseHas('companies', [
            'cabinet_id' => $cabinet->id,
            'name' => 'Société catalogue',
            'activity' => 'Services numériques',
        ]);

        $this->actingAs($admin)->from('/companies')->post('/companies', [
            'name' => 'Société activité inconnue',
            'activity' => 'Activité non enregistrée',
        ])->assertSessionHasErrors('activity');
    }

    public function test_company_manager_cannot_create_a_company(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post('/companies', ['name' => 'Interdite'])->assertForbidden();
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_cabinet_administrator_can_update_a_company_profile(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($admin)->put("/companies/{$company->id}", [
            'name' => 'Nom mis à jour',
            'legal_name' => 'Nom légal mis à jour',
            'tax_identifier' => '9876543Z',
            'activity' => 'Services professionnels et conseil',
            'sector' => 'Services',
            'country_code' => 'tn',
            'currency' => 'tnd',
        ])->assertRedirect('/companies');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'cabinet_id' => $cabinet->id,
            'name' => 'Nom mis à jour',
            'country_code' => 'TN',
            'currency' => 'TND',
        ]);
    }

    public function test_administrator_cannot_update_a_company_in_another_cabinet(): void
    {
        $admin = User::factory()->cabinetAdmin()->create();
        $foreignCompany = Company::factory()->create();

        $this->actingAs($admin)->put("/companies/{$foreignCompany->id}", [
            'name' => 'Accès interdit',
        ])->assertForbidden();
    }

    public function test_company_list_is_limited_to_company_user_access(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $visible = Company::factory()->create(['cabinet_id' => $cabinet->id, 'name' => 'Visible']);
        Company::factory()->create(['cabinet_id' => $cabinet->id, 'name' => 'Non affectée']);
        $foreignCompany = Company::factory()->create(['name' => 'Autre cabinet']);
        $user->companies()->attach($visible, ['role' => User::COMPANY_ROLE_INVOICE_MANAGER]);
        $user->companies()->attach($foreignCompany, ['role' => User::COMPANY_ROLE_INVOICE_MANAGER]);

        $this->actingAs($user)
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Companies/Index')
                ->has('companies', 1)
                ->where('companies.0.name', 'Visible'));
    }

    public function test_cabinet_administrator_can_create_a_user_with_company_level_roles(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($admin)->post('/cabinet/users', [
            'name' => 'Collaboratrice',
            'email' => 'collaboratrice@example.test',
            'password' => 'mot-de-passe-solide',
            'cabinet_role' => User::CABINET_ROLE_MEMBER,
            'company_access' => [
                ['company_id' => $company->id, 'role' => User::COMPANY_ROLE_INVOICE_MANAGER],
            ],
        ])->assertRedirect('/cabinet/users');

        $user = User::query()->where('email', 'collaboratrice@example.test')->firstOrFail();
        $this->assertSame($cabinet->id, $user->cabinet_id);
        $this->assertDatabaseHas('company_user_access', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role' => User::COMPANY_ROLE_INVOICE_MANAGER,
        ]);
    }

    public function test_company_access_cannot_be_granted_to_a_company_in_another_cabinet(): void
    {
        $cabinet = Cabinet::factory()->create();
        $otherCabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $foreignCompany = Company::factory()->create(['cabinet_id' => $otherCabinet->id]);

        $this->actingAs($admin)->from('/cabinet/users')->post('/cabinet/users', [
            'name' => 'Collaborateur',
            'email' => 'collaborateur@example.test',
            'password' => 'mot-de-passe-solide',
            'cabinet_role' => User::CABINET_ROLE_MEMBER,
            'company_access' => [
                ['company_id' => $foreignCompany->id, 'role' => User::COMPANY_ROLE_USER],
            ],
        ])->assertSessionHasErrors('company_access');

        $this->assertDatabaseMissing('users', ['email' => 'collaborateur@example.test']);
    }

    public function test_cabinet_administrator_can_update_a_members_company_access(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $member = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($admin)->put("/cabinet/users/{$member->id}", [
            'name' => $member->name,
            'email' => $member->email,
            'password' => '',
            'cabinet_role' => User::CABINET_ROLE_MEMBER,
            'company_access' => [
                ['company_id' => $company->id, 'role' => User::COMPANY_ROLE_INVOICE_MANAGER],
            ],
        ])->assertRedirect('/cabinet/users');

        $this->assertDatabaseHas('company_user_access', [
            'user_id' => $member->id,
            'company_id' => $company->id,
            'role' => User::COMPANY_ROLE_INVOICE_MANAGER,
        ]);
    }

    public function test_promoting_a_member_to_cabinet_admin_clears_company_assignments(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $member = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $member->companies()->attach($company, ['role' => User::COMPANY_ROLE_INVOICE_MANAGER]);

        $this->actingAs($admin)->put("/cabinet/users/{$member->id}", [
            'name' => $member->name,
            'email' => $member->email,
            'password' => '',
            'cabinet_role' => User::CABINET_ROLE_ADMIN,
            'company_access' => [
                ['company_id' => $company->id, 'role' => User::COMPANY_ROLE_INVOICE_MANAGER],
            ],
        ])->assertRedirect('/cabinet/users');

        $this->assertSame(User::CABINET_ROLE_ADMIN, $member->fresh()->cabinet_role);
        $this->assertDatabaseMissing('company_user_access', [
            'user_id' => $member->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_administrator_cannot_edit_a_user_in_another_cabinet(): void
    {
        $admin = User::factory()->cabinetAdmin()->create();
        $foreignUser = User::factory()->create();

        $this->actingAs($admin)->put("/cabinet/users/{$foreignUser->id}", [
            'name' => $foreignUser->name,
            'email' => $foreignUser->email,
            'cabinet_role' => User::CABINET_ROLE_MEMBER,
            'company_access' => [],
        ])->assertForbidden();
    }

    public function test_cabinet_cannot_be_left_without_an_administrator(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($admin)->from('/cabinet/users')->put("/cabinet/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => '',
            'cabinet_role' => User::CABINET_ROLE_MEMBER,
            'company_access' => [],
        ])->assertSessionHasErrors('cabinet_role');

        $this->assertSame(User::CABINET_ROLE_ADMIN, $admin->fresh()->cabinet_role);
    }

    public function test_non_admin_cannot_manage_cabinet_users(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/cabinet/users')->assertForbidden();
    }
}
