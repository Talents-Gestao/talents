<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\SeedsNr1SurveyResults;
use Tests\TestCase;

class ActionPlanGenerateSuggestedTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;
    use SeedsNr1SurveyResults;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_action_plan_edit_page_does_not_expose_plan_items(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0, 'Demandas');

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->get(route('nr1.parecer', ['empresa' => $fx->company->id, 'campanha' => $fx->survey->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Nr1/Section')
                ->missing('section.legacy.documents.laudo.items')
                ->missing('section.legacy.documents.laudo.generateSuggestedPlanUrl')
                ->has('section.legacy.documents.laudo.technical_opinion')
            );
    }
}
