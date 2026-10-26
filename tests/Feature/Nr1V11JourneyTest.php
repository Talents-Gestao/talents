<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Nr1\Nr1TrainingService;
use App\Support\Nr1InstrumentMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * «Minha jornada» (Caderno §7): orientação sempre visível; treinamento só do que foi contratado E liberado ao público
 * do link (T05, T06); nunca resultado, oferta, preço ou dado de outro colaborador.
 */
class Nr1V11JourneyTest extends TestCase
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
     * @return object{survey: Survey, talents: User, time: SurveyAudienceLink, leaders: SurveyAudienceLink, questionIds: array<int, int>}
     */
    private function campaign(): object
    {
        $campaign = $this->v11Campaign(false);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $links = [];
        foreach (SurveyAudienceLink::AUDIENCES as $audience) {
            $links[$audience] = SurveyAudienceLink::query()->create(['survey_id' => $campaign->survey->id, 'audience' => $audience, 'token' => Str::random(40), 'enabled' => true]);
        }

        return (object) [
            'survey' => $campaign->survey->fresh(),
            'talents' => User::factory()->talentsOwner()->create(),
            'time' => $links['time'],
            'leaders' => $links['lideres'],
            'questionIds' => $campaign->questionIds,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function contract(object $c, string $modality, string $audience, bool $released, array $overrides = [], ?UploadedFile $file = null): Nr1Training
    {
        return app(Nr1TrainingService::class)->contract($c->survey, $c->talents, $modality, $audience, array_merge($modality === 'online'
            ? ['scope' => 'Trilha online de organização do trabalho', 'link_url' => 'https://treino.example.com/'.$audience, 'price_cents' => 987600, 'released' => $released]
            : ['scope' => 'Oficina presencial', 'location' => 'Sala 3', 'event_at' => '2026-12-03 09:00:00', 'capacity' => 20, 'facilitator' => 'Marina', 'price_cents' => 987600, 'released' => $released], $overrides), $file);
    }

    public function test_orientation_is_always_visible_and_the_questionnaire_is_one_statement_per_step(): void
    {
        $c = $this->campaign();

        $this->get(route('survey.public', $c->time->token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Survey/Take')
                ->where('journey.orientation.availability', 'Disponível para todos')
                ->has('journey.orientation.paragraphs', 4)
                ->where('journey.audience_label', 'Time')
                ->has('journey.trainings', 0)
                ->where('likertLabels.1', 'Nunca')
                ->where('likertLabels.2', 'Raramente')
                ->where('likertLabels.3', 'Às vezes')
                ->where('likertLabels.4', 'Frequentemente')
                ->where('likertLabels.5', 'Sempre')
                ->has('survey.template.sections', 7));
    }

    public function test_t05_only_contracted_trainings_reach_the_employee(): void
    {
        $c = $this->campaign();

        // Registro existente, mas sem contratação: nada aparece.
        Nr1Training::query()->create(['survey_id' => $c->survey->id, 'modality' => 'online', 'audience' => 'time', 'scope' => 'Rascunho', 'released_at' => now()]);
        $this->get(route('survey.public', $c->time->token))->assertInertia(fn (Assert $page) => $page->has('journey.trainings', 0));

        // Contratado e liberado: aparece, com o que o colaborador precisa e sem preço.
        Nr1Training::query()->delete();
        $this->contract($c, 'online', 'time', true);
        $response = $this->get(route('survey.public', $c->time->token));
        $response->assertInertia(fn (Assert $page) => $page
            ->has('journey.trainings', 1)
            ->where('journey.trainings.0.label', 'Treinamento Online')
            ->where('journey.trainings.0.link_url', 'https://treino.example.com/time')
            ->missing('journey.trainings.0.price_cents'));
        $this->assertStringNotContainsString('987600', $response->getContent());

        // Contratação removida: some.
        app(Nr1TrainingService::class)->uncontract($c->survey, $c->talents, 'online', 'time');
        $this->get(route('survey.public', $c->time->token))->assertInertia(fn (Assert $page) => $page->has('journey.trainings', 0));
    }

    public function test_t06_only_what_is_released_to_the_audience_of_the_link_appears(): void
    {
        $c = $this->campaign();
        $this->contract($c, 'online', 'time', true);
        $this->contract($c, 'presencial', 'time', false);
        $this->contract($c, 'online', 'lideres', true, ['link_url' => 'https://treino.example.com/lideres']);
        $this->contract($c, 'presencial', 'lideres', true);

        // Time: só o online liberado (o presencial contratado não foi liberado; os de Líderes não são dele).
        $this->get(route('survey.public', $c->time->token))->assertInertia(function (Assert $page) {
            $page->has('journey.trainings', 1)->where('journey.trainings.0.modality', 'online');
            $this->assertStringNotContainsString('lideres', json_encode($page->toArray()['props']['journey']['trainings']));
        });

        // Líderes: online e presencial, com local e data.
        $this->get(route('survey.public', $c->leaders->token))->assertInertia(fn (Assert $page) => $page
            ->has('journey.trainings', 2)
            ->where('journey.trainings.1.location', 'Sala 3')
            ->where('journey.trainings.1.event_at', '03/12/2026 09:00'));
    }

    public function test_the_journey_never_carries_results_offers_or_other_collaborators_data(): void
    {
        $c = $this->campaign();
        $this->contract($c, 'online', 'time', true);
        SurveyResponse::query()->create(['survey_id' => $c->survey->id, 'session_token' => 'sessao-de-outro-colaborador', 'completed_at' => now()]);

        $response = $this->get(route('survey.public', $c->time->token))->assertOk();

        $response->assertInertia(function (Assert $page) {
            $journey = $page->toArray()['props']['journey'];
            $this->assertEqualsCanonicalizing(['orientation', 'audience_label', 'trainings'], array_keys($journey));
            $this->assertEqualsCanonicalizing(
                ['id', 'modality', 'label', 'scope', 'period', 'link_url', 'material_name', 'material_url', 'location', 'event_at', 'facilitator'],
                array_keys($journey['trainings'][0]),
            );
        });

        $content = $response->getContent();
        foreach (['sessao-de-outro-colaborador', 'average_score', 'risk_level', 'Solicitar proposta', 'Solicitar conversa', 'nr1_opportunities', 'overall'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $content);
        }
    }

    public function test_survey_level_token_without_audience_shows_no_training(): void
    {
        $campaign = $this->v11Campaign(false);
        $survey = $campaign->survey;
        $talents = User::factory()->talentsOwner()->create();
        app(Nr1TrainingService::class)->contract($survey, $talents, 'online', 'time', ['scope' => 'Trilha', 'link_url' => 'https://treino.example.com/', 'released' => true]);

        $this->get(route('survey.public', $survey->public_token))->assertInertia(fn (Assert $page) => $page
            ->where('journey.audience_label', null)
            ->has('journey.trainings', 0));
    }

    public function test_old_campaigns_keep_the_previous_questionnaire_without_journey(): void
    {
        $old = $this->createSurveyFixture();

        $this->get(route('survey.public', $old->survey->public_token))->assertInertia(fn (Assert $page) => $page->where('journey', null));
    }

    public function test_training_file_only_for_the_released_audience_with_the_campaign_open(): void
    {
        $c = $this->campaign();
        $file = UploadedFile::fake()->create('apostila.pdf', 50, 'application/pdf');
        $training = $this->contract($c, 'online', 'time', true, [], $file);
        $url = route('survey.public.training-material', [$c->time->token, $training->id]);

        $this->get(route('survey.public', $c->time->token))->assertInertia(fn (Assert $page) => $page
            ->where('journey.trainings.0.material_name', 'apostila.pdf')
            ->where('journey.trainings.0.material_url', $url));
        $this->get($url)->assertOk()->assertDownload('apostila.pdf');

        // Outro público, token inexistente e treinamento de outra campanha: 404.
        $this->get(route('survey.public.training-material', [$c->leaders->token, $training->id]))->assertNotFound();
        $this->get(route('survey.public.training-material', ['token-inexistente', $training->id]))->assertNotFound();
        $this->get(route('survey.public.training-material', [$c->time->token, $training->id + 999]))->assertNotFound();

        // Não liberado ou não contratado: 404.
        $training->forceFill(['released_at' => null])->save();
        $this->get($url)->assertNotFound();
        $training->forceFill(['released_at' => now(), 'contracted_at' => null])->save();
        $this->get($url)->assertNotFound();

        // Campanha encerrada: 404.
        $training->forceFill(['contracted_at' => now()])->save();
        $c->survey->forceFill(['ends_at' => now()->subDay()])->save();
        $this->get($url)->assertNotFound();
    }

    public function test_all_39_statements_are_submitted_once_and_counted(): void
    {
        $c = $this->campaign();
        $answers = [];
        foreach (Nr1InstrumentMatrix::forTemplate((int) $c->survey->survey_template_id) as $row) {
            $answers[$row['question_id']] = 3;
        }
        $this->assertCount(39, $answers);

        $this->post(route('survey.public.submit', $c->time->token), ['answers' => $answers])->assertRedirect();

        $this->assertSame(1, $c->survey->completedResponses()->count());
    }
}
