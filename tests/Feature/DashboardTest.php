<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_and_recent_documents_are_scoped_to_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->documents()->create([
            'original_filename' => 'ma-facture.pdf',
            'status' => Document::STATUS_NEEDS_REVIEW,
        ]);
        $otherUser->documents()->create([
            'original_filename' => 'autre-facture.pdf',
            'status' => Document::STATUS_NEEDS_REVIEW,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.to_review', 1)
                ->has('recentDocuments', 1)
                ->where('recentDocuments.0.original_filename', 'ma-facture.pdf')
                ->has('monthlySpend', 6));
    }
}
