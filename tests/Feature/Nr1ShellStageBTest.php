<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Nr1\Nr1TrainingService;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Shell NR-1, Etapa B: Visão geral, Campanha NR-1 e Minha jornada (dados que alimentam as telas fiéis ao HTML).
 */
class Nr1ShellStageBTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    /** @return object{company: Company, survey: Survey, owner: User, admin: User} */
    private function campaign(): object
    {
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();

        return (object) [
            'company' => $c->company,
            'survey' => $c->survey->fresh(),
            'owner' => User::factory()->talentsOwner()->create(),
            'admin' => User::factory()->companyAdmin($c->company->id)->create(),
        ];
    }

    private function contract(object $c, string $modality, string $audience, bool $released): Nr1Training
    {
        return app(Nr1TrainingService::class)->contract($c->survey, $c->owner, $modality, $audience, $modality === 'online'
            ? ['scope' => 'Trilha online', 'link_url' => 'https://treino.example.com/'.$audience, 'price_cents' => 987600, 'released' => $released]
            : ['scope' => 'Oficina', 'location' => 'Sala 3', 'event_at' => '2026-12-03 09:00:00', 'capacity' => 20, 'price_cents' => 987600, 'released' => $released]);
    }

    public function test_the_overview_carries_the_real_numbers_of_the_appendix_for_talents_and_the_company(): void
    {
        $c = $this->campaign();

        foreach ([$c->owner, $c->admin] as $user) {
            $this->actingAs($user)->get(route('nr1.visao-geral'))->assertOk()->assertInertia(fn (Assert $p) => $p
                ->where('section.overview.hasResults', true)
                ->where('section.overview.cards.overall.score_label', '2,60')
                ->where('section.overview.cards.attention.count', 6)
                ->where('section.overview.cards.attention.total', 7)
                ->where('section.overview.cards.participation.hidden', false)
                ->has('section.overview.strengths', 3)
                ->has('section.overview.dimensions', 7)
                ->has('section.overview.attention', 6)
                ->where('section.overview.strengths.0.item', 7)
                ->where('section.overview.strengths.0.pct_label', '92,5%'));
        }
    }

    public function test_the_overview_payload_has_no_individual_answers_or_session_tokens(): void
    {
        $c = $this->campaign();

        $json = json_encode($this->actingAs($c->admin)->get(route('nr1.visao-geral'))->viewData('page')['props']['section']['overview']);

        $this->assertStringNotContainsString('session_token', (string) $json);
        $this->assertStringNotContainsString('survey_response', (string) $json);
        $this->assertStringNotContainsString('department_id', (string) $json);
    }

    public function test_the_company_overview_hides_participation_below_the_minimum_group(): void
    {
        $c = $this->v11Campaign(false);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $admin = User::factory()->companyAdmin($c->company->id)->create();
        $this->seedAppendixResponses($c->survey, $c->questionIds);
        // Deixa só 3 respostas completas (abaixo do grupo mínimo de 5).
        SurveyResponse::query()->where('survey_id', $c->survey->id)->orderBy('id')->skip(3)->take(100)->get()->each->delete();
        app(SurveyResultCalculator::class)->recalculate($c->survey->fresh());

        $props = $this->actingAs($admin)->get(route('nr1.visao-geral'))->assertOk()->viewData('page')['props'];

        $this->assertNull($props['context']['survey']['respondents']);
        $overview = $props['section']['overview'];
        if ($overview['hasResults']) {
            $this->assertTrue($overview['cards']['participation']['hidden']);
            $this->assertNull($overview['respondents']);
            $this->assertSame([], $overview['groups']);
        }
    }

    public function test_the_campaign_page_gives_talents_the_contract_tools_and_the_company_only_the_request(): void
    {
        $c = $this->campaign();
        $this->contract($c, 'online', 'time', true);

        $this->actingAs($c->owner)->get(route('nr1.campanha'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.campaign.questionnaire.questions', 39)->where('section.campaign.questionnaire.dimensions', 7)
            ->where('section.campaign.trainings.0.rows.0.state', 'contracted')
            ->where('section.campaign.trainings.0.rows.1.state', 'not_contracted')
            ->where('section.campaign.contract.contract', route('admin.companies.surveys.campaign.contract', [$c->company->id, $c->survey->id]))
            ->where('section.campaign.eligibleEditor.url', route('admin.companies.surveys.campaign.eligible', [$c->company->id, $c->survey->id]))
            ->where('section.campaign.canRequest', false));

        $page = $this->actingAs($c->admin)->get(route('nr1.campanha'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('section.campaign.contract', null)->where('section.campaign.eligibleEditor', null)
            ->where('section.campaign.canRequest', true)
            ->where('section.campaign.trainings.0.rows.1.request_url', route('client.surveys.trainings.request', [$c->survey->id, 'online', 'lideres']))
            ->where('section.campaign.trainings.0.rows.0.request_url', null));

        // A empresa nunca recebe preço nem campos de edição da Talents.
        $json = (string) json_encode($page->viewData('page')['props']['section']['campaign']);
        $this->assertStringNotContainsString('price_cents', $json);
        $this->assertStringNotContainsString('987600', $json);
        $this->assertStringNotContainsString('starts_on_raw', $json);
    }

    public function test_the_company_request_button_posts_to_the_existing_route_and_comes_back(): void
    {
        $c = $this->campaign();

        $this->actingAs($c->admin)->from(route('nr1.campanha'))
            ->post(route('client.surveys.trainings.request', [$c->survey->id, 'presencial', 'lideres']))
            ->assertRedirect(route('nr1.campanha'));

        $this->actingAs($c->admin)->get(route('nr1.campanha'))->assertInertia(fn (Assert $p) => $p
            ->where('section.campaign.trainings.1.rows.1.state', 'requested')->where('section.campaign.trainings.1.rows.1.request_url', null));
    }

    public function test_the_collaborator_journey_shows_only_the_released_training_of_the_own_audience(): void
    {
        $c = $this->campaign();
        $links = [];
        foreach (SurveyAudienceLink::AUDIENCES as $audience) {
            $links[$audience] = SurveyAudienceLink::query()->create(['survey_id' => $c->survey->id, 'audience' => $audience, 'token' => Str::random(40), 'enabled' => true]);
        }
        $this->contract($c, 'online', 'time', true);
        $this->contract($c, 'presencial', 'time', false); // contratado, mas ainda não liberado
        $this->contract($c, 'online', 'lideres', true);   // de outro público

        foreach (['minha-jornada', 'campanha'] as $section) {
            $this->get(route('nr1.jornada', [$links['time']->token, $section]))->assertOk()->assertInertia(fn (Assert $p) => $p
                ->where('shell.profile', 'colaborador')
                ->where('section.journey.audience_label', 'Time')
                ->has('section.journey.trainings', 1)
                ->where('section.journey.trainings.0.modality', 'online')
                ->where('section.journey.questionnaire.questions', 39)
                ->where('section.journey.surveyUrl', route('survey.public', $links['time']->token))
                ->where('section.journey.orientation.availability', 'Disponível para todos'));
        }

        $json = (string) json_encode($this->get(route('nr1.jornada', $links['time']->token))->viewData('page')['props']);
        $this->assertStringNotContainsString('price_cents', $json);
        $this->assertStringNotContainsString('987600', $json);
        $this->assertStringNotContainsString($links['lideres']->token, $json);
        $this->assertStringNotContainsString('Sala 3', $json);
    }
}
