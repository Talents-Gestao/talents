<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Nr1Opportunity;
use App\Models\Nr1OpportunityEvent;
use App\Models\Survey;
use App\Models\User;
use App\Services\Nr1\Nr1OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * A empresa vê o andamento do próprio pedido de conversa ou proposta em Soluções (Solicitado, Em contato, Proposta
 * enviada, Encerrado), sem ver nada interno do Comercial.
 */
class Nr1V11SolutionRequestStatusTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private const SECRET = 'anotacao-interna-do-comercial';

    /** @return object{company: mixed, survey: Survey, admin: User} */
    private function campaign(): object
    {
        $campaign = $this->v11Campaign(true);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();

        return (object) [
            'company' => $campaign->company,
            'survey' => $campaign->survey->fresh(),
            'admin' => User::factory()->companyAdmin($campaign->company->id)->create(),
        ];
    }

    private function requests(object $c, ?User $as = null): array
    {
        $page = $this->actingAs($as ?? $c->admin)->get(route('client.surveys.solutions', $c->survey))->assertOk();

        return $page->viewData('page')['props']['requests'];
    }

    public function test_status_follows_the_commercial_queue_with_the_four_company_labels(): void
    {
        $c = $this->campaign();
        $training = app(Nr1OpportunityService::class)->requestTraining($c->survey, $c->admin, 'online', 'time');
        $solution = app(Nr1OpportunityService::class)->openSolution($c->survey, Nr1Opportunity::ORIGIN_SOLUTION_REQUEST, 'Clima e escuta', 'Pedido.', $c->admin);

        $labels = fn () => collect($this->requests($c))->pluck('status_label', 'id')->all();
        $this->assertSame([$solution->id => 'Solicitado', $training->id => 'Solicitado'], $labels());

        foreach ([
            [Nr1Opportunity::STATUS_IN_CONTACT, 'Em contato'],
            [Nr1Opportunity::STATUS_PROPOSAL_SENT, 'Proposta enviada'],
            [Nr1Opportunity::STATUS_WON, 'Encerrado'],
        ] as [$status, $label]) {
            $training->forceFill(['status' => $status])->save();
            $this->assertSame($label, $labels()[$training->id], $status);
        }

        // «Perdido» também é «Encerrado»: a empresa não vê o desfecho nem o motivo.
        $solution->forceFill(['status' => Nr1Opportunity::STATUS_LOST, 'lost_reason' => self::SECRET])->save();
        $this->assertSame('Encerrado', $labels()[$solution->id]);
    }

    public function test_rows_describe_the_request_in_company_language(): void
    {
        $c = $this->campaign();
        app(Nr1OpportunityService::class)->requestTraining($c->survey, $c->admin, 'presencial', 'lideres');
        app(Nr1OpportunityService::class)->openSolution($c->survey, Nr1Opportunity::ORIGIN_SOLUTION_REQUEST, 'Clima e escuta', 'Pedido.', $c->admin);

        $this->actingAs($c->admin)->get(route('client.surveys.solutions', $c->survey))->assertInertia(fn (Assert $page) => $page
            ->has('requests', 2)
            ->where('requests.0.kind', 'Conversa sobre solução')
            ->where('requests.0.subject', 'Clima e escuta')
            ->where('requests.1.kind', 'Proposta de treinamento')
            ->where('requests.1.subject', 'Treinamento presencial · Líderes')
            ->where('requests.1.status', 'requested')
            ->whereType('requests.1.requested_at', 'string'));
    }

    public function test_nothing_internal_reaches_the_company(): void
    {
        $c = $this->campaign();
        $commercial = User::factory()->superAdmin()->create(['name' => 'Carla Comercial']);
        $opportunity = app(Nr1OpportunityService::class)->requestTraining($c->survey, $c->admin, 'online', 'time');
        $opportunity->forceFill([
            'status' => Nr1Opportunity::STATUS_PROPOSAL_SENT,
            'assigned_to' => $commercial->id,
            'next_action' => self::SECRET.'-proxima',
            'lost_reason' => self::SECRET.'-motivo',
            'need' => self::SECRET.'-necessidade',
        ])->save();
        Nr1OpportunityEvent::query()->create(['opportunity_id' => $opportunity->id, 'user_id' => $commercial->id, 'action' => 'note', 'body' => self::SECRET.'-historico']);

        $response = $this->actingAs($c->admin)->get(route('client.surveys.solutions', $c->survey))->assertOk();
        $content = $response->getContent();
        foreach (['-proxima', '-motivo', '-necessidade', '-historico'] as $suffix) {
            $this->assertStringNotContainsString(self::SECRET.$suffix, $content);
        }
        $this->assertStringNotContainsString('Carla Comercial', $content);
        $this->assertStringNotContainsString('Perdido', $content);

        // Lista fechada de campos.
        $this->assertEqualsCanonicalizing(
            ['id', 'kind', 'subject', 'status', 'status_label', 'requested_at', 'updated_at'],
            array_keys($response->viewData('page')['props']['requests'][0]),
        );
    }

    public function test_an_automatic_suggestion_is_never_shown_as_a_company_request_until_the_company_asks(): void
    {
        $c = $this->campaign();
        app(Nr1OpportunityService::class)->openSolution($c->survey, Nr1Opportunity::ORIGIN_SOLUTION_SUGGESTION, 'Clima e escuta', 'Em atenção.', null);

        $this->assertSame([], $this->requests($c));

        // A empresa pede conversa sobre a sugestão: passa a ser pedido dela e aparece como «Solicitado».
        $suggestion = Nr1Opportunity::query()->sole();
        $suggestion->forceFill(['origin' => Nr1Opportunity::ORIGIN_SOLUTION_REQUEST, 'status' => Nr1Opportunity::STATUS_NEW, 'open_key' => 'x:y'])->save();
        $this->assertSame('Solicitado', $this->requests($c)[0]['status_label']);
    }

    public function test_only_the_companys_own_requests_of_this_campaign_are_listed(): void
    {
        $c = $this->campaign();
        app(Nr1OpportunityService::class)->requestTraining($c->survey, $c->admin, 'online', 'time');

        $other = $this->v11Campaign(false);
        $other->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $outsider = User::factory()->companyAdmin($other->company->id)->create();
        app(Nr1OpportunityService::class)->requestTraining($other->survey, $outsider, 'presencial', 'lideres');

        $this->assertCount(1, $this->requests($c));
        $this->assertSame('Treinamento online · Time', $this->requests($c)[0]['subject']);

        // Outra empresa não abre a página desta campanha.
        $this->actingAs($outsider)->get(route('client.surveys.solutions', $c->survey))->assertNotFound();
    }

    public function test_the_page_shows_the_empty_state_and_stays_behind_publication(): void
    {
        $c = $this->campaign();
        $this->assertSame([], $this->requests($c));

        $c->survey->forceFill(['published_at' => null])->save();
        $this->actingAs($c->admin)->get(route('client.surveys.solutions', $c->survey))->assertNotFound();
    }
}
