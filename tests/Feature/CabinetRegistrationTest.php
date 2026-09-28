<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CabinetRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_a_cabinet_and_become_its_first_administrator(): void
    {
        Cabinet::factory()->create(['slug' => 'cabinet-conseil']);

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));

        $this->post('/register', [
            'cabinet_name' => ' Cabinet Conseil ',
            'name' => ' Nadia Comptable ',
            'email' => ' NADIA@EXAMPLE.TEST ',
            'password' => 'un-mot-de-passe-solide',
            'password_confirmation' => 'un-mot-de-passe-solide',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $cabinet = Cabinet::query()->where('slug', 'cabinet-conseil-2')->firstOrFail();
        $admin = User::query()->where('email', 'nadia@example.test')->firstOrFail();

        $this->assertSame('Cabinet Conseil', $cabinet->name);
        $this->assertSame('Nadia Comptable', $admin->name);
        $this->assertSame($cabinet->id, $admin->cabinet_id);
        $this->assertSame(User::CABINET_ROLE_ADMIN, $admin->cabinet_role);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_registration_requires_a_confirmed_password_of_at_least_twelve_characters(): void
    {
        $this->from('/register')->post('/register', [
            'cabinet_name' => 'Cabinet Exemple',
            'name' => 'Nadia',
            'email' => 'nadia@example.test',
            'password' => 'trop-court',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password']);

        $this->assertGuest();
        $this->assertDatabaseCount('cabinets', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
