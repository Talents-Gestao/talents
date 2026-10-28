<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\Company;
use App\Models\Nr1Opportunity;
use App\Models\Nr1ServiceMatch;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialReviewRound;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Models\User;
use App\Services\Nr1\Nr1OpportunityService;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\Psychosocial\ReviewRoundService;
use App\Services\PsychosocialMeasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Shell NR-1, Etapa D (V11): Documentos, Soluções Talents e Oportunidades. Só apresentação e navegação: as props vêm dos
 * controllers atuais e as ações usam as rotas atuais (com o step-up de MFA de Publicar).
 */
class Nr1ShellStageDTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake(PsychosocialDocumentService::DISK);
        config(['nr1.shell_enabled' => true]);
    }

    /** @return object{company: Company, survey: Survey, owner: User, admin: User} */
    private function campaign(bool $published = true): object
    {
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => $published ? now() : null, 'requires_passeg_review' => false])->save();
        SurveyCampaignRegistration::query()->updateOrCreate(['survey_id' => $c->survey->id], [
            'company_id' => $c->company->id, 'legal_name_snapshot' => 'Empresa V11 Ltda', 'cnpj_snapshot' => '11.222.333/0001-81', 'unit_name' => 'Unidade Centro',
            'eligible_count' => 48, 'eligible_source' => 'Cadastro de RH', 'technical_responsible_name' => 'Carla Souza',
            'technical_responsible_role' => 'Psicóloga', 'technical_responsible_registration' => 'CRP 06/123456', 'approving_organization_name' => 'Org',
        ]);

        $owner = User::factory()->superAdmin()->mfaVerified()->create(['is_owner' => true]);
        app(PsychosocialMeasureService::class)->createManual($c->survey->fresh(), ['factor_label' => 'Demandas e Sobrecarga', 'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION, 'measure_description' => 'Revisar tarefas.', 'responsible_text' => 'Operações', 'suggested_due_date' => now()->addDays(30)->toDateString()], $owner);

        return (object) [
            'company' => $c->company,
            'survey' => $c->survey->fresh(),
            'owner' => $owner,
            'admin' => User::factory()->companyAdmin($c->company->id)->create(),
        ];
    }

    private function publishedRound(object $c): PsychosocialReviewRound
    {
        $c->survey->forceFill(['published_at' => null])->save();
        $rounds = app(ReviewRoundService::class);
        $round = $rounds->regenerate($rounds->start($c->survey->fresh(), $c->owner), $c->owner);

        return $rounds->publish($round, $c->owner);
    }

    public function test_documents_for_talents_carry_the_round_actions_urls_and_history_and_the_company_sees_only_the_published_round(): void
    {
        $c = $this->campaign(false);
        $rounds = app(ReviewRoundService::class);
        $round = $rounds->regenerate($rounds->start($c->survey, $c->owner), $c->owner);
        $q = ['empresa' => $c->company->id, 'campanha' => $c->survey->id];

        $this->actingAs($c->owner)->get(route('nr1.documentos', $q))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.documents.mode', 'talents')
            ->where('section.documents.round.status', 'draft')->where('section.documents.round.status_label', 'Em edição')
            ->where('section.documents.round.actions.publish', true)->where('section.documents.round.actions.regenerate', true)
            ->has('section.documents.round.versions', 2)->has('section.documents.round.history')
            ->where('section.documents.requiresPassegReview', false)
            ->where('section.documents.urls.publish', route('admin.companies.surveys.documents.publish', [$c->company->id, $c->survey->id, $round->id]))
            ->where('section.documents.urls.regenerate', route('admin.companies.surveys.documents.regenerate', [$c->company->id, $c->survey->id, $round->id]))
            ->where('section.documents.urls.start', route('admin.companies.surveys.documents.start', [$c->company->id, $c->survey->id])));

        // Empresa antes de publicar: campanha não publicada = nada (contexto vazio); com a campanha liberada mas SEM rodada publicada: round nulo.
        $this->actingAs($c->admin)->get(route('nr1.documentos'))->assertInertia(fn (Assert $p) => $p->where('context.survey', null)->missing('section.documents'));
        $c->survey->forceFill(['published_at' => now()])->save();
        $page = $this->actingAs($c->admin)->get(route('nr1.documentos'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.documents.mode', 'company')->where('section.documents.round', null));
        $json = (string) json_encode($page->viewData('page')['props']['section']['documents']);
        foreach (['reviewers', 'pending', 'regenerate', 'publish', 'status_label'] as $leak) {
            $this->assertStringNotContainsString($leak, $json, "a empresa não recebe {$leak}");
        }

        // Publicada: a empresa vê só a rodada publicada e só os eventos permitidos (publicação, ciência, PGR).
        $c->survey->forceFill(['published_at' => null])->save();
        $published = $rounds->publish($round->fresh(), $c->owner);
        $this->actingAs($c->admin)->get(route('nr1.documentos'))->assertInertia(fn (Assert $p) => $p
            ->where('section.documents.round.id', $published->id)->where('section.documents.round.status', 'published')->has('section.documents.round.versions', 2)
            ->where('section.documents.urls.acknowledge', route('client.surveys.documents.acknowledge', $c->survey->id)));
    }

    public function test_publish_from_the_shell_still_requires_the_recent_mfa_and_actions_return_to_the_shell(): void
    {
        $c = $this->campaign(false);
        $rounds = app(ReviewRoundService::class);
        $round = $rounds->regenerate($rounds->start($c->survey, $c->owner), $c->owner);
        $back = '/nr1/documentos?empresa='.$c->company->id.'&campanha='.$c->survey->id;
        $publish = route('admin.companies.surveys.documents.publish', [$c->company->id, $c->survey->id, $round->id]);

        // Sem verificação recente: o step-up manda para o desafio e NADA é publicado.
        $this->actingAs($c->owner)->post($publish, ['_nr1_return' => $back])->assertRedirect(route('mfa.challenge'));
        $this->assertNull($c->survey->fresh()->published_at);

        // Verificado: publica e volta para o shell (e só para caminhos /nr1; qualquer outro destino é ignorado).
        $this->actingAsMfa($c->owner)->post($publish, ['_nr1_return' => $back])->assertRedirect($back);
        $this->assertNotNull($c->survey->fresh()->published_at);

        $regenerate = route('admin.companies.surveys.documents.start', [$c->company->id, $c->survey->id]);
        foreach (['https://evil.example/x', '//evil.example', '/admin/qualquer'] as $bad) {
            $this->actingAsMfa($c->owner)->post($regenerate, ['_nr1_return' => $bad])->assertRedirect(route('admin.companies.surveys.documents.index', [$c->company->id, $c->survey->id]));
            DB::table('psychosocial_review_rounds')->where('survey_id', $c->survey->id)->where('status', 'draft')->delete();
        }
    }

    public function test_solutions_show_the_cards_the_locked_message_before_publication_and_only_reviewed_rules_to_the_company(): void
    {
        $c = $this->campaign(false);
        $q = ['empresa' => $c->company->id, 'campanha' => $c->survey->id];

        $this->actingAs($c->owner)->get(route('nr1.solucoes', $q))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.solutions.mode', 'staff')->where('section.solutions.locked', true)->where('section.solutions.recommendations', [])
            ->where('section.solutions.cards.0.number', '01')->where('section.solutions.cards.0.status', 'Serviço realizado')
            ->where('section.solutions.cards.1.status', 'Sob proposta')->where('section.solutions.cards.2.title', 'Intervenção Organizacional'));

        $c->survey->forceFill(['published_at' => now()])->save();
        $this->actingAs($c->owner)->get(route('nr1.solucoes', $q))->assertInertia(fn (Assert $p) => $p
            ->where('section.solutions.locked', false)->where('section.solutions.mode', 'staff')->has('section.solutions.recommendations'));

        // Empresa: regras ainda não revisadas pela Talents não chegam; depois de revisadas, chegam (texto provisório).
        $before = $this->actingAs($c->admin)->get(route('nr1.solucoes'))->assertOk()->viewData('page')['props']['section']['solutions'];
        $this->assertSame([], $before['recommendations']);
        Nr1ServiceMatch::query()->update(['reviewed_at' => now()]);
        $after = $this->actingAs($c->admin)->get(route('nr1.solucoes'))->viewData('page')['props']['section']['solutions'];
        $this->assertSame('company', $after['mode']);
        $this->assertSame(route('client.surveys.solutions.request', [$c->survey->id, '__MATCH__']), $after['requestUrlBase']);
        $this->assertNotEmpty($after['recommendations']);
    }

    public function test_commercial_sees_solutions_only_if_the_matrix_allows_and_without_a_campaign(): void
    {
        $this->campaign();
        $commercial = User::factory()->superAdmin()->create(['is_owner' => false]);
        AdminUserPermission::query()->create(['user_workspace_id' => $commercial->workspaces()->firstOrFail()->id, 'module' => AdminPermissionModule::Nr1Oportunidades, 'action' => PermissionAction::View]);

        // Matriz sem methodology: a seção não existe para o Comercial.
        $this->actingAs($commercial)->get(route('nr1.solucoes'))->assertForbidden();

        AdminUserPermission::query()->create(['user_workspace_id' => $commercial->workspaces()->firstOrFail()->id, 'module' => AdminPermissionModule::Methodology, 'action' => PermissionAction::View]);
        $this->actingAs($commercial)->get(route('nr1.solucoes'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.solutions.noSurvey', true)->has('section.solutions.cards', 3)->where('section.solutions.recommendations', []));
    }

    public function test_the_commercial_queue_has_counters_filters_transitions_and_urls_and_the_company_never_reaches_it(): void
    {
        $c = $this->campaign();
        $opportunities = app(Nr1OpportunityService::class);
        $first = $opportunities->requestTraining($c->survey, $c->admin, 'presencial', 'time');
        $second = $opportunities->requestTraining($c->survey, $c->admin, 'presencial', 'lideres');
        $opportunities->assume($first, $c->owner);
        $opportunities->changeStatus($first->fresh(), $c->owner, Nr1Opportunity::STATUS_IN_CONTACT, 'Contato feito.');

        $this->actingAs($c->owner)->get(route('nr1.oportunidades'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.opportunities.counters.identified', 2)->where('section.opportunities.counters.in_contact', 1)->where('section.opportunities.counters.to_qualify', 1)
            ->has('section.opportunities.queue', 2)->where('section.opportunities.canWork', true)
            ->has('section.opportunities.stages', 6)
            ->where('section.opportunities.queue.0.urls.assume', fn ($url) => str_contains($url, '/oportunidades-nr1/fila/')));

        // Filtro por estágio usa o filtro do controller atual.
        $this->actingAs($c->owner)->get(route('nr1.oportunidades', ['status' => 'in_contact']))->assertInertia(fn (Assert $p) => $p
            ->has('section.opportunities.queue', 1)->where('section.opportunities.queue.0.id', $first->id)->where('section.opportunities.filters.status', 'in_contact'));

        // Transições permitidas por estágio (as do serviço atual).
        $row = collect($this->actingAs($c->owner)->get(route('nr1.oportunidades'))->viewData('page')['props']['section']['opportunities']['queue'])->firstWhere('id', $second->id);
        $this->assertSame(['to_qualify', 'in_contact', 'lost'], array_column($row['transitions'], 'value'));

        // Empresa nunca vê esta área; Comercial só com a permissão (e sem edição não trabalha a fila).
        $this->actingAs($c->admin)->get(route('nr1.oportunidades'))->assertForbidden();
        $viewer = User::factory()->superAdmin()->create(['is_owner' => false]);
        AdminUserPermission::query()->create(['user_workspace_id' => $viewer->workspaces()->firstOrFail()->id, 'module' => AdminPermissionModule::Nr1Oportunidades, 'action' => PermissionAction::View]);
        $this->actingAs($viewer)->get(route('nr1.oportunidades'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('section.opportunities.canWork', false));
        $json = (string) json_encode($this->actingAs($viewer)->get(route('nr1.oportunidades'))->viewData('page')['props']['section']['opportunities']);
        $this->assertStringNotContainsString('session_token', $json);

        // Mudar o estágio pelo shell usa a rota atual e volta para o shell.
        $this->actingAs($c->owner)->post(route('admin.nr1-opportunities.status', $second->id), ['status' => 'in_contact', 'note' => 'Primeiro contato', '_nr1_return' => '/nr1/oportunidades'])->assertRedirect();
        $this->assertSame('in_contact', $second->fresh()->status);
    }

    public function test_the_default_campaign_for_the_company_is_the_most_recent_published_one_and_never_leaks_an_unpublished_one(): void
    {
        $c = $this->campaign();
        $c->survey->forceFill(['starts_at' => now()->subMonths(2)])->save();
        $newer = Survey::query()->create([
            'company_id' => $c->company->id, 'survey_template_id' => $c->survey->survey_template_id, 'title' => 'Campanha mais nova SIGILOSA', 'public_token' => (string) Str::uuid(),
            'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'status' => 'active', 'method_version_id' => $c->survey->method_version_id,
            'requires_publication' => true, 'published_at' => null,
        ]);

        // Empresa: a mais nova NÃO publicada nunca é o padrão, nem aparece no seletor, nem por ?campanha=.
        foreach ([[], ['campanha' => $newer->id]] as $query) {
            $page = $this->actingAs($c->admin)->get(route('nr1.visao-geral', $query))->assertOk();
            $props = $page->viewData('page')['props'];
            $this->assertSame($c->survey->id, $props['context']['survey']['id']);
            $this->assertCount(1, $props['context']['surveys']);
            $this->assertStringNotContainsString('SIGILOSA', (string) json_encode($props));
        }

        // Talents: a mais recente por início de coleta.
        $this->actingAs($c->owner)->get(route('nr1.visao-geral', ['empresa' => $c->company->id]))->assertInertia(fn (Assert $p) => $p->where('context.survey.id', $newer->id));
    }

    public function test_the_old_screens_and_routes_keep_working_for_the_v11_campaign(): void
    {
        $c = $this->campaign();
        $this->publishedRound($c);
        $c->survey->forceFill(['published_at' => now()])->save();

        foreach ([false, true] as $on) {
            config(['nr1.shell_enabled' => $on]);
            $this->actingAs($c->owner)->get(route('admin.companies.surveys.documents.index', [$c->company->id, $c->survey->id]))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Documents/Central'));
            $this->actingAs($c->admin)->get(route('client.surveys.documents.index', $c->survey->id))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Client/Surveys/Documents'));
            $this->actingAs($c->admin)->get(route('client.surveys.solutions', $c->survey->id))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Client/Surveys/Solutions'));
            $this->actingAs($c->owner)->get(route('admin.nr1-opportunities.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Nr1Opportunities/Index'));
        }
    }
}
