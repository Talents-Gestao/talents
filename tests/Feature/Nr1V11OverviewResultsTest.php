<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\ApproverOrganization;
use App\Models\PsychosocialInternalReview;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\User;
use App\Services\PsychosocialMeasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Etapa 1 (telas): Visão geral com "Próximas ações", Resultados com filtros e a Revisão interna da Talents (Caderno §9),
 * que só a consultoria enxerga.
 */
class Nr1V11OverviewResultsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private const INTERNAL_NOTE = 'anotacao-interna-secreta-da-consultoria';

    /**
     * @return object{company: mixed, survey: Survey, talents: User, admin: User}
     */
    private function campaign(bool $published = true, bool $withResponses = true): object
    {
        $campaign = $this->v11Campaign($withResponses);
        $campaign->survey->forceFill([
            'requires_publication' => true,
            'published_at' => $published ? now() : null,
        ])->save();

        $talents = User::factory()->talentsOwner()->mfaVerified()->create(['name' => 'Talents Owner']);
        $admin = User::factory()->companyAdmin($campaign->company->id)->create();

        return (object) ['company' => $campaign->company, 'survey' => $campaign->survey->fresh(), 'talents' => $talents, 'admin' => $admin];
    }

    private function measure(Survey $survey, User $talents, string $factor, bool $confirm = false): PsychosocialMeasure
    {
        $measure = app(PsychosocialMeasureService::class)->createManual($survey, [
            'factor_label' => $factor,
            'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
            'measure_description' => "Medida de {$factor}.",
            'responsible_text' => 'Gestão de operações',
            'suggested_due_date' => now()->addDays(30)->toDateString(),
        ], $talents);

        return $measure;
    }

    private function reviewUrl(object $c): string
    {
        return route('admin.companies.surveys.internal-review.store', [$c->company->id, $c->survey->id]);
    }

    public function test_overview_shows_the_first_three_open_actions_with_the_chip_of_the_approved_html(): void
    {
        $c = $this->campaign();
        foreach (['Demandas', 'Autonomia', 'Relacionamentos', 'Reconhecimento'] as $factor) {
            $this->measure($c->survey, $c->talents, $factor);
        }

        $this->actingAs($c->admin)
            ->get(route('client.surveys.psychosocial-dashboard', $c->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Surveys/PsychosocialDashboard')
                ->has('nextActions', 3)
                ->where('nextActions.0.factor', 'Demandas')
                ->where('nextActions.0.chip', 'A confirmar')
                ->where('nextActions.0.status_label', 'Proposta')
                ->where('nextActions.0.confirmed', false)
                ->has('dashboard.groups.items')
                ->has('dashboard.attention'));
    }

    public function test_overview_next_actions_show_confirmed_responsible_and_skip_completed_measures(): void
    {
        $c = $this->campaign();
        $done = $this->measure($c->survey, $c->talents, 'Concluída');
        $done->forceFill(['status' => PsychosocialMeasure::STATUS_IMPLEMENTED, 'implemented_at' => now(), 'implemented_by' => $c->talents->id])->save();
        $open = $this->measure($c->survey, $c->talents, 'Aberta');
        $open->forceFill([
            'status' => PsychosocialMeasure::STATUS_PENDING,
            'confirmed_at' => now(),
            'confirmed_by' => $c->admin->id,
            'confirmed_responsible_user_id' => $c->admin->id,
            'confirmed_due_date' => now()->addDays(10)->toDateString(),
        ])->save();

        $this->actingAs($c->admin)
            ->get(route('client.surveys.psychosocial-dashboard', $c->survey))
            ->assertInertia(fn (Assert $page) => $page
                ->has('nextActions', 1)
                ->where('nextActions.0.factor', 'Aberta')
                ->where('nextActions.0.chip', 'Responsável confirmado')
                ->where('nextActions.0.status_label', 'Confirmada')
                ->where('nextActions.0.responsible', $c->admin->name));
    }

    public function test_empty_state_when_there_are_no_responses_and_no_plan(): void
    {
        $c = $this->campaign(withResponses: false);

        $this->actingAs($c->admin)
            ->get(route('client.surveys.psychosocial-dashboard', $c->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('nextActions', 0)
                ->where('dashboard.has_results', false)
                ->where('dashboard.respondents', 0)
                ->has('dashboard.attention', 0)
                ->where('dashboard.items.0.n', 0)
                ->where('dashboard.items.0.favorable_pct', null));
    }

    public function test_results_campaign_filter_lists_only_published_campaigns_of_the_same_company_for_the_client(): void
    {
        $c = $this->campaign();
        $second = $c->survey->replicate(['public_token', 'published_at']);
        $second->forceFill(['title' => 'Segunda campanha', 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'published_at' => now()])->save();
        $hidden = $c->survey->replicate(['public_token', 'published_at']);
        $hidden->forceFill(['title' => 'Campanha não publicada', 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'published_at' => null])->save();

        $otherCompany = $this->v11Campaign(false);
        $otherCompany->survey->forceFill(['title' => 'De outra empresa', 'requires_publication' => true, 'published_at' => now()])->save();

        $this->actingAs($c->admin)
            ->get(route('client.surveys.psychosocial-dashboard', $c->survey))
            ->assertInertia(function (Assert $page) use ($c, $second, $hidden, $otherCompany) {
                $page->has('campaigns', 2);
                $titles = collect($page->toArray()['props']['campaigns'])->pluck('title')->all();
                $this->assertContains('Segunda campanha', $titles);
                $this->assertNotContains('Campanha não publicada', $titles);
                $this->assertNotContains('De outra empresa', $titles);
                $this->assertSame([$second->id, $c->survey->id], collect($page->toArray()['props']['campaigns'])->pluck('id')->sortDesc()->values()->all());
            });

        // A Talents vê também a não publicada.
        $this->actingAs($c->talents)
            ->get(route('admin.companies.surveys.results.show', [$c->company->id, $c->survey->id]))
            ->assertInertia(fn (Assert $page) => $page->has('campaigns', 3));
    }

    public function test_internal_review_is_saved_with_history_and_never_creates_anything_else(): void
    {
        $c = $this->campaign(published: false);
        $measures = PsychosocialMeasure::query()->count();

        $this->actingAs($c->talents)->get(route('admin.companies.surveys.results.show', [$c->company->id, $c->survey->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Surveys/PsychosocialResults')
                ->where('published', null)
                ->where('internalReview.current', null)
                ->has('internalReview.items')
                ->where('internalReview.decisions.documental', 'Solicitar informação ou documento')
                ->where('internalReview.decisions.entrevistas', 'Sugerir avaliação complementar')
                ->where('internalReview.decisions.suficiente', 'Informações suficientes para esta etapa'));

        $this->actingAsMfa($c->talents)->post($this->reviewUrl($c), [
            'decision' => 'documental',
            'note' => self::INTERNAL_NOTE,
            'checked_items' => ['work_demands', 'item-inexistente'],
        ])->assertSessionHas('success');

        $this->actingAsMfa($c->talents)->post($this->reviewUrl($c), ['decision' => 'suficiente', 'note' => '', 'checked_items' => []])
            ->assertSessionHas('success');

        $rows = PsychosocialInternalReview::query()->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['documental', $c->talents->id, ['work_demands']], [$rows[0]->decision, $rows[0]->user_id, $rows[0]->checked_items]);
        $this->assertSame(self::INTERNAL_NOTE, $rows[0]->note);

        $this->actingAs($c->talents)->get(route('admin.companies.surveys.results.show', [$c->company->id, $c->survey->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('internalReview.current.decision', 'suficiente')
                ->has('internalReview.history', 2)
                ->where('internalReview.history.1.note', self::INTERNAL_NOTE)
                ->where('internalReview.history.1.author', 'Talents Owner'));

        $this->assertSame($measures, PsychosocialMeasure::query()->count(), 'Revisão interna não gera medida, contratação nem oportunidade.');

        foreach ([
            fn () => $rows[0]->fill(['note' => 'x'])->save(),
            fn () => $rows[0]->delete(),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Análise interna foi alterada.');
            } catch (LogicException) {
            }
        }
    }

    public function test_internal_review_validates_encaminhamento_and_requires_a_note_when_something_is_requested(): void
    {
        $c = $this->campaign();

        $this->actingAsMfa($c->talents)->post($this->reviewUrl($c), ['decision' => 'qualquer', 'note' => 'x'])->assertSessionHasErrors('decision');
        $this->actingAsMfa($c->talents)->post($this->reviewUrl($c), ['note' => 'x'])->assertSessionHasErrors('decision');

        $this->actingAsMfa($c->talents)->post($this->reviewUrl($c), ['decision' => 'entrevistas', 'note' => ''])->assertSessionHas('error');
        $this->actingAsMfa($c->talents)->post($this->reviewUrl($c), ['decision' => 'documental', 'note' => 'ok'])->assertSessionHas('error');
        $this->assertSame(0, PsychosocialInternalReview::query()->count());

        $this->actingAsMfa($c->talents)->post($this->reviewUrl($c), ['decision' => 'entrevistas', 'note' => 'Sugerir avaliação complementar com o setor.'])->assertSessionHas('success');
        $this->assertSame(1, PsychosocialInternalReview::query()->count());
    }

    public function test_internal_review_lists_one_item_per_dimension_in_attention(): void
    {
        $c = $this->campaign();

        $this->actingAs($c->talents)->get(route('admin.companies.surveys.results.show', [$c->company->id, $c->survey->id]))
            ->assertInertia(function (Assert $page) {
                $props = $page->toArray()['props'];
                $keys = collect($props['internalReview']['items'])->pluck('key');
                $this->assertContains('work_demands', $keys);
                $this->assertContains('existing_measures', $keys);
                $this->assertSame(count($props['dashboard']['attention']), $keys->filter(fn ($k) => str_starts_with($k, 'dimension:'))->count());
            });
    }

    public function test_internal_review_never_reaches_the_company_the_reviewer_or_commercial(): void
    {
        $c = $this->campaign();
        $this->actingAsMfa($c->talents)->post($this->reviewUrl($c), ['decision' => 'documental', 'note' => self::INTERNAL_NOTE])->assertSessionHas('success');

        // Empresa: o painel não carrega nada da revisão interna, nem o texto.
        $response = $this->actingAs($c->admin)->get(route('client.surveys.psychosocial-dashboard', $c->survey))->assertOk();
        $this->assertStringNotContainsString(self::INTERNAL_NOTE, $response->getContent());
        $this->assertStringNotContainsString('internalReview', $response->getContent());
        $this->assertStringNotContainsString('Revisão interna da Talents', $response->getContent());
        foreach ([
            route('client.surveys.documents.index', $c->survey),
            route('client.surveys.measures.index', $c->survey),
        ] as $url) {
            $this->assertStringNotContainsString(self::INTERNAL_NOTE, $this->actingAs($c->admin)->get($url)->getContent());
        }

        // Empresa não alcança a rota da consultoria.
        $this->actingAs($c->admin)->post($this->reviewUrl($c), ['decision' => 'suficiente'])->assertForbidden();
        $this->actingAs($c->admin)->get(route('admin.companies.surveys.results.show', [$c->company->id, $c->survey->id]))->assertForbidden();

        // PASSEG (revisor externo).
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        $reviewer = User::factory()->externalReviewer($organization->id)->create();
        $this->actingAs($reviewer)->post($this->reviewUrl($c), ['decision' => 'suficiente'])->assertForbidden();
        $this->actingAs($reviewer)->get(route('admin.companies.surveys.results.show', [$c->company->id, $c->survey->id]))->assertForbidden();

        // Comercial limitado (só o módulo de oportunidades) e super admin sem permissão.
        $commercial = User::factory()->superAdmin()->create(['is_owner' => false]);
        AdminUserPermission::query()->create([
            'user_workspace_id' => $commercial->workspaces()->firstOrFail()->id,
            'module' => AdminPermissionModule::Nr1Oportunidades,
            'action' => PermissionAction::View,
        ]);
        $this->actingAs($commercial)->post($this->reviewUrl($c), ['decision' => 'suficiente'])->assertForbidden();
        $this->actingAs($commercial)->get(route('admin.companies.surveys.results.show', [$c->company->id, $c->survey->id]))->assertForbidden();

        $plain = User::factory()->superAdmin()->create(['is_owner' => false]);
        $this->actingAs($plain)->post($this->reviewUrl($c), ['decision' => 'suficiente'])->assertForbidden();

        $this->assertSame(1, PsychosocialInternalReview::query()->count());
    }

    public function test_results_page_of_talents_is_scoped_to_its_company_and_to_v11_campaigns(): void
    {
        $c = $this->campaign();

        $this->actingAs($c->talents)->get(route('admin.companies.surveys.results.show', [$c->company->id + 999, $c->survey->id]))->assertNotFound();

        $old = $this->createSurveyFixture();
        $oldSurvey = $old->survey ?? $old;
        $this->actingAs($c->talents)
            ->get(route('admin.companies.surveys.results.show', [$oldSurvey->company_id, $oldSurvey->id]))
            ->assertNotFound();
    }
}
