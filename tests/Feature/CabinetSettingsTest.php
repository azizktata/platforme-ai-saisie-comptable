<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CabinetSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cabinet_administrator_can_view_and_update_cabinet_settings(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($admin)
            ->get('/cabinet/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cabinet/Settings')
                ->where('cabinet.id', $cabinet->id)
                ->where('cabinet.name', $cabinet->name));

        $this->actingAs($admin)->patch('/cabinet/settings', [
            'name' => ' Cabinet Mis à Jour ',
            'slug' => ' MON-CABINET_2026 ',
        ])->assertRedirect(route('cabinet.settings.edit'));

        $this->assertDatabaseHas('cabinets', [
            'id' => $cabinet->id,
            'name' => 'Cabinet Mis à Jour',
            'slug' => 'mon-cabinet_2026',
        ]);
    }

    public function test_only_a_cabinet_administrator_can_manage_settings(): void
    {
        $cabinet = Cabinet::factory()->create();
        $member = User::factory()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($member)->get('/cabinet/settings')->assertForbidden();
        $this->actingAs($member)->patch('/cabinet/settings', [
            'name' => 'Interdit',
            'slug' => 'interdit',
        ])->assertForbidden();
    }

    public function test_cabinet_slug_must_be_unique(): void
    {
        $cabinet = Cabinet::factory()->create();
        $otherCabinet = Cabinet::factory()->create(['slug' => 'already-used']);
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);

        $this->actingAs($admin)->from('/cabinet/settings')->patch('/cabinet/settings', [
            'name' => 'Nom inchangé',
            'slug' => $otherCabinet->slug,
        ])->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('cabinets', ['id' => $cabinet->id, 'slug' => $cabinet->slug]);
    }
}
