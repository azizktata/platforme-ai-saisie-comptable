<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CabinetActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cabinet_administrator_can_add_a_custom_activity_for_their_company_catalog(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($admin)->post('/cabinet/activities', [
            'name' => '  Services   numériques  ',
        ])->assertRedirect(route('companies.index'));

        $this->assertDatabaseHas('cabinet_activities', [
            'cabinet_id' => $cabinet->id,
            'name' => 'Services numériques',
        ]);

        $this->actingAs($admin)
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Companies/Index')
                ->where('canManageActivities', true)
                ->where('activities.'.count(config('company_activities')), 'Services numériques'));
    }

    public function test_custom_activities_are_private_to_their_cabinet(): void
    {
        $firstCabinet = Cabinet::factory()->create();
        $secondCabinet = Cabinet::factory()->create();
        $firstAdmin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $firstCabinet->id]);
        $secondAdmin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $secondCabinet->id]);
        $firstCabinet->activities()->create(['name' => 'Activité privée']);

        $this->actingAs($secondAdmin)
            ->get('/companies')
            ->assertInertia(fn (Assert $page) => $page
                ->where('activities', config('company_activities')));

        $this->actingAs($firstAdmin)
            ->get('/companies')
            ->assertInertia(fn (Assert $page) => $page
                ->where('activities.'.count(config('company_activities')), 'Activité privée'));
    }

    public function test_only_cabinet_administrators_can_add_activities_and_duplicates_are_rejected(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $member = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $cabinet->activities()->create(['name' => 'Activité personnalisée']);

        $this->actingAs($member)->post('/cabinet/activities', [
            'name' => 'Activité non autorisée',
        ])->assertForbidden();

        $this->actingAs($admin)->from('/companies')->post('/cabinet/activities', [
            'name' => ' commerce de détail ',
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->from('/companies')->post('/cabinet/activities', [
            'name' => ' ACTIVITÉ PERSONNALISÉE ',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('cabinet_activities', 1);
    }
}
