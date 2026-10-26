<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\AdminUserPermission;
use App\Models\ApproverOrganization;
use App\Models\Nr1Opportunity;
use App\Models\Nr1OpportunityEvent;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Nr1\Nr1OpportunityService;
use App\Services\Nr1CommercialSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Oportunidades (Caderno §14): fila com origem treinamento e solução, estados Novo / A qualificar / Em contato /
 * Proposta enviada / Ganho / Perdido, «Assumir oportunidade», próxima ação, histórico, recusa registrada (T22) e
 * visão do Comercial só com metadados e agregados de campanha publicada (T21).
 */
class Nr1V11OpportunitiesTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private const RAW_MARKER = 'sessao-bruta-nao-pode-sair';

    /**
     * @return object{company: mixed, survey: Survey, admin: User, talents: User}
     */
    private function campaign(bool $published = true): object
    {
        $campaign = $this->v11Campaign(true);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => $published ? now() : null])->save();
        SurveyResponse::query()->create(['survey_id' => $campaign->survey->id, 'session_token' => self::RAW_MARKER, 'completed_at' => now()]);

        return (object) [
            'company' => $campaign->company,
            'survey' => $campaign->survey->fresh(),
            'admin' => User::factory()->companyAdmin($campaign->company->id)->create(['name' => 'Ana Admin']),
            'talents' => User::factory()->talentsOwner()->create(['name' => 'Talents Owner']),
        ];
    }

    /** @param  list<PermissionAction>  $actions */
    private function commercial(array $actions = [PermissionAction::View, PermissionAction::Edit], string $name = 'Comercial'): User
    {
        $user = User::factory()->superAdmin()->create(['is_owner' => false, 'name' => $name]);
        foreach ($actions as $action) {
            AdminUserPermission::query()->create([
                'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
                'module' => AdminPermissionModule::Nr1Oportunidades,
                'action' => $action,
            ]);
        }

        return $user;
    }

    private function request(object $c, string $modality = 'online', string $audience = 'time'): Nr1Opportunity
    {
        return app(Nr1OpportunityService::class)->requestTraining($c->survey, $c->admin, $modality, $audience);
    }

    private function solution(object $c, string $origin = Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION, string $service = 'Clima e escuta'): ?Nr1Opportunity
    {
        return app(Nr1OpportunityService::class)->openSolution($c->survey, $origin, $service, 'Dimensão em atenção', $origin === Nr1Opportunity::ORIGIN_SOLUTION_REQUEST ? $c->admin : null);
    }

    public function test_queue_lists_training_and_solution_origins_with_counters_and_filters(): void
    {
        $c = $this->campaign();
        $user = $this->commercial([PermissionAction::View]);
        $this->request($c);
        $this->request($c, 'presencial', 'lideres')->forceFill(['status' => Nr1Opportunity::STATUS_IN_CONTACT])->save();
        $this->solution($c);

        $this->actingAs($user)->get(route('admin.nr1-opportunities.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('queue', 3)
            ->where('counters.identified', 3)
            ->where('counters.in_contact', 1)
            ->where('counters.to_qualify', 2)
            ->where('canWork', false)
            ->has('surveys', 1));

        $this->actingAs($user)->get(route('admin.nr1-opportunities.index', ['origin' => 'training_request']))
            ->assertInertia(fn (Assert $page) => $page->has('queue', 2)->where('filters.origin', 'training_request'));
        $this->actingAs($user)->get(route('admin.nr1-opportunities.index', ['status' => 'in_contact']))
            ->assertInertia(fn (Assert $page) => $page->has('queue', 1)->where('queue.0.service', 'Treinamento Presencial · Líderes'));
        $this->actingAs($user)->get(route('admin.nr1-opportunities.index', ['origin' => 'invalida']))->assertSessionHasErrors('origin');
    }

    public function test_queue_row_exposes_only_the_whitelisted_metadata(): void
    {
        $c = $this->campaign();
        $opportunity = $this->request($c);
        $row = app(Nr1CommercialSerializer::class)->queueRow($opportunity);

        $this->assertSame(
            ['id', 'origin', 'origin_label', 'company_name', 'survey_title', 'service', 'status', 'status_label', 'open', 'requester', 'assignee', 'assigned_to', 'created_at', 'last_activity_at', 'next_action', 'next_action_at'],
            array_keys($row),
        );
        $this->assertSame(['Treinamento', 'Novo', 'Ana Admin', 'Treinamento Online · Time'], [$row['origin_label'], $row['status_label'], $row['requester'], $row['service']]);
    }

    public function test_t21_commercial_sees_aggregates_only_of_published_campaigns_and_never_raw_answers(): void
    {
        $published = $this->campaign();
        $opportunity = $this->request($published);
        $user = $this->commercial([PermissionAction::View]);

        $response = $this->actingAs($user)->get(route('admin.nr1-opportunities.opportunity', $opportunity->id))->assertOk();
        $props = $response->viewData('page')['props']['opportunity'];

        $this->assertNotNull($props['aggregate']);
        $this->assertSame(40, $props['aggregate']['respondents']);
        $json = json_encode($props, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(self::RAW_MARKER, $json);
        foreach (['session_token', 'answers', 'survey_answers', 'age_range', 'tenure', 'respondent_id', 'items'] as $forbidden) {
            $this->assertStringNotContainsString('"'.$forbidden.'"', $json, "Chave proibida: {$forbidden}");
        }

        // Campanha ainda não publicada: a oportunidade aparece (metadados), o agregado não.
        $published->survey->forceFill(['published_at' => null])->save();
        $this->actingAs($user)->get(route('admin.nr1-opportunities.opportunity', $opportunity->id))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('opportunity.aggregate', null));
        $this->actingAs($user)->get(route('admin.nr1-opportunities.show', $published->survey->id))->assertNotFound();
    }

    public function test_assume_sets_the_owner_moves_new_to_qualify_logs_it_and_does_not_steal(): void
    {
        $c = $this->campaign();
        $opportunity = $this->request($c);
        $ana = $this->commercial(name: 'Ana Comercial');
        $beto = $this->commercial(name: 'Beto Comercial');

        $this->actingAs($ana)->post(route('admin.nr1-opportunities.assume', $opportunity->id))->assertSessionHas('success');

        $row = $opportunity->fresh();
        $this->assertSame([$ana->id, 'to_qualify'], [$row->assigned_to, $row->status]);
        $this->assertSame(['created', 'assumed'], Nr1OpportunityEvent::query()->pluck('action')->all());

        $this->actingAs($beto)->post(route('admin.nr1-opportunities.assume', $opportunity->id))->assertSessionHas('error');
        $this->assertSame($ana->id, $opportunity->fresh()->assigned_to);

        // O mesmo responsável pode assumir de novo sem efeito colateral.
        $this->actingAs($ana)->post(route('admin.nr1-opportunities.assume', $opportunity->id))->assertSessionHas('success');
    }

    public function test_status_flow_closes_with_won_only_after_proposal_and_frees_a_new_request(): void
    {
        $c = $this->campaign();
        $opportunity = $this->request($c);
        $user = $this->commercial();
        $post = fn (string $status, ?string $note = null) => $this->actingAs($user)
            ->post(route('admin.nr1-opportunities.status', $opportunity->id), ['status' => $status, 'note' => $note]);

        $post('won')->assertSessionHas('error');
        $post('proposal_sent')->assertSessionHas('error');
        $post('in_contact', 'Primeiro contato por telefone')->assertSessionHas('success');
        $post('proposal_sent')->assertSessionHas('success');
        $post('won', 'Contrato assinado')->assertSessionHas('success');

        $row = $opportunity->fresh();
        $this->assertSame('won', $row->status);
        $this->assertNull($row->open_key);
        $post('in_contact')->assertSessionHas('error');
        $this->assertSame(['created', 'status_changed', 'status_changed', 'status_changed'], Nr1OpportunityEvent::query()->pluck('action')->all());

        // Encerrada: um novo pedido aberto do mesmo treinamento é permitido.
        $this->assertSame('new', $this->request($c)->status);
    }

    public function test_t22_refusal_requires_a_reason_and_is_recorded_in_the_history(): void
    {
        $c = $this->campaign();
        $opportunity = $this->request($c);
        $user = $this->commercial(name: 'Carla Comercial');
        $url = route('admin.nr1-opportunities.status', $opportunity->id);

        $this->actingAs($user)->post($url, ['status' => 'lost'])->assertSessionHas('error');
        $this->actingAs($user)->post($url, ['status' => 'lost', 'note' => 'ok'])->assertSessionHas('error');
        $this->assertSame('new', $opportunity->fresh()->status);

        $this->actingAs($user)->post($url, ['status' => 'lost', 'note' => 'Cliente optou por fornecedor interno'])->assertSessionHas('success');

        $row = $opportunity->fresh();
        $this->assertSame(['lost', 'Cliente optou por fornecedor interno', null], [$row->status, $row->lost_reason, $row->open_key]);
        $event = Nr1OpportunityEvent::query()->where('action', 'lost')->sole();
        $this->assertSame([$user->id, 'new', 'lost', 'Cliente optou por fornecedor interno'], [$event->user_id, $event->from_status, $event->to_status, $event->body]);

        $this->actingAs($user)->get(route('admin.nr1-opportunities.opportunity', $opportunity->id))->assertInertia(fn (Assert $page) => $page
            ->where('opportunity.lost_reason', 'Cliente optou por fornecedor interno')
            ->where('opportunity.history.1.author', 'Carla Comercial')
            ->where('opportunity.history.1.to', 'Perdido'));
    }

    public function test_next_action_is_saved_and_refused_when_empty_or_closed(): void
    {
        $c = $this->campaign();
        $opportunity = $this->request($c);
        $user = $this->commercial();
        $url = route('admin.nr1-opportunities.next-action', $opportunity->id);

        $this->actingAs($user)->post($url, ['next_action' => ''])->assertSessionHasErrors('next_action');
        $this->actingAs($user)->post($url, ['next_action' => 'Ligar para o RH', 'next_action_at' => '2026-11-10'])->assertSessionHas('success');

        $row = $opportunity->fresh();
        $this->assertSame('Ligar para o RH', $row->next_action);
        $this->assertSame('2026-11-10', $row->next_action_at->format('Y-m-d'));
        $this->assertSame('next_action', Nr1OpportunityEvent::query()->latest('id')->first()->action);

        $row->forceFill(['status' => 'lost', 'open_key' => null])->save();
        $this->actingAs($user)->post($url, ['next_action' => 'Tarde demais'])->assertSessionHas('error');
    }

    public function test_view_only_commercial_can_read_but_not_work_the_queue(): void
    {
        $c = $this->campaign();
        $opportunity = $this->request($c);
        $viewer = $this->commercial([PermissionAction::View]);

        $this->actingAs($viewer)->get(route('admin.nr1-opportunities.opportunity', $opportunity->id))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canWork', false));

        $this->actingAs($viewer)->post(route('admin.nr1-opportunities.assume', $opportunity->id))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.nr1-opportunities.status', $opportunity->id), ['status' => 'in_contact'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.nr1-opportunities.next-action', $opportunity->id), ['next_action' => 'x'])->assertForbidden();

        $this->assertSame(['status' => 'new', 'assigned_to' => null], ['status' => $opportunity->fresh()->status, 'assigned_to' => $opportunity->fresh()->assigned_to]);
    }

    public function test_company_reviewer_and_plain_admin_never_reach_the_queue(): void
    {
        $c = $this->campaign();
        $opportunity = $this->request($c);
        $reviewer = User::factory()->externalReviewer(ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true])->id)->create();
        $plain = User::factory()->superAdmin()->create(['is_owner' => false]);

        foreach ([$c->admin, $reviewer, $plain] as $user) {
            $this->actingAs($user)->get(route('admin.nr1-opportunities.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.nr1-opportunities.opportunity', $opportunity->id))->assertForbidden();
            $this->actingAs($user)->post(route('admin.nr1-opportunities.assume', $opportunity->id))->assertForbidden();
        }

        $this->assertSame('new', $opportunity->fresh()->status);
    }

    public function test_solution_opportunities_do_not_duplicate_while_open_and_suggestion_is_never_declared_interest(): void
    {
        $c = $this->campaign();

        $suggested = $this->solution($c);
        $this->assertNotNull($suggested);
        $this->assertSame('to_qualify', $suggested->status);
        $this->assertNull($suggested->requested_by);
        $this->assertStringContainsString('não é interesse declarado', Nr1OpportunityEvent::query()->firstOrFail()->body);
        $this->assertNull($this->solution($c), 'Segunda sugestão aberta do mesmo serviço não duplica.');

        $requested = $this->solution($c, Nr1Opportunity::ORIGIN_SOLUTION_REQUEST);
        $this->assertNotNull($requested);
        $this->assertSame('new', $requested->status);
        $this->assertSame($c->admin->id, $requested->requested_by);
        $this->assertSame(2, Nr1Opportunity::query()->count());
    }

    public function test_opportunity_is_not_created_by_viewing_the_queue_or_the_campaign(): void
    {
        $c = $this->campaign();
        $user = $this->commercial([PermissionAction::View]);

        $this->actingAs($user)->get(route('admin.nr1-opportunities.index'))->assertOk();
        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertOk();

        $this->assertSame(0, Nr1Opportunity::query()->count());
    }
}
