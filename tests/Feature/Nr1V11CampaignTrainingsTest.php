<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Company;
use App\Models\Nr1Opportunity;
use App\Models\Nr1OpportunityEvent;
use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyCampaignRegistration;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Campanha NR-1 (Caderno §6): orientação sempre incluída, treinamentos Online e Presencial por público, «Marcar
 * contratado» (Talents), «Solicitar proposta» (empresa, T07), link e QR só com a campanha aberta, elegíveis só da Talents.
 */
class Nr1V11CampaignTrainingsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @return object{company: Company, survey: Survey, talents: User, admin: User}
     */
    private function campaign(bool $withResponses = false): object
    {
        $campaign = $this->v11Campaign($withResponses);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        foreach (SurveyAudienceLink::AUDIENCES as $audience) {
            SurveyAudienceLink::query()->create(['survey_id' => $campaign->survey->id, 'audience' => $audience, 'token' => Str::random(40), 'enabled' => true]);
        }

        return (object) [
            'company' => $campaign->company,
            'survey' => $campaign->survey->fresh(),
            'talents' => User::factory()->talentsOwner()->create(['name' => 'Talents Owner']),
            'admin' => User::factory()->companyAdmin($campaign->company->id)->create(),
        ];
    }

    /** @return array<string, mixed> */
    private function onlineData(array $overrides = []): array
    {
        return array_merge([
            'modality' => 'online',
            'audience' => 'time',
            'scope' => 'Trilha online de 4 módulos sobre organização do trabalho',
            'price_cents' => 480000,
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
            'link_url' => 'https://treino.example.com/time',
            'responsible' => 'Carla Souza',
            'released' => true,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function inPersonData(array $overrides = []): array
    {
        return array_merge([
            'modality' => 'presencial',
            'audience' => 'lideres',
            'scope' => 'Oficina presencial de 4 horas',
            'location' => 'Auditório da matriz',
            'event_at' => '2026-12-03 09:00:00',
            'facilitator' => 'Marina Lopes',
            'capacity' => 30,
        ], $overrides);
    }

    private function contractUrl(object $c): string
    {
        return route('admin.companies.surveys.campaign.contract', [$c->company->id, $c->survey->id]);
    }

    private function requestUrl(object $c, string $modality = 'online', string $audience = 'time'): string
    {
        return route('client.surveys.trainings.request', [$c->survey->id, $modality, $audience]);
    }

    public function test_company_sees_orientation_always_and_nothing_contracted_by_default(): void
    {
        $c = $this->campaign();

        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaign.orientation.availability', 'Disponível para todos')
                ->has('campaign.orientation.paragraphs', 4)
                ->has('campaign.trainings', 2)
                ->where('campaign.trainings.0.label', 'Treinamento Online')
                ->where('campaign.trainings.1.label', 'Treinamento Presencial')
                ->where('campaign.trainings.0.rows.0.audience_label', 'Time')
                ->where('campaign.trainings.0.rows.1.audience_label', 'Líderes')
                ->where('campaign.trainings.0.rows.0.state', 'not_contracted')
                ->where('campaign.trainings.0.rows.0.training', null)
                ->where('canRequestProposal', true));
    }

    public function test_talents_marks_online_and_in_person_as_contracted_and_the_company_sees_them_highlighted(): void
    {
        $c = $this->campaign();
        $file = UploadedFile::fake()->create('apostila.pdf', 100, 'application/pdf');

        $this->actingAs($c->talents)->post($this->contractUrl($c), $this->onlineData(['material' => $file]))->assertSessionHas('success');
        $this->actingAs($c->talents)->post($this->contractUrl($c), $this->inPersonData())->assertSessionHas('success');

        $online = Nr1Training::query()->where('modality', 'online')->where('audience', 'time')->sole();
        $this->assertTrue($online->isContracted());
        $this->assertSame($c->talents->id, $online->contracted_by);
        $this->assertSame(480000, $online->price_cents);
        $this->assertSame('apostila.pdf', $online->material_name);
        Storage::disk('local')->assertExists($online->material_path);
        $this->assertTrue($online->isAvailableToEmployees());

        $presencial = Nr1Training::query()->where('modality', 'presencial')->where('audience', 'lideres')->sole();
        $this->assertSame([30, 'Auditório da matriz', 'Marina Lopes'], [$presencial->capacity, $presencial->location, $presencial->facilitator]);
        $this->assertFalse($presencial->isAvailableToEmployees(), 'Contratado, mas não liberado ao público.');

        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertInertia(fn (Assert $page) => $page
            ->where('campaign.trainings.0.rows.0.state', 'contracted')
            ->where('campaign.trainings.0.rows.0.training.responsible', 'Carla Souza')
            ->where('campaign.trainings.0.rows.0.training.availability', 'Liberado ao público')
            ->where('campaign.trainings.0.rows.1.state', 'not_contracted')
            ->where('campaign.trainings.1.rows.1.state', 'contracted')
            ->where('campaign.trainings.1.rows.1.training.event_at', '03/12/2026 09:00')
            ->missing('campaign.trainings.0.rows.0.training.price_cents'));
    }

    public function test_contracting_validates_the_required_fields_of_each_modality(): void
    {
        $c = $this->campaign();
        $post = fn (array $data) => $this->actingAs($c->talents)->post($this->contractUrl($c), $data);

        $post($this->onlineData(['link_url' => null]))->assertSessionHas('error');
        $post($this->onlineData(['scope' => '']))->assertSessionHasErrors('scope');
        $post($this->onlineData(['ends_on' => '2026-10-01']))->assertSessionHas('error');
        $post($this->inPersonData(['location' => '']))->assertSessionHas('error');
        $post($this->inPersonData(['capacity' => null]))->assertSessionHas('error');
        $post($this->inPersonData(['attendance' => 31]))->assertSessionHas('error');
        $post($this->onlineData(['modality' => 'hibrido']))->assertSessionHasErrors('modality');
        $post($this->onlineData(['audience' => 'todos']))->assertSessionHasErrors('audience');
        $post($this->onlineData(['link_url' => 'não é url']))->assertSessionHasErrors('link_url');

        $this->assertSame(0, Nr1Training::query()->count());
    }

    public function test_uncontracting_hides_the_training_from_the_company_and_from_employees(): void
    {
        $c = $this->campaign();
        $this->actingAs($c->talents)->post($this->contractUrl($c), $this->onlineData());

        $this->actingAs($c->talents)->post(route('admin.companies.surveys.campaign.uncontract', [$c->company->id, $c->survey->id]), ['modality' => 'online', 'audience' => 'time'])
            ->assertSessionHas('success');

        $training = Nr1Training::query()->sole();
        $this->assertFalse($training->isContracted());
        $this->assertFalse($training->isAvailableToEmployees());
        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))
            ->assertInertia(fn (Assert $page) => $page->where('campaign.trainings.0.rows.0.state', 'not_contracted')->where('campaign.trainings.0.rows.0.training', null));
    }

    public function test_t07_request_proposal_creates_the_opportunity_only_on_explicit_post(): void
    {
        $c = $this->campaign();

        // Ver a tela não cria nada.
        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertOk();
        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertOk();
        $this->assertSame(0, Nr1Opportunity::query()->count());

        $this->actingAs($c->admin)->post($this->requestUrl($c))->assertSessionHas('success');

        $opportunity = Nr1Opportunity::query()->sole();
        $this->assertSame(
            [$c->company->id, $c->survey->id, 'training_request', 'online', 'time', $c->admin->id, 'new'],
            [$opportunity->company_id, $opportunity->survey_id, $opportunity->origin, $opportunity->modality, $opportunity->audience, $opportunity->requested_by, $opportunity->status],
        );
        $this->assertNotNull($opportunity->created_at);
        $this->assertSame('Novo', Nr1Opportunity::STATUS_LABELS[$opportunity->status]);
        $this->assertSame(['created'], Nr1OpportunityEvent::query()->pluck('action')->all());

        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertInertia(fn (Assert $page) => $page
            ->where('campaign.trainings.0.rows.0.state', 'requested'));
    }

    public function test_t07_blocks_a_duplicate_open_request_for_the_same_modality_and_audience(): void
    {
        $c = $this->campaign();

        $this->actingAs($c->admin)->post($this->requestUrl($c))->assertSessionHas('success');
        $this->actingAs($c->admin)->post($this->requestUrl($c))->assertSessionHas('error');
        $this->assertSame(1, Nr1Opportunity::query()->count());

        // Outro público, outra modalidade e outra campanha seguem livres.
        $this->actingAs($c->admin)->post($this->requestUrl($c, 'online', 'lideres'))->assertSessionHas('success');
        $this->actingAs($c->admin)->post($this->requestUrl($c, 'presencial', 'time'))->assertSessionHas('success');
        $this->assertSame(3, Nr1Opportunity::query()->count());

        // Fechado (Perdido): um novo pedido é permitido.
        $first = Nr1Opportunity::query()->where('modality', 'online')->where('audience', 'time')->sole();
        $first->forceFill(['status' => Nr1Opportunity::STATUS_LOST, 'open_key' => null])->save();
        $this->actingAs($c->admin)->post($this->requestUrl($c))->assertSessionHas('success');
        $this->assertSame(4, Nr1Opportunity::query()->count());
    }

    public function test_t07_database_unique_key_holds_even_if_the_service_check_is_bypassed(): void
    {
        $c = $this->campaign();
        $key = Nr1Opportunity::openKey($c->survey->id, 'training_request', 'online', 'time');
        $attributes = ['company_id' => $c->company->id, 'survey_id' => $c->survey->id, 'origin' => 'training_request', 'modality' => 'online', 'audience' => 'time', 'status' => 'new', 'last_activity_at' => now(), 'open_key' => $key];

        Nr1Opportunity::query()->create($attributes);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Nr1Opportunity::query()->create($attributes);
    }

    public function test_marking_as_contracted_closes_the_open_request_as_won_and_contracted_cannot_be_requested(): void
    {
        $c = $this->campaign();
        $this->actingAs($c->admin)->post($this->requestUrl($c))->assertSessionHas('success');

        $this->actingAs($c->talents)->post($this->contractUrl($c), $this->onlineData())->assertSessionHas('success');

        $opportunity = Nr1Opportunity::query()->sole();
        $this->assertSame('won', $opportunity->status);
        $this->assertNull($opportunity->open_key);
        $this->assertSame(['created', 'status_changed'], Nr1OpportunityEvent::query()->pluck('action')->all());

        $this->actingAs($c->admin)->post($this->requestUrl($c))->assertSessionHas('error');
        $this->assertSame(1, Nr1Opportunity::query()->count());
    }

    public function test_request_proposal_authorization_per_profile(): void
    {
        $c = $this->campaign();

        // Usuário da empresa sem edição em Pesquisas: 403. Com a permissão de edição: cria.
        $user = User::factory()->companyUser($c->company->id)->create();
        $workspaceId = $user->workspaces()->firstOrFail()->id;
        foreach ([PermissionAction::View] as $action) {
            UserPermission::query()->create(['user_workspace_id' => $workspaceId, 'module' => PermissionModule::Pesquisas->value, 'action' => $action->value]);
        }
        $this->actingAs($user)->post($this->requestUrl($c))->assertForbidden();
        $this->actingAs($user)->get(route('client.surveys.show', $c->survey))->assertInertia(fn (Assert $page) => $page->where('canRequestProposal', false));

        UserPermission::query()->create(['user_workspace_id' => $workspaceId, 'module' => PermissionModule::Pesquisas->value, 'action' => PermissionAction::Edit->value]);
        $this->actingAs($user)->post($this->requestUrl($c))->assertSessionHas('success');

        // Outra empresa: 404. Talents e super admin comum não pedem pelo lado da empresa.
        $other = $this->v11Campaign(false);
        $otherAdmin = User::factory()->companyAdmin($other->company->id)->create();
        $this->actingAs($otherAdmin)->post($this->requestUrl($c, 'online', 'lideres'))->assertNotFound();
        $this->actingAs($c->talents)->post($this->requestUrl($c, 'online', 'lideres'))->assertRedirect();
        $this->assertSame(1, Nr1Opportunity::query()->count());

        // Rota inexistente para modalidade ou público inventados.
        $this->actingAs($c->admin)->post(route('client.surveys.show', $c->survey).'/treinamentos/hibrido/time/proposta')->assertNotFound();

        // Campanha antiga (não V11): sem cartões e sem pedido.
        $old = $this->createSurveyFixture();
        $oldAdmin = User::factory()->companyAdmin($old->company->id)->create();
        $this->actingAs($oldAdmin)->get(route('client.surveys.show', $old->survey))->assertInertia(fn (Assert $page) => $page->where('campaign', null)->where('canRequestProposal', false));
        $this->actingAs($oldAdmin)->post(route('client.surveys.trainings.request', [$old->survey->id, 'online', 'time']))->assertSessionHas('error');
        $this->assertSame(1, Nr1Opportunity::query()->count());
    }

    public function test_company_and_plain_admins_cannot_mark_contracted_or_edit_eligibles(): void
    {
        $c = $this->campaign();
        $eligibleUrl = route('admin.companies.surveys.campaign.eligible', [$c->company->id, $c->survey->id]);
        $uncontractUrl = route('admin.companies.surveys.campaign.uncontract', [$c->company->id, $c->survey->id]);
        $showUrl = route('admin.companies.surveys.campaign.show', [$c->company->id, $c->survey->id]);
        $plain = User::factory()->superAdmin()->create(['is_owner' => false]);

        foreach ([$c->admin, $plain] as $user) {
            $this->actingAs($user)->post($this->contractUrl($c), $this->onlineData())->assertForbidden();
            $this->actingAs($user)->post($uncontractUrl, ['modality' => 'online', 'audience' => 'time'])->assertForbidden();
            $this->actingAs($user)->post($eligibleUrl, ['eligible_count' => 50, 'eligible_source' => 'RH'])->assertForbidden();
            $this->actingAs($user)->get($showUrl)->assertForbidden();
        }

        $this->assertSame(0, Nr1Training::query()->count());
        $this->assertNull(SurveyCampaignRegistration::query()->first()?->eligible_count);
    }

    public function test_talents_page_renders_forms_and_is_scoped_to_company_and_v11(): void
    {
        $c = $this->campaign();

        $this->actingAs($c->talents)->get(route('admin.companies.surveys.campaign.show', [$c->company->id, $c->survey->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Surveys/Campaign')
                ->has('campaign.trainings', 2)
                ->where('campaign.trainings.0.rows.0.state', 'not_contracted'));

        $this->actingAs($c->talents)->get(route('admin.companies.surveys.campaign.show', [$c->company->id + 999, $c->survey->id]))->assertNotFound();
        $old = $this->createSurveyFixture();
        $this->actingAs($c->talents)->get(route('admin.companies.surveys.campaign.show', [$old->company->id, $old->survey->id]))->assertNotFound();
    }

    public function test_link_and_qr_are_visible_only_while_the_campaign_is_open(): void
    {
        $c = $this->campaign();
        $link = $c->survey->audienceLinks()->where('audience', 'time')->firstOrFail();

        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertInertia(fn (Assert $page) => $page
            ->where('audienceLinks.0.open', true)
            ->whereType('audienceLinks.0.url', 'string')
            ->whereType('audienceLinks.0.qr_url', 'string'));
        $this->actingAs($c->admin)->get(route('client.surveys.audiences.qr', [$c->survey, $link]))->assertOk();

        // Campanha encerrada: nem o link nem o QR (a rota também recusa).
        $c->survey->forceFill(['ends_at' => now()->subDay()])->save();
        $response = $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('audienceLinks.0.open', false)
            ->where('audienceLinks.0.url', null)
            ->where('audienceLinks.0.qr_url', null)
            ->where('campaign.collection.open', false));
        $this->assertStringNotContainsString($link->token, $response->getContent());
        $this->actingAs($c->admin)->get(route('client.surveys.audiences.qr', [$c->survey, $link]))->assertNotFound();

        // Campanha aberta, mas o link do público desabilitado.
        $c->survey->forceFill(['ends_at' => now()->addMonth()])->save();
        $link->forceFill(['enabled' => false])->save();
        $this->actingAs($c->admin)->get(route('client.surveys.audiences.qr', [$c->survey, $link]))->assertNotFound();
    }

    public function test_participation_card_and_eligibles_only_edited_by_talents(): void
    {
        $c = $this->campaign(withResponses: true);
        $eligibleUrl = route('admin.companies.surveys.campaign.eligible', [$c->company->id, $c->survey->id]);

        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertInertia(fn (Assert $page) => $page
            ->where('campaign.participation.completed', 40)
            ->where('campaign.participation.eligible', null)
            ->where('campaign.participation.percent_label', null));

        // Elegíveis menor que as respostas é recusado; a Talents informa com a fonte.
        $this->actingAs($c->talents)->post($eligibleUrl, ['eligible_count' => 30, 'eligible_source' => 'Cadastro de RH'])->assertSessionHasErrors('eligible_count');
        $this->actingAs($c->talents)->post($eligibleUrl, ['eligible_count' => 48, 'eligible_source' => ''])->assertSessionHasErrors('eligible_source');
        $this->actingAs($c->talents)->post($eligibleUrl, ['eligible_count' => 48, 'eligible_source' => 'Cadastro de RH de outubro'])->assertSessionHas('success');

        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertInertia(fn (Assert $page) => $page
            ->where('campaign.participation.eligible', 48)
            ->where('campaign.participation.percent_label', '83,3%')
            ->where('campaign.participation.eligible_source', 'Cadastro de RH de outubro'));
    }

    public function test_empty_state_without_responses(): void
    {
        $c = $this->campaign(withResponses: false);

        $this->actingAs($c->admin)->get(route('client.surveys.show', $c->survey))->assertInertia(fn (Assert $page) => $page
            ->where('campaign.participation.completed', 0)
            ->where('campaign.participation.percent_label', null));
    }
}
