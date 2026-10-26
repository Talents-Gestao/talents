<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Nr1Opportunity;
use App\Models\Nr1OpportunityEvent;
use App\Models\Nr1ServiceMatch;
use App\Models\Survey;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Nr1\Nr1SolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Soluções Talents (Caderno §14): três cartões de serviço e recomendações por dimensão em atenção, só depois de
 * «Publicado»; «Solicitar conversa» gera lead; sugestão automática gera só «A qualificar», nunca interesse declarado.
 */
class Nr1V11SolutionsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    /**
     * @return object{company: mixed, survey: Survey, admin: User, talents: User}
     */
    private function campaign(bool $published = true): object
    {
        $campaign = $this->v11Campaign(true);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => $published ? now() : null])->save();

        return (object) [
            'company' => $campaign->company,
            'survey' => $campaign->survey->fresh(),
            'admin' => User::factory()->companyAdmin($campaign->company->id)->create(),
            'talents' => User::factory()->talentsOwner()->create(['name' => 'Talents Owner']),
        ];
    }

    private function approveAll(): void
    {
        Nr1ServiceMatch::query()->update(['reviewed_at' => now()]);
    }

    private function solutionsUrl(object $c): string
    {
        return route('client.surveys.solutions', $c->survey);
    }

    private function requestUrl(object $c, int $matchId): string
    {
        return route('client.surveys.solutions.request', [$c->survey->id, $matchId]);
    }

    private function match(string $factor): Nr1ServiceMatch
    {
        return Nr1ServiceMatch::query()->where('factor_label', $factor)->firstOrFail();
    }

    public function test_the_six_html_solutions_are_the_initial_default_and_start_unreviewed(): void
    {
        $this->assertSame(6, Nr1ServiceMatch::query()->count());
        $this->assertSame(0, Nr1ServiceMatch::query()->whereNotNull('reviewed_at')->count());
        $this->assertSame(
            ['Demandas e Sobrecarga', 'Segurança Psicológica', 'Esgotamento e Saúde Emocional', 'Liderança e Gestão', 'Relacionamentos e Clima', 'Percepção Organizacional'],
            Nr1ServiceMatch::query()->orderBy('sort_order')->pluck('factor_label')->all(),
        );
        $demanda = $this->match('Demandas e Sobrecarga');
        $this->assertSame('Diagnóstico Empresarial e Contratação de Talentos', $demanda->service_name);
        $this->assertSame('media', $demanda->confidence);
    }

    public function test_t02_nothing_before_published_and_the_three_service_cards_after(): void
    {
        $c = $this->campaign(published: false);
        $this->approveAll();

        $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertNotFound();
        $this->actingAs($c->admin)->post($this->requestUrl($c, $this->match('Demandas e Sobrecarga')->id))->assertNotFound();
        $this->assertSame(0, Nr1Opportunity::query()->count());

        $c->survey->forceFill(['published_at' => now()])->save();
        $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Client/Surveys/Solutions')
            ->has('cards', 3)
            ->where('cards.0.title', 'Pesquisa Psicossocial')
            ->where('cards.0.status', 'Serviço realizado')
            ->where('cards.1.title', 'Avaliação Complementar')
            ->where('cards.1.status', 'Sob proposta')
            ->where('cards.2.title', 'Intervenção Organizacional'));
    }

    public function test_company_only_sees_reviewed_active_rules_of_dimensions_in_attention(): void
    {
        $c = $this->campaign();

        // Nada revisado: nada aparece para a empresa.
        $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertInertia(fn (Assert $page) => $page->has('recommendations', 0));

        // Revisadas: uma por dimensão em atenção (o conjunto de teste tem 6 em atenção e Autonomia fora).
        $this->approveAll();
        $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertInertia(function (Assert $page) {
            $recommendations = collect($page->toArray()['props']['recommendations']);
            $this->assertCount(6, $recommendations);
            $first = $recommendations->firstWhere('factor', 'Demandas e Sobrecarga');
            $this->assertSame(['3,30', 'Média'], [$first['score_label'], $first['confidence_label']]);
            $this->assertSame('Diagnóstico Empresarial e Contratação de Talentos', $first['service_name']);
            $this->assertSame(['id', 'factor', 'score_label', 'risk_level', 'band_label', 'service_name', 'eligible_services', 'text', 'confidence', 'confidence_label', 'requested'], array_keys($first));
        });

        // Regra inativa some.
        $this->match('Segurança Psicológica')->update(['active' => false]);
        $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertInertia(fn (Assert $page) => $page->has('recommendations', 5));

        // Revisão desligada por configuração: a empresa vê as ativas mesmo sem revisão.
        Nr1ServiceMatch::query()->update(['reviewed_at' => null]);
        config(['nr1.solutions.require_review' => false]);
        $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertInertia(fn (Assert $page) => $page->has('recommendations', 5));
    }

    public function test_request_conversation_creates_the_lead_with_company_survey_service_need_date_and_requester(): void
    {
        $c = $this->campaign();
        $this->approveAll();
        $match = $this->match('Liderança e Gestão');

        // Ver a tela não cria nada.
        $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertOk();
        $this->assertSame(0, Nr1Opportunity::query()->count());

        $this->actingAs($c->admin)->post($this->requestUrl($c, $match->id), ['note' => 'Queremos começar em janeiro.'])->assertSessionHas('success');

        $lead = Nr1Opportunity::query()->sole();
        $this->assertSame(
            [$c->company->id, $c->survey->id, 'solution_request', 'Desenvolvimento de Lideranças', $c->admin->id, 'new', null],
            [$lead->company_id, $lead->survey_id, $lead->origin, $lead->service_key, $lead->requested_by, $lead->status, $lead->assigned_to],
        );
        $this->assertStringContainsString('Liderança e Gestão em atenção', $lead->need);
        $this->assertStringContainsString('Queremos começar em janeiro.', $lead->need);
        $this->assertNotNull($lead->created_at);

        // Duplicado aberto: recusado; a tela mostra «Conversa solicitada».
        $this->actingAs($c->admin)->post($this->requestUrl($c, $match->id))->assertSessionHas('error');
        $this->assertSame(1, Nr1Opportunity::query()->count());
        $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertInertia(function (Assert $page) {
            $row = collect($page->toArray()['props']['recommendations'])->firstWhere('factor', 'Liderança e Gestão');
            $this->assertTrue($row['requested']);
        });

        // Outro serviço segue livre.
        $this->actingAs($c->admin)->post($this->requestUrl($c, $this->match('Segurança Psicológica')->id))->assertSessionHas('success');
        $this->assertSame(2, Nr1Opportunity::query()->count());
    }

    public function test_default_commercial_owner_of_the_rule_becomes_the_lead_owner(): void
    {
        $c = $this->campaign();
        $this->approveAll();
        $owner = User::factory()->superAdmin()->create(['is_owner' => false, 'name' => 'Responsável Padrão']);
        $match = $this->match('Relacionamentos e Clima');
        $match->update(['commercial_owner_id' => $owner->id]);

        $this->actingAs($c->admin)->post($this->requestUrl($c, $match->id))->assertSessionHas('success');

        $lead = Nr1Opportunity::query()->sole();
        $this->assertSame($owner->id, $lead->assigned_to);
        $this->assertContains('assigned_default', Nr1OpportunityEvent::query()->pluck('action')->all());
    }

    public function test_request_conversation_authorization_and_availability(): void
    {
        $c = $this->campaign();
        $this->approveAll();
        $match = $this->match('Demandas e Sobrecarga');

        // Usuário da empresa só com ver em Pesquisas: vê, não pede.
        $user = User::factory()->companyUser($c->company->id)->create();
        UserPermission::query()->create(['user_workspace_id' => $user->workspaces()->firstOrFail()->id, 'module' => PermissionModule::Pesquisas->value, 'action' => PermissionAction::View->value]);
        $this->actingAs($user)->get($this->solutionsUrl($c))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canRequest', false));
        $this->actingAs($user)->post($this->requestUrl($c, $match->id))->assertForbidden();

        // Outra empresa: 404. Regra de dimensão que não está em atenção: recusada. Regra inexistente: recusada.
        $other = $this->v11Campaign(false);
        $otherAdmin = User::factory()->companyAdmin($other->company->id)->create();
        $this->actingAs($otherAdmin)->get($this->solutionsUrl($c))->assertNotFound();
        $this->actingAs($otherAdmin)->post($this->requestUrl($c, $match->id))->assertNotFound();

        $match->update(['active' => false]);
        $this->actingAs($c->admin)->post($this->requestUrl($c, $match->id))->assertSessionHas('error');
        $this->actingAs($c->admin)->post($this->requestUrl($c, 9999))->assertSessionHas('error');

        // Campanha antiga (não V11): 404.
        $old = $this->createSurveyFixture(['requires_publication' => true, 'published_at' => now()]);
        $oldAdmin = User::factory()->companyAdmin($old->company->id)->create();
        $this->actingAs($oldAdmin)->get(route('client.surveys.solutions', $old->survey))->assertNotFound();

        $this->assertSame(0, Nr1Opportunity::query()->count());
    }

    public function test_automatic_suggestion_creates_only_to_qualify_never_declared_interest_and_does_not_duplicate(): void
    {
        $c = $this->campaign();

        $created = app(Nr1SolutionService::class)->suggest($c->survey);
        $this->assertSame(6, $created);

        $rows = Nr1Opportunity::query()->get();
        $this->assertCount(6, $rows);
        foreach ($rows as $row) {
            $this->assertSame(Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION, $row->origin);
            $this->assertSame('to_qualify', $row->status);
            $this->assertNull($row->requested_by, 'Sugestão automática não tem solicitante.');
            $this->assertNotNull($row->open_key);
        }
        $this->assertSame(0, Nr1Opportunity::query()->where('origin', Nr1Opportunity::ORIGIN_SOLUTION_REQUEST)->count());
        $this->assertStringContainsString('não é interesse declarado', Nr1OpportunityEvent::query()->firstOrFail()->body);

        // Rodar de novo não duplica.
        $this->assertSame(0, app(Nr1SolutionService::class)->suggest($c->survey));
        $this->assertSame(6, Nr1Opportunity::query()->count());
    }

    public function test_suggestion_does_not_run_before_published_or_for_inactive_rules(): void
    {
        $draft = $this->campaign(published: false);
        $this->assertSame(0, app(Nr1SolutionService::class)->suggest($draft->survey));

        $c = $this->campaign();
        Nr1ServiceMatch::query()->where('factor_label', '!=', 'Demandas e Sobrecarga')->update(['active' => false]);
        $this->assertSame(1, app(Nr1SolutionService::class)->suggest($c->survey));
    }

    public function test_company_request_converts_the_open_suggestion_into_the_companys_request(): void
    {
        $c = $this->campaign();
        $this->approveAll();
        app(Nr1SolutionService::class)->suggest($c->survey);
        $match = $this->match('Segurança Psicológica');

        $this->actingAs($c->admin)->post($this->requestUrl($c, $match->id))->assertSessionHas('success');

        $this->assertSame(6, Nr1Opportunity::query()->count(), 'A sugestão virou pedido: não cria uma segunda.');
        $lead = Nr1Opportunity::query()->where('service_key', 'Voz do Time e comunicação segura')->sole();
        $this->assertSame(['solution_request', 'new', $c->admin->id], [$lead->origin, $lead->status, $lead->requested_by]);
        $this->assertContains('requested', Nr1OpportunityEvent::query()->where('opportunity_id', $lead->id)->pluck('action')->all());
        $this->assertSame(5, Nr1Opportunity::query()->where('origin', Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION)->count());

        // E um segundo clique agora é pedido duplicado.
        $this->actingAs($c->admin)->post($this->requestUrl($c, $match->id))->assertSessionHas('error');
    }

    public function test_rules_are_edited_and_reviewed_only_by_talents_and_editing_the_text_requires_a_new_review(): void
    {
        $c = $this->campaign();
        $match = $this->match('Demandas e Sobrecarga');
        $patch = fn (array $extra = []) => array_merge([
            'service_name' => $match->service_name,
            'eligible_services' => $match->eligible_services,
            'consultative_text' => $match->consultative_text,
            'confidence' => 'media',
            'active' => true,
        ], $extra);

        $this->actingAs($c->talents)->get(route('admin.service-matches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ServiceMatches/Index')
            ->has('matches', 6)
            ->where('matches.0.reviewed', false)
            ->where('requireReview', true));

        $this->actingAs($c->talents)->post(route('admin.service-matches.approve', $match->id))->assertSessionHas('success');
        $fresh = $match->fresh();
        $this->assertTrue($fresh->isReviewed());
        $this->assertSame($c->talents->id, $fresh->reviewed_by);

        // Mudar só «ativa» mantém a revisão; mudar o texto ou a confiança a derruba.
        $this->actingAs($c->talents)->patch(route('admin.service-matches.update', $match->id), $patch(['active' => false]))->assertSessionHas('success');
        $this->assertTrue($match->fresh()->isReviewed());
        $this->actingAs($c->talents)->patch(route('admin.service-matches.update', $match->id), $patch(['consultative_text' => 'Texto novo da consultoria.']))->assertSessionHas('success');
        $this->assertFalse($match->fresh()->isReviewed());
        $this->assertSame('Texto novo da consultoria.', $match->fresh()->consultative_text);

        $this->actingAs($c->talents)->post(route('admin.service-matches.approve', $match->id));
        $this->actingAs($c->talents)->patch(route('admin.service-matches.update', $match->id), $patch(['confidence' => 'alta']));
        $this->assertFalse($match->fresh()->isReviewed());

        // Validação.
        $this->actingAs($c->talents)->patch(route('admin.service-matches.update', $match->id), $patch(['confidence' => 'enorme']))->assertSessionHasErrors('confidence');
        $this->actingAs($c->talents)->patch(route('admin.service-matches.update', $match->id), $patch(['service_name' => '']))->assertSessionHasErrors('service_name');
    }

    public function test_company_and_plain_admin_cannot_edit_or_review_rules(): void
    {
        $c = $this->campaign();
        $match = $this->match('Demandas e Sobrecarga');
        $plain = User::factory()->superAdmin()->create(['is_owner' => false]);

        foreach ([$c->admin, $plain] as $user) {
            $this->actingAs($user)->get(route('admin.service-matches.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.service-matches.approve', $match->id))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.service-matches.update', $match->id), ['service_name' => 'x', 'eligible_services' => 'x', 'consultative_text' => 'x', 'confidence' => 'alta', 'active' => true])->assertForbidden();
        }

        $this->assertFalse($match->fresh()->isReviewed());
        $this->assertSame('Diagnóstico Empresarial e Contratação de Talentos', $match->fresh()->service_name);
    }

    public function test_solutions_page_exposes_no_raw_answers_or_other_company_data(): void
    {
        $c = $this->campaign();
        $this->approveAll();

        $response = $this->actingAs($c->admin)->get($this->solutionsUrl($c))->assertOk();
        $json = json_encode($response->viewData('page')['props'], JSON_UNESCAPED_UNICODE);

        foreach (['session_token', 'survey_answers', '"answers"', 'age_range', 'tenure', 'respondent_id', 'lost_reason', 'internalReview'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }
}
