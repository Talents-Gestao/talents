<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\Department;
use App\Models\PsychosocialMeasure;
use App\Models\SurveyAnswer;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplateQuestion;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\Support\Nr1V11Dataset;
use Tests\TestCase;

/**
 * Visão geral (4 cartões e pontos fortes), Campanha NR-1 (etapas, QR, mínimo) e a nova navegação
 * Documentos × «Parecer e publicação», na V11 e no modelo anterior.
 */
class Nr1ShellPanelsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function query(object $c): array
    {
        return ['empresa' => $c->company->id, 'campanha' => $c->survey->id];
    }

    public function test_v11_strengths_are_the_three_most_favorable_questions_with_real_counts_and_stable_tiebreak(): void
    {
        $c = $this->v11Campaign();
        $owner = User::factory()->talentsOwner()->create();

        // Cálculo independente: favorável = Frequentemente/Sempre nos enunciados positivos, Nunca/Raramente nos desfavoráveis.
        $positive = SurveyTemplateQuestion::query()->whereIn('id', $c->questionIds)->pluck('reverse_score', 'id');
        $rows = [];
        foreach (Nr1V11Dataset::COUNTS as $item => $counts) {
            $n = array_sum($counts);
            $favorable = $positive[$c->questionIds[$item]] ? $counts[3] + $counts[4] : $counts[0] + $counts[1];
            $rows[] = ['item' => $item, 'favorable' => $favorable, 'n' => $n, 'pct' => round($favorable / $n * 100, 1)];
        }
        usort($rows, fn ($a, $b) => [$b['pct'], $b['n'], $a['item']] <=> [$a['pct'], $a['n'], $b['item']]);
        $expected = array_slice($rows, 0, 3);

        $page = $this->actingAs($owner)->get(route('nr1.visao-geral', $this->query($c)))->assertOk();
        $overview = $page->viewData('page')['props']['section']['overview'];

        $this->assertSame(array_column($expected, 'item'), array_column($overview['strengths'], 'item'));
        $this->assertSame(array_column($expected, 'favorable'), array_column($overview['strengths'], 'favorable'));
        $this->assertSame(Nr1V11Dataset::RESPONDENTS, $overview['strengthsBase']);
        $this->assertNull($overview['strengthsEmpty']);
        foreach ($overview['strengths'] as $index => $strength) {
            $this->assertSame(number_format($expected[$index]['pct'], 1, ',', '').'%', $strength['pct_label']);
            $this->assertSame(Nr1V11Dataset::RESPONDENTS, $strength['n']);
        }
    }

    public function test_v11_cards_are_honest_without_eligible_registration_and_without_plan(): void
    {
        $c = $this->v11Campaign();
        $owner = User::factory()->talentsOwner()->create();

        $this->actingAs($owner)->get(route('nr1.visao-geral', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->where('section.overview.cards.participation.eligible', null)
            ->where('section.overview.cards.participation.percent_label', null)
            ->where('section.overview.cards.plan.total', 0)
            ->where('section.overview.cards.attention.total', 7)
            ->where('section.overview.cards.attention.count', fn ($count) => $count >= 0 && $count <= 7));

        // Com cadastro técnico e medidas: percentual real e «x / y» com responsável confirmado.
        SurveyCampaignRegistration::query()->updateOrCreate(['survey_id' => $c->survey->id], ['company_id' => $c->company->id, 'eligible_count' => 48]);
        foreach ([now(), null] as $index => $confirmedAt) {
            PsychosocialMeasure::query()->create([
                'survey_id' => $c->survey->id,
                'company_id' => $c->company->id,
                'factor_label' => 'Demandas',
                'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
                'measure_description' => 'Medida '.$index,
                'origin' => PsychosocialMeasure::ORIGIN_MANUAL,
                'status' => $confirmedAt ? PsychosocialMeasure::STATUS_PENDING : PsychosocialMeasure::STATUS_TO_APPROVE,
                'confirmed_at' => $confirmedAt,
                'confirmed_due_date' => $confirmedAt ? now()->addMonth() : null,
                'confirmed_responsible_text' => $confirmedAt ? 'RH' : null,
            ]);
        }

        $this->actingAs($owner)->get(route('nr1.visao-geral', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->where('section.overview.cards.participation.eligible', 48)
            ->where('section.overview.cards.participation.percent_label', '83,3%')
            ->where('section.overview.cards.plan.total', 2)
            ->where('section.overview.cards.plan.confirmed', 1));
    }

    public function test_v11_strengths_have_no_numbers_below_the_minimum_group(): void
    {
        $c = $this->v11Campaign(false);
        $owner = User::factory()->talentsOwner()->create();
        // 3 respostas completas (abaixo do mínimo 5).
        for ($r = 0; $r < 3; $r++) {
            $response = SurveyResponse::query()->create(['survey_id' => $c->survey->id, 'session_token' => (string) Str::uuid(), 'completed_at' => now()]);
            foreach ($c->questionIds as $questionId) {
                SurveyAnswer::query()->create(['survey_response_id' => $response->id, 'survey_template_question_id' => $questionId, 'value' => 5]);
            }
        }
        app(SurveyResultCalculator::class)->recalculate($c->survey->fresh());

        $this->actingAs($owner)->get(route('nr1.visao-geral', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->where('section.overview.strengths', [])
            ->where('section.overview.strengthsBase', null)
            ->where('section.overview.strengthsEmpty', fn ($text) => str_contains($text, 'ao menos 5 respostas')));
    }

    public function test_v11_campaign_shows_steps_minimum_and_qr_while_open(): void
    {
        $c = $this->v11Campaign();
        $owner = User::factory()->talentsOwner()->create();

        $this->actingAs($owner)->get(route('nr1.campanha', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->where('section.campaign.facts.minGroup', 5)
            ->where('section.campaign.facts.steps.0.state', 'done')
            ->where('section.campaign.facts.steps.1.key', 'coleta')
            ->where('section.campaign.facts.steps.1.state', 'current')
            ->where('section.campaign.publicQr', fn ($qr) => str_starts_with((string) $qr, 'data:image/svg+xml;base64,')));

        // Encerrada: sem link nem QR (o QR só existe enquanto a campanha está aberta).
        $c->survey->forceFill(['ends_at' => now()->subDay(), 'status' => 'closed'])->save();
        $this->actingAs($owner)->get(route('nr1.campanha', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->where('section.campaign.publicQr', null)
            ->where('section.campaign.facts.steps.1.state', 'done'));
    }

    public function test_parecer_e_publicacao_only_exists_for_legacy_and_v11_keeps_documents(): void
    {
        $c = $this->v11Campaign();
        $owner = User::factory()->talentsOwner()->create();

        $menu = $this->actingAs($owner)->get(route('nr1.visao-geral', $this->query($c)))->viewData('page')['props']['shell']['menu'];
        $this->assertNotContains('parecer', array_column($menu, 'key'));
        $this->assertContains('documentos', array_column($menu, 'key'));
        $this->actingAs($owner)->get(route('nr1.parecer', $this->query($c)))->assertRedirect('/nr1/documentos?'.http_build_query($this->query($c)));
    }

    private function legacyCampaign(array $overrides = []): object
    {
        $f = $this->createSurveyFixture($overrides);
        $department = Department::query()->create(['company_id' => $f->company->id, 'name' => 'Produção']);
        for ($i = 0; $i < 6; $i++) {
            $response = SurveyResponse::query()->create(['survey_id' => $f->survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $department->id, 'completed_at' => now()]);
            SurveyAnswer::query()->create(['survey_response_id' => $response->id, 'survey_template_question_id' => $f->question->id, 'value' => 4]);
        }
        app(SurveyResultCalculator::class)->recalculate($f->survey->fresh());

        return (object) [
            'company' => $f->company,
            'survey' => $f->survey->fresh(),
            'owner' => User::factory()->talentsOwner()->create(),
            'admin' => User::factory()->companyAdmin($f->company->id)->create(),
        ];
    }

    public function test_legacy_overview_has_the_four_cards_and_an_honest_strengths_state(): void
    {
        $c = $this->legacyCampaign();

        $this->actingAs($c->owner)->get(route('nr1.visao-geral', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->has('section.legacy.overview.cards.overall.score_label')
            ->where('section.legacy.overview.cards.participation.respondents', 6)
            ->where('section.legacy.overview.cards.participation.eligible', null)
            ->where('section.legacy.overview.cards.attention.total', 1)
            ->where('section.legacy.overview.cards.plan', null)
            ->where('section.legacy.overview.cards.planMessage', 'Plano de ação ainda não elaborado no parecer.')
            ->where('section.legacy.overview.strengthsEmpty', fn ($text) => str_contains($text, 'modelo anterior')));

        // Plano do parecer com 2 itens, 1 com responsável: «1 / 2».
        $plan = ActionPlan::query()->create(['company_id' => $c->company->id, 'survey_id' => $c->survey->id]);
        ActionPlanItem::query()->create(['action_plan_id' => $plan->id, 'title' => 'Rodas de conversa', 'responsible_name' => 'RH', 'sort_order' => 1]);
        ActionPlanItem::query()->create(['action_plan_id' => $plan->id, 'title' => 'Revisar metas', 'sort_order' => 2]);

        $this->actingAs($c->owner)->get(route('nr1.visao-geral', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.overview.cards.plan.confirmed', 1)
            ->where('section.legacy.overview.cards.plan.total', 2));
    }

    public function test_legacy_documents_is_the_document_central_and_the_laudo_moved_to_parecer(): void
    {
        $c = $this->legacyCampaign();

        $this->actingAs($c->owner)->get(route('nr1.documentos', $this->query($c)))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('section.legacy.central.documents', 2)
            ->where('section.legacy.central.documents.0.title', 'Relatório executivo')
            ->where('section.legacy.central.documents.1.title', 'Relatório técnico + plano de ação')
            ->where('section.legacy.central.status', 'Rascunho')
            ->where('section.legacy.central.history', [])
            ->where('section.legacy.central.parecerUrl', route('nr1.parecer'))
            ->missing('section.legacy.documents'));

        // Publicado: histórico real, e a empresa recebe os arquivos.
        ActionPlan::query()->create(['company_id' => $c->company->id, 'survey_id' => $c->survey->id, 'admin_published_at' => now()]);
        $this->actingAs($c->owner)->get(route('nr1.documentos', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.central.status', 'Publicado')
            ->where('section.legacy.central.history.0.label', 'Parecer e plano publicados para a empresa'));

        $this->actingAs($c->admin)->get(route('nr1.documentos'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.central.documents.0.url', route('client.surveys.reports.executive', $c->survey->id))
            ->where('section.legacy.central.documents.1.url', route('client.surveys.reports.action-plan', $c->survey->id))
            ->where('section.legacy.central.parecerUrl', null));
    }

    public function test_company_sees_honest_state_before_publication_and_needs_planos_acao_for_parecer(): void
    {
        $c = $this->legacyCampaign();

        $this->actingAs($c->admin)->get(route('nr1.documentos'))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.central.documents.0.url', null)
            ->where('section.legacy.central.documents.1.url', null)
            ->where('section.legacy.central.help', 'Documento em revisão pela Talents.'));
        $this->actingAs($c->admin)->get(route('nr1.plano-de-acao'))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.plan.locked', true)->where('section.legacy.plan.items', []));

        // company_user só com relatórios: vê Documentos, mas não «Parecer e publicação» (exige também planos_acao).
        $reader = User::factory()->companyUser($c->company->id)->create();
        foreach ([PermissionModule::Relatorios, PermissionModule::Pesquisas] as $module) {
            UserPermission::query()->create(['user_workspace_id' => $reader->workspaces()->firstOrFail()->id, 'module' => $module, 'action' => PermissionAction::View]);
        }
        $menu = $this->actingAs($reader)->get(route('nr1.documentos'))->assertOk()->viewData('page')['props']['shell']['menu'];
        $this->assertNotContains('parecer', array_column($menu, 'key'));
        $this->actingAs($reader)->get(route('nr1.parecer'))->assertForbidden();
    }

    public function test_legacy_campaign_uses_the_same_layout_with_questionnaire_link_and_qr(): void
    {
        $c = $this->legacyCampaign();

        $this->actingAs($c->owner)->get(route('nr1.campanha', $this->query($c)))->assertInertia(fn (Assert $p) => $p
            ->where('section.legacy.campaign.questionnaire.questions', 1)
            ->where('section.legacy.campaign.questionnaire.dimensions', 1)
            ->where('section.legacy.campaign.participation.completed', 6)
            ->has('section.legacy.campaign.facts.steps', 4)
            ->where('section.legacy.campaign.facts.publication.published', false)
            ->where('section.legacy.campaign.registrationUrl', route('admin.companies.surveys.registration.edit', [$c->company->id, $c->survey->id])));
    }
}
