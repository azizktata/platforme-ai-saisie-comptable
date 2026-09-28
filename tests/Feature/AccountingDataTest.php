<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingData\SeedDemoAccountingData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountingDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_company_scoped_repeatable_and_uses_balanced_entries(): void
    {
        $company = Company::factory()->create();
        $seedDemoAccountingData = app(SeedDemoAccountingData::class);

        $seedDemoAccountingData->handle($company);

        $this->assertDatabaseHas('chart_accounts', [
            'company_id' => $company->id,
            'code' => '000012',
        ]);
        $this->assertDatabaseCount('chart_accounts', 12);
        $this->assertDatabaseCount('analytical_accounts', 3);
        $this->assertDatabaseCount('third_parties', 3);
        $this->assertDatabaseCount('journals', 4);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('journal_entry_lines', 6);

        foreach (JournalEntry::query()->with('lines')->get() as $entry) {
            $debitMillimes = $entry->lines->sum(fn ($line): int => (int) round((float) $line->debit * 1000));
            $creditMillimes = $entry->lines->sum(fn ($line): int => (int) round((float) $line->credit * 1000));

            $this->assertSame($debitMillimes, $creditMillimes);
        }

        $seedDemoAccountingData->handle($company);

        $this->assertDatabaseCount('chart_accounts', 12);
        $this->assertDatabaseCount('analytical_accounts', 3);
        $this->assertDatabaseCount('third_parties', 3);
        $this->assertDatabaseCount('journals', 4);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('journal_entry_lines', 6);
    }

    public function test_company_user_can_view_their_company_data_but_not_another_company(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $visibleCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $hiddenCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $user->companies()->attach($visibleCompany, ['role' => User::COMPANY_ROLE_USER]);

        $seedDemoAccountingData = app(SeedDemoAccountingData::class);
        $seedDemoAccountingData->handle($visibleCompany);
        $seedDemoAccountingData->handle($hiddenCompany);

        $this->actingAs($user)
            ->get("/companies/{$visibleCompany->id}/accounting-data")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AccountingData/Show')
                ->where('company.id', $visibleCompany->id)
                ->has('companies', 1)
                ->where('companies.0.id', $visibleCompany->id)
                ->has('chartAccounts.data', 12)
                ->has('analyticalAccounts.data', 3)
                ->has('thirdParties.data', 3)
                ->has('journals.data', 4)
                ->has('entries.data', 2)
                ->where('canLoadDemoData', false));

        $this->actingAs($user)
            ->get("/companies/{$hiddenCompany->id}/accounting-data")
            ->assertForbidden();
    }

    public function test_invoice_manager_can_load_demo_data_but_company_user_cannot(): void
    {
        $cabinet = Cabinet::factory()->create();
        $manager = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $reader = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $manager->companies()->attach($company, ['role' => User::COMPANY_ROLE_INVOICE_MANAGER]);
        $reader->companies()->attach($company, ['role' => User::COMPANY_ROLE_USER]);

        $this->actingAs($reader)
            ->post("/companies/{$company->id}/accounting-data/demo")
            ->assertForbidden();
        $this->assertDatabaseCount('chart_accounts', 0);

        $this->actingAs($manager)
            ->post("/companies/{$company->id}/accounting-data/demo")
            ->assertRedirect(route('companies.accounting-data', $company));

        $this->assertDatabaseCount('chart_accounts', 12);
        $this->assertDatabaseHas('chart_accounts', [
            'company_id' => $company->id,
            'code' => '000012',
        ]);
    }

    public function test_global_accounting_workspace_selects_only_companies_accessible_to_the_user(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $firstCompany = Company::factory()->create(['cabinet_id' => $cabinet->id, 'name' => 'A Société']);
        $secondCompany = Company::factory()->create(['cabinet_id' => $cabinet->id, 'name' => 'B Société']);

        $this->actingAs($admin)
            ->get('/accounting-data')
            ->assertRedirect(route('accounting-data.index', ['company_id' => $firstCompany->id]));

        $this->actingAs($admin)
            ->get(route('accounting-data.index', ['company_id' => $secondCompany->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AccountingData/Show')
                ->where('company.id', $secondCompany->id)
                ->has('companies', 2));

        $member = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $foreignCompany = Company::factory()->create();
        $member->companies()->attach($firstCompany, ['role' => User::COMPANY_ROLE_USER]);
        $member->companies()->attach($foreignCompany, ['role' => User::COMPANY_ROLE_USER]);

        $this->actingAs($member)
            ->get(route('accounting-data.index', ['company_id' => $secondCompany->id]))
            ->assertNotFound();

        $this->actingAs($member)
            ->get(route('accounting-data.index', ['company_id' => $foreignCompany->id]))
            ->assertNotFound();

        $this->actingAs($member)
            ->get('/accounting-data')
            ->assertRedirect(route('accounting-data.index', ['company_id' => $firstCompany->id]));
    }
}
