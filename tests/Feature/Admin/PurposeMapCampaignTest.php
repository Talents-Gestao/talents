<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\PurposeMapCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class PurposeMapCampaignTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    public function test_admin_menu_opens_dashboard_with_questions_url(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = Company::query()->create([
            'name' => 'Escritório Pasqualino',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);

        $this->actingAs($admin)
            ->get(route('admin.mapa-proposito.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/PurposeMap/Show')
                ->has('dashboard')
                ->has('questionsUrl')
            );

        $this->assertTrue(PurposeMapCampaign::query()->where('status', 'active')->exists());
    }
}
