<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\PsychosocialMeasureService;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Shell NR-1, Etapa C: Resultados (Dimensões / Setores / 39 perguntas + Revisão interna) e Plano de ação.
 */
class Nr1ShellStageCTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['nr1.shell_enabled' => true]);
    }

    /** @return object{company: Company, survey: Survey, owner: User, admin: User} */
    private function campaign(bool $published = true): object
    {
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => $published ? now() : null])->save();

        return (object) [
            'company' => $c->company,
            'survey' => $c->survey->fresh(),
            'owner' => User::factory()->talentsOwner()->create(),
            'admin' => User::factory()->companyAdmin($c->company->id)->create(),
        ];
    }

    /** Distribui as 40 respostas em setores: Produção (22), Manual (6) e três setores pequenos (4, 4 e 4). */
    private function withDepartments(object $c): void
    {
        $names = ['Produção' => 22, 'Manual' => 6, 'Costura' => 4, 'Portaria' => 4, 'Limpeza' => 4];
        $ids = [];
        foreach ($names as $name => $count) {
            $id = Department::query()->create(['company_id' => $c->company->id, 'name' => $name])->id;
            $ids = array_merge($ids, array_fill(0, $count, $id));
        }
        SurveyResponse::query()->where('survey_id', $c->survey->id)->orderBy('id')->get()->each(fn (SurveyResponse $r, int $i) => $r->update(['department_id' => $ids[$i]]));
        app(SurveyResultCalculator::class)->recalculate($c->survey->fresh());
    }

    public function test_results_carry_the_three_tabs_with_adopted_band_labels_and_the_internal_review_only_for_talents(): void
    {
        $c = $this->campaign();

        $this->actingAs($c->owner)->get(route('nr1.resultados'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.results.hasResults', true)
            ->has('section.results.dimensions', 7)
            ->has('section.results.items', 39)
            ->where('section.results.bands.0.label', 'Menor sinalização')
            ->where('section.results.bands.1.label', 'Atenção')
            ->where('section.results.bands.2.label', 'Maior sinalização')
            ->where('section.results.bands.0.range', '1,00–2,33')
            ->where('section.results.internalReview.save_url', route('admin.companies.surveys.internal-review.store', [$c->company->id, $c->survey->id]))
            ->has('section.results.internalReview.items')
            ->where('section.results.internalReview.decisions.documental', 'Solicitar informação ou documento')
            ->where('section.results.items.0.position', 1)
            ->has('section.results.items.0.distribution'));

        // A empresa nunca recebe a Revisão interna.
        $page = $this->actingAs($c->admin)->get(route('nr1.resultados'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.results.internalReview', null)->has('section.results.items', 39));
        $json = (string) json_encode($page->viewData('page')['props']['section']['results']);
        $this->assertStringNotContainsString('save_url', $json);
        $this->assertStringNotContainsString('checked_items', $json);
        $this->assertStringNotContainsString('Somente consultoria', $json);
    }

    public function test_sectors_pool_small_groups_into_other_sectors_and_never_expose_a_small_sector_name(): void
    {
        $c = $this->campaign();
        $this->withDepartments($c);

        foreach ([$c->owner, $c->admin] as $user) {
            $props = $this->actingAs($user)->get(route('nr1.resultados'))->assertOk()->viewData('page')['props']['section']['results'];
            $names = array_column($props['sectors'], 'name');

            $this->assertContains('Produção', $names);
            $this->assertContains('Manual', $names);
            $this->assertContains('Outros Setores', $names);
            $this->assertSame(40, array_sum(array_column($props['sectors'], 'n')), 'Todas as respostas contabilizadas.');

            $json = (string) json_encode($props);
            foreach (['Costura', 'Portaria', 'Limpeza'] as $small) {
                $this->assertStringNotContainsString($small, $json, 'Setor pequeno nunca aparece isolado.');
            }
            $this->assertStringNotContainsString('department_id', $json);
            $others = collect($props['sectors'])->firstWhere('name', 'Outros Setores');
            $this->assertTrue($others['others']);
            $this->assertSame(12, $others['n']);
            $this->assertCount(7, $others['dimensions']);
        }
    }

    public function test_the_plan_shows_suggested_versus_confirmed_and_the_talents_edit_stops_at_publication(): void
    {
        $c = $this->campaign(published: false);
        $measure = app(PsychosocialMeasureService::class)->createEngineSuggestion($c->survey, [
            'factor_label' => 'Demandas e Sobrecarga', 'measure_description' => 'Revisar volume e ritmo.', 'suggested_due_date' => now()->addDays(30)->toDateString(), 'sort_order' => 1,
        ]);
        app(PsychosocialMeasureService::class)->createEngineSuggestion($c->survey, ['factor_label' => 'Liderança e Gestão', 'measure_description' => 'Desenvolver líderes.', 'sort_order' => 2]);

        // Antes de publicar: Talents edita a sugestão; a empresa nem enxerga o plano.
        $this->actingAs($c->owner)->get(route('nr1.plano-de-acao'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.plan.counters.total', 2)->where('section.plan.counters.confirmed', 0)->where('section.plan.counters.pending', 2)
            ->where('section.plan.canEditSuggestion', true)
            ->where('section.plan.measures.0.urls.suggestion', route('admin.companies.surveys.measures.suggestion', [$c->company->id, $c->survey->id, $measure->id]))
            ->where('section.plan.measures.0.timeline.0.state', 'current'));
        $this->actingAs($c->admin)->get(route('nr1.plano-de-acao'))->assertInertia(fn (Assert $p) => $p->where('context.survey', null)->missing('section.plan'));

        // Publicada: a sugestão não é mais editável pela Talents e a empresa confirma e inicia pelas rotas existentes.
        $c->survey->forceFill(['published_at' => now()])->save();
        $this->actingAs($c->owner)->get(route('nr1.plano-de-acao'))->assertInertia(fn (Assert $p) => $p
            ->where('section.plan.canEditSuggestion', false)->where('section.plan.measures.0.urls.suggestion', null));

        $this->actingAs($c->admin)->get(route('nr1.plano-de-acao'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.plan.canAct', true)
            ->where('section.plan.measures.0.urls.confirm', route('client.surveys.measures.confirm', [$c->survey->id, $measure->id]))
            ->where('section.plan.measures.0.urls.start', null)
            ->where('section.plan.measures.0.confirmed', null));

        $this->actingAs($c->admin)->from(route('nr1.plano-de-acao'))
            ->post(route('client.surveys.measures.confirm', [$c->survey->id, $measure->id]), ['responsible_text' => 'Diretoria', 'due_date' => now()->addDays(20)->toDateString()])
            ->assertRedirect(route('nr1.plano-de-acao'));

        $this->actingAs($c->admin)->get(route('nr1.plano-de-acao'))->assertInertia(fn (Assert $p) => $p
            ->where('section.plan.counters.confirmed', 1)->where('section.plan.counters.pending', 1)
            ->where('section.plan.measures.0.status', PsychosocialMeasure::STATUS_PENDING)
            ->where('section.plan.measures.0.confirmed.responsible', 'Diretoria')
            ->where('section.plan.measures.0.suggested.responsible', null)
            ->where('section.plan.measures.0.urls.confirm', null)
            ->where('section.plan.measures.0.urls.start', route('client.surveys.measures.start', [$c->survey->id, $measure->id]))
            ->where('section.plan.measures.0.timeline.1.state', 'current'));
    }

    public function test_the_plan_and_results_payloads_follow_the_shell_matrix_for_other_profiles(): void
    {
        $c = $this->campaign();
        $companyUser = User::factory()->companyUser($c->company->id)->create();

        // company_user sem permissão não recebe nem a rota (a matriz vale no back-end).
        $this->actingAs($companyUser)->get(route('nr1.resultados'))->assertForbidden();
        $this->actingAs($companyUser)->get(route('nr1.plano-de-acao'))->assertForbidden();
    }
}
