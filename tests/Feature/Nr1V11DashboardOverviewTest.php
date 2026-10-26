<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Models\User;
use App\Services\PsychosocialDashboardBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Etapa 5, fatia 1 (Visão geral): cabeçalho, cards, pontos fortes, sete dimensões e lista completa
 * de dimensões em atenção, sobre o dataset do Apêndice A (40 respostas).
 */
class Nr1V11DashboardOverviewTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    /** Índices do Caderno (T14) com 4 casas; o banco grava 2 casas, então a comparação tem tolerância de 0,005. Autonomia (1,905) fica fora. */
    private const ATTENTION_SCORES = [3.3042, 2.7458, 2.6400, 2.5625, 2.4500, 2.4400];

    /**
     * @return array<string, mixed>
     */
    private function build(Survey $survey): array
    {
        return app(PsychosocialDashboardBuilder::class)->build($survey->fresh());
    }

    public function test_overall_index_is_shown_with_two_decimals_and_matches_the_caderno(): void
    {
        $campaign = $this->v11Campaign();
        $dashboard = $this->build($campaign->survey);

        $this->assertEqualsWithDelta(2.5974, (float) $dashboard['overall']['score'], 0.0051);
        $this->assertSame('2,60', $dashboard['overall']['score_label']);
        $this->assertSame('2,60', $dashboard['cards']['overall']['score_label']);
        $this->assertSame('Atenção', $dashboard['cards']['overall']['band_label']);
    }

    public function test_t14_attention_list_has_all_six_dimensions_in_descending_order(): void
    {
        $campaign = $this->v11Campaign();
        $dashboard = $this->build($campaign->survey);

        $this->assertCount(7, $dashboard['dimensions']);
        $this->assertCount(6, $dashboard['attention']);

        $scores = array_map(fn (array $d) => (float) $d['score'], $dashboard['attention']);
        foreach (self::ATTENTION_SCORES as $index => $expected) {
            $this->assertEqualsWithDelta($expected, $scores[$index], 0.0051, "Posição {$index} da lista de atenção.");
        }
        $this->assertStringContainsString('Demandas', $dashboard['attention'][0]['title']);

        $autonomy = collect($dashboard['dimensions'])->first(fn (array $d) => (float) $d['score'] < 2.0);
        $this->assertNotNull($autonomy);
        $this->assertEqualsWithDelta(1.905, (float) $autonomy['score'], 0.0051);
        $this->assertFalse($autonomy['in_attention']);
        $this->assertNotContains($autonomy['section_id'], array_column($dashboard['attention'], 'section_id'));

        $this->assertSame(6, $dashboard['cards']['attention']['count']);
        $this->assertSame(7, $dashboard['cards']['attention']['total']);
    }

    public function test_dimension_bars_carry_the_band_and_a_width_on_the_one_to_five_scale(): void
    {
        $campaign = $this->v11Campaign();
        $dashboard = $this->build($campaign->survey);

        foreach ($dashboard['dimensions'] as $dimension) {
            $expected = round(((float) $dimension['score'] - 1) / 4 * 100, 1);
            $this->assertEqualsWithDelta($expected, $dimension['bar_percent'], 0.05);
            $this->assertContains($dimension['risk_level'], ['green', 'yellow', 'red']);
            $this->assertNotSame('', $dimension['band_label']);
        }
    }

    public function test_strengths_show_favorable_percent_with_the_question_base(): void
    {
        $campaign = $this->v11Campaign();
        $dashboard = $this->build($campaign->survey);
        $q7 = $campaign->questionIds[7];
        $q8 = $campaign->questionIds[8];

        $this->assertLessThanOrEqual(5, count($dashboard['strengths']));
        $top = $dashboard['strengths'][0];
        $this->assertSame($q7, $top['id']);
        $this->assertSame(92.5, $top['favorable_pct']);
        $this->assertSame('92,5%', $top['favorable_pct_label']);
        $this->assertSame(40, $top['n']);

        $items = collect($dashboard['items'])->keyBy('id');
        $this->assertSame(37, $items[$q7]['counts'][4] + $items[$q7]['counts'][5], 'Q7: 37/40.');
        $this->assertSame(30, $items[$q8]['counts'][4] + $items[$q8]['counts'][5], 'Q8: 30/40.');
        $this->assertSame(75.0, $items[$q8]['favorable_pct']);

        $percents = array_column($dashboard['strengths'], 'favorable_pct');
        $sorted = $percents;
        rsort($sorted);
        $this->assertSame($sorted, $percents);
        foreach ($dashboard['strengths'] as $strength) {
            $this->assertSame(40, $strength['n']);
        }
    }

    public function test_participation_card_needs_eligible_count_and_never_invents_one(): void
    {
        $campaign = $this->v11Campaign();

        $missing = $this->build($campaign->survey)['cards']['participation'];
        $this->assertSame('missing', $missing['status']);
        $this->assertNull($missing['percent_label']);
        $this->assertNull($missing['eligible']);
        $this->assertSame(40, $missing['respondents']);

        SurveyCampaignRegistration::query()->create([
            'survey_id' => $campaign->survey->id,
            'company_id' => $campaign->company->id,
            'unit_name' => 'Unidade Centro',
            'eligible_count' => 48,
        ]);

        $card = $this->build($campaign->survey)['cards']['participation'];
        $this->assertSame('ok', $card['status']);
        $this->assertSame('83,3%', $card['percent_label']);
        $this->assertSame(48, $card['eligible']);

        SurveyCampaignRegistration::query()->where('survey_id', $campaign->survey->id)->update(['eligible_count' => 30]);
        $inconsistent = $this->build($campaign->survey)['cards']['participation'];
        $this->assertSame('inconsistent', $inconsistent['status']);
        $this->assertNull($inconsistent['percent_label']);
    }

    public function test_header_shows_real_company_unit_period_and_status(): void
    {
        $campaign = $this->v11Campaign(withResponses: false);
        SurveyCampaignRegistration::query()->create([
            'survey_id' => $campaign->survey->id,
            'company_id' => $campaign->company->id,
            'unit_name' => 'Unidade Centro',
        ]);

        $header = $this->build($campaign->survey)['header'];
        $this->assertSame('Empresa V11', $header['company_name']);
        $this->assertSame('Unidade Centro', $header['unit_name']);
        $this->assertSame('Campanha V11', $header['campaign_title']);
        $this->assertSame(
            $campaign->survey->starts_at->format('d/m/Y').' a '.$campaign->survey->ends_at->format('d/m/Y'),
            $header['period_label'],
        );
        $this->assertSame(['code' => 'collecting', 'label' => 'Em coleta'], $header['status']);

        $campaign->survey->update(['starts_at' => now()->addDays(3)]);
        $this->assertSame('scheduled', $this->build($campaign->survey)['header']['status']['code']);

        $campaign->survey->update(['starts_at' => now()->subDays(40), 'ends_at' => now()->subDay()]);
        $this->assertSame(['code' => 'closed', 'label' => 'Coleta encerrada'], $this->build($campaign->survey)['header']['status']);

        $campaign->survey->update(['ends_at' => now()->addDay(), 'status' => 'inactive']);
        $this->assertSame('inactive', $this->build($campaign->survey)['header']['status']['code']);
    }

    public function test_plan_card_counts_implemented_measures_over_the_total(): void
    {
        $campaign = $this->v11Campaign();
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->assertSame(['implemented' => 0, 'total' => 0], $this->build($campaign->survey)['cards']['plan']);

        foreach ([PsychosocialMeasure::STATUS_TO_APPROVE, PsychosocialMeasure::STATUS_PENDING, PsychosocialMeasure::STATUS_IMPLEMENTED] as $status) {
            PsychosocialMeasure::query()->create([
                'survey_id' => $campaign->survey->id,
                'company_id' => $campaign->company->id,
                'factor_label' => 'Demandas',
                'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
                'measure_description' => 'Medida '.$status,
                'origin' => PsychosocialMeasure::ORIGIN_MANUAL,
                'status' => $status,
                'implemented_at' => $status === PsychosocialMeasure::STATUS_IMPLEMENTED ? now() : null,
                'implemented_by' => $status === PsychosocialMeasure::STATUS_IMPLEMENTED ? $user->id : null,
            ]);
        }

        $this->assertSame(['implemented' => 1, 'total' => 3], $this->build($campaign->survey)['cards']['plan']);
    }

    public function test_t25_empty_campaign_shows_empty_state_without_zeroing_anything(): void
    {
        $campaign = $this->v11Campaign(withResponses: false);
        $dashboard = $this->build($campaign->survey);

        $this->assertFalse($dashboard['has_results']);
        $this->assertNull($dashboard['overall']);
        $this->assertNull($dashboard['cards']['overall']);
        $this->assertNull($dashboard['cards']['attention']['count']);
        $this->assertSame(0, $dashboard['cards']['attention']['total']);
        $this->assertNull($dashboard['cards']['participation']['percent_label']);
        $this->assertSame([], $dashboard['dimensions']);
        $this->assertSame([], $dashboard['attention']);
        $this->assertSame([], $dashboard['strengths']);
        $this->assertSame(['implemented' => 0, 'total' => 0], $dashboard['cards']['plan']);
    }

    public function test_page_receives_the_overview_blocks_and_stays_company_scoped(): void
    {
        $campaign = $this->v11Campaign();
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard', $campaign->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Surveys/PsychosocialDashboard')
                ->has('dashboard.header')
                ->has('dashboard.cards')
                ->has('dashboard.attention', 6)
                ->has('dashboard.dimensions', 7)
                ->where('dashboard.cards.overall.score_label', '2,60'));

        $other = $this->createSurveyFixture();
        $outsider = User::factory()->companyAdmin($other->company->id)->create();
        $this->actingAs($outsider)
            ->get(route('client.surveys.psychosocial-dashboard', $campaign->survey))
            ->assertNotFound();
    }

    public function test_aggregated_exports_do_not_carry_the_new_blocks(): void
    {
        $campaign = $this->v11Campaign();
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $json = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.export.json', $campaign->survey))
            ->assertOk()
            ->getContent();

        foreach (['"header"', '"cards"', '"strengths"', '"attention"', '"items"', 'Empresa V11'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }
}
