<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
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

    public function test_generate_suggested_replaces_previous_items_in_page_props(): void
    {
        $fx = $this->createSurveyFixture();
        $this->seedNr1OverallAndSectionResult($fx, 'yellow', 3.0, 'Demandas');

        $plan = ActionPlan::query()->create([
            'company_id' => $fx->company->id,
            'survey_id' => $fx->survey->id,
            'status' => 'open',
            'admin_published_at' => null,
        ]);

        ActionPlanItem::query()->create([
            'action_plan_id' => $plan->id,
            'title' => 'Item antigo que deve sumir',
            'description' => 'Texto curto da primeira geração.',
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->actingAs($admin)
            ->from(route('admin.companies.surveys.action-plan.edit', [$fx->company, $fx->survey]))
            ->post(route('admin.companies.surveys.action-plan.generate-suggested', [$fx->company, $fx->survey]))
            ->assertRedirect(route('admin.companies.surveys.action-plan.edit', [$fx->company, $fx->survey]));

        $this->assertDatabaseMissing('action_plan_items', [
            'action_plan_id' => $plan->id,
            'title' => 'Item antigo que deve sumir',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.companies.surveys.action-plan.edit', [$fx->company, $fx->survey]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ActionPlan/Edit')
                ->has('items', 1)
                ->where('items.0.title', fn (string $title) => str_contains($title, 'Demandas')
                    && ! str_contains($title, 'Item antigo que deve sumir'))
                ->where('items.0.description', fn (string $description) => str_contains($description, 'O que deve ser feito:')
                    && ! str_contains($description, 'Texto curto da primeira geração.'))
            );
    }
}
