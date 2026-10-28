<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyGroupResult;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Etapa 2: "Outros Setores" sobre respostas originais, percentuais favorável/desfavorável por item
 * e adesão em percentual.
 */
class Nr1V11CalculationTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    /**
     * Uma pergunta, um valor por setor: sector => [n, valor].
     *
     * @param  array<string, array{0: int, 1: int}>  $sectors
     * @return object{fx: object, departments: array<string, Department>}
     */
    private function campaign(array $sectors, string $method = SurveyMethodVersion::CODE_TALENTS39_V1, array $overrides = []): object
    {
        $fx = $this->createSurveyFixture(['method_version_id' => SurveyMethodVersion::idForCode($method)] + $overrides);
        $departments = [];

        foreach ($sectors as $name => [$size, $value]) {
            $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => $name]);
            $departments[$name] = $department;

            for ($i = 0; $i < $size; $i++) {
                SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => (string) Str::uuid(),
                    'department_id' => $department->id,
                    'completed_at' => now()->startOfMonth(),
                ])->answers()->create(['survey_template_question_id' => $fx->question->id, 'value' => $value]);
            }
        }

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        return (object) ['fx' => $fx, 'departments' => $departments];
    }

    /** Produção 19, Manual 6 e 15 respostas em cinco setores pequenos (HTML V11). */
    private function t12Sectors(): array
    {
        return [
            'Produção' => [19, 3],
            'Manual' => [6, 3],
            'Costura' => [4, 5],
            'Diretoria' => [4, 1],
            'Escritório' => [3, 4],
            'Portaria' => [2, 2],
            'Limpeza' => [2, 3],
        ];
    }

    private function dashboard(Survey $survey): array
    {
        return app(PsychosocialDashboardBuilder::class)->build($survey->fresh());
    }

    public function test_t12_others_aggregates_15_responses_and_recalculates_from_the_originals(): void
    {
        $campaign = $this->campaign($this->t12Sectors());
        $survey = $campaign->fx->survey;

        $overall = SurveyGroupResult::query()
            ->where('survey_id', $survey->id)
            ->where('group_key', SurveyGroupResult::GROUP_OTHERS)
            ->whereNull('survey_template_section_id')
            ->firstOrFail();

        // 4×5 + 4×1 + 3×4 + 2×2 + 2×3 = 46 sobre 15 respostas: 3,0667 → 3,07.
        // A média das médias dos cinco setores daria (5+1+4+2+3)/5 = 3,00: o agregado não pode ser isso.
        $this->assertSame(15, (int) $overall->respondent_count);
        $this->assertSame(46, (int) $overall->score_numerator);
        $this->assertSame(15, (int) $overall->score_denominator);
        $this->assertEqualsWithDelta(3.07, (float) $overall->getRawOriginal('average_score'), 0.0001);
        $this->assertSame('yellow', $overall->risk_level);

        // Nenhuma resposta é descartada: o geral soma as 40 (121 / 40 = 3,025 → 3,03).
        $general = SurveyResult::query()->where('survey_id', $survey->id)->whereNull('department_id')->whereNull('survey_template_section_id')->firstOrFail();
        $this->assertSame(40, (int) $general->respondent_count);
        $this->assertSame(121, (int) $general->score_numerator);
        $this->assertEqualsWithDelta(3.03, (float) $general->getRawOriginal('average_score'), 0.0001);

        // As linhas dos setores pequenos continuam gravadas (uso interno); só a exibição é suprimida.
        $this->assertSame(7, SurveyResult::query()->where('survey_id', $survey->id)->whereNotNull('department_id')->whereNull('survey_template_section_id')->count());

        $dashboard = $this->dashboard($survey);
        $published = array_column($dashboard['sectors'], 'department_name');
        sort($published);
        $this->assertSame(['Manual', 'Produção'], $published);
        $this->assertSame('Outros Setores', $dashboard['others']['name']);
        $this->assertSame(15, $dashboard['others']['n']);
        $this->assertEqualsWithDelta(3.07, (float) $dashboard['others']['score'], 0.0001);
        $this->assertCount(1, $dashboard['others']['dimensions']);
        $this->assertSame(40, $dashboard['respondents']);

        // A composição nunca vai para o painel.
        $encoded = json_encode($dashboard, JSON_UNESCAPED_UNICODE);
        foreach (['Costura', 'Diretoria', 'Escritório', 'Portaria', 'Limpeza'] as $small) {
            $this->assertStringNotContainsString($small, $encoded);
        }
    }

    public function test_others_rows_never_leak_into_the_survey_results_readers(): void
    {
        $campaign = $this->campaign($this->t12Sectors());

        // Só uma linha geral e uma por seção sem setor: o agregado de grupo vive em tabela própria.
        $this->assertSame(1, SurveyResult::query()->where('survey_id', $campaign->fx->survey->id)->whereNull('department_id')->whereNull('survey_template_section_id')->count());
        $this->assertSame(1, SurveyResult::query()->where('survey_id', $campaign->fx->survey->id)->whereNull('department_id')->whereNotNull('survey_template_section_id')->count());
        $this->assertSame(2, SurveyGroupResult::query()->where('survey_id', $campaign->fx->survey->id)->count());
    }

    public function test_recalculation_is_idempotent_for_the_others_group(): void
    {
        $campaign = $this->campaign($this->t12Sectors());
        app(SurveyResultCalculator::class)->recalculate($campaign->fx->survey->fresh());
        app(SurveyResultCalculator::class)->recalculate($campaign->fx->survey->fresh());

        $this->assertSame(2, SurveyGroupResult::query()->where('survey_id', $campaign->fx->survey->id)->count());
    }

    public function test_others_below_the_floor_is_suppressed_but_stays_in_the_overall_index(): void
    {
        // Só setores pequenos: 2 + 2 = 4 respostas < 5. A exibição é suprimida; o índice geral usa as 4.
        $campaign = $this->campaign(['Pequeno A' => [2, 5], 'Pequeno B' => [2, 1]]);
        $dashboard = $this->dashboard($campaign->fx->survey);

        $this->assertNull($dashboard['others']);
        $this->assertSame([], $dashboard['sectors']);
        $this->assertSame(4, $dashboard['overall']['n']);
        $this->assertEqualsWithDelta(3.0, (float) $dashboard['overall']['score'], 0.0001);
        // As respostas foram agregadas sobre o original e seguem gravadas para uso interno.
        $this->assertSame(4, (int) SurveyGroupResult::query()->where('survey_id', $campaign->fx->survey->id)->whereNull('survey_template_section_id')->value('respondent_count'));
        $this->assertStringNotContainsString('Pequeno', json_encode($dashboard, JSON_UNESCAPED_UNICODE));
    }

    public function test_cascade_absorbs_the_smallest_visible_sector_into_others(): void
    {
        // 19 visível; 4 oculto (< 5). Soma oculta 4 < 5: o menor visível (19) também sai e todos viram "Outros".
        $campaign = $this->campaign(['Grande' => [19, 3], 'Pequeno' => [4, 3]]);
        $dashboard = $this->dashboard($campaign->fx->survey);

        $this->assertSame([], $dashboard['sectors']);
        $this->assertSame(23, $dashboard['others']['n']);
    }

    public function test_others_also_works_with_the_production_method(): void
    {
        $campaign = $this->campaign($this->t12Sectors(), SurveyMethodVersion::CODE_PRODUCTION_V1);

        $others = SurveyGroupResult::query()
            ->where('survey_id', $campaign->fx->survey->id)
            ->whereNull('survey_template_section_id')
            ->firstOrFail();

        // production_v1: média dos escores por respondente (cada um tem uma pergunta): 46 / 15.
        $this->assertSame(15, (int) $others->respondent_count);
        $this->assertEqualsWithDelta(46 / 15, (float) $others->average_score, 0.001);
    }

    public function test_raising_the_group_minimum_recomputes_who_goes_to_others(): void
    {
        $campaign = $this->campaign($this->t12Sectors(), SurveyMethodVersion::CODE_TALENTS39_V1, ['min_group_size' => 7]);
        $dashboard = $this->dashboard($campaign->fx->survey);

        // Com mínimo 7, Manual (6) também sai: Produção fica sozinha e Outros soma 6 + 15 = 21.
        $this->assertSame(['Produção'], array_column($dashboard['sectors'], 'department_name'));
        $this->assertSame(21, $dashboard['others']['n']);
    }

    public function test_item_percentages_for_q7_and_q8_and_a_direct_item_from_the_appendix(): void
    {
        $campaign = $this->v11Campaign();
        $items = collect($this->dashboard($campaign->survey)['items'])->keyBy('id');

        // Q7 e Q8 são enunciados favoráveis (Invertida): favorável = Frequentemente + Sempre, base 40.
        $q7 = $items[$campaign->questionIds[7]];
        $this->assertSame(40, $q7['n']);
        $this->assertSame([1 => 0, 2 => 1, 3 => 2, 4 => 6, 5 => 31], $q7['counts']);
        $this->assertSame(92.5, $q7['favorable_pct']);
        $this->assertSame(2.5, $q7['unfavorable_pct']);
        $this->assertSame('favorable', $q7['statement']);

        $q8 = $items[$campaign->questionIds[8]];
        $this->assertSame(75.0, $q8['favorable_pct']);

        // Item 1 é desfavorável (Direta): favorável = Nunca + Raramente = 3 de 40; desfavorável = 22 de 40.
        $q1 = $items[$campaign->questionIds[1]];
        $this->assertSame('unfavorable', $q1['statement']);
        $this->assertSame(7.5, $q1['favorable_pct']);
        $this->assertSame(55.0, $q1['unfavorable_pct']);

        foreach ($items as $item) {
            $this->assertSame(40, $item['n']);
            $this->assertFalse($item['quality_alert']);
            $this->assertEqualsWithDelta(100.0, $item['favorable_pct'] + $item['unfavorable_pct'] + (($item['counts'][3] / 40) * 100), 0.15);
        }
    }

    public function test_participation_is_valid_respondents_over_eligible(): void
    {
        $campaign = $this->v11Campaign();

        $missing = $this->dashboard($campaign->survey)['eligible'];
        $this->assertNull($missing['participation_percent']);
        $this->assertNull($missing['participation_percent_label']);

        SurveyCampaignRegistration::query()->create(['survey_id' => $campaign->survey->id, 'company_id' => $campaign->company->id, 'eligible_count' => 48]);
        $card = $this->dashboard($campaign->survey)['eligible'];
        $this->assertSame(83.3, $card['participation_percent']);
        $this->assertSame('83,3%', $card['participation_percent_label']);
        $this->assertSame('40 de 48', $card['participation_label']);

        SurveyCampaignRegistration::query()->where('survey_id', $campaign->survey->id)->update(['eligible_count' => 40]);
        $this->assertSame('100,0%', $this->dashboard($campaign->survey)['eligible']['participation_percent_label']);

        SurveyCampaignRegistration::query()->where('survey_id', $campaign->survey->id)->update(['eligible_count' => 30]);
        $inconsistent = $this->dashboard($campaign->survey)['eligible'];
        $this->assertNull($inconsistent['participation_percent']);
        $this->assertNotNull($inconsistent['alert']);
    }

    public function test_t25_empty_campaign_shows_empty_state_without_zeroed_averages(): void
    {
        $campaign = $this->v11Campaign(withResponses: false);
        $dashboard = $this->dashboard($campaign->survey);

        $this->assertFalse($dashboard['has_results']);
        $this->assertNull($dashboard['overall']);
        $this->assertNull($dashboard['others']);
        $this->assertSame([], $dashboard['dimensions']);
        $this->assertSame([], $dashboard['sectors']);
        $this->assertSame(0, $dashboard['respondents']);
        $this->assertNull($dashboard['eligible']['participation_percent']);

        foreach ($dashboard['items'] as $item) {
            $this->assertSame(0, $item['n']);
            $this->assertNull($item['favorable_pct']);
            $this->assertNull($item['unfavorable_pct']);
        }

        $this->assertSame(0, SurveyResult::query()->where('survey_id', $campaign->survey->id)->count());
    }

    public function test_dashboard_export_includes_others_only_when_displayable(): void
    {
        $campaign = $this->campaign($this->t12Sectors());
        $user = User::factory()->companyAdmin($campaign->fx->company->id)->create();

        $json = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.export.json', $campaign->fx->survey))
            ->assertOk()
            ->json();

        $this->assertContains('outros_setores', array_column($json['rows'], 'recorte'));
        $this->assertContains(15, array_column($json['rows'], 'n'));
        $encoded = json_encode($json, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Costura', $encoded);
        $this->assertStringNotContainsString('Portaria', $encoded);
    }
}
