<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Survey;
use App\Models\SurveyGroupResult;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1PooledItemScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Etapa 5, fatia 2 (Resultados): participação por grupo, "Outros Setores" (D7/D8), cartões das 39 perguntas,
 * T13 (nada permite inferir setor pequeno) e soma/N guardados para auditoria em 4 casas (§15).
 */
class Nr1V11DashboardResultsTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    /**
     * Uma pergunta, valor 4 em todos; sector => nº de respondentes.
     *
     * @param  array<string, int>  $sectors
     */
    private function groupCampaign(array $sectors, bool $showComposition = false): object
    {
        $fx = $this->createSurveyFixture([
            'method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1),
            'show_others_composition' => $showComposition,
        ]);

        foreach ($sectors as $name => $size) {
            $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => $name]);
            for ($i = 0; $i < $size; $i++) {
                SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => (string) Str::uuid(),
                    'department_id' => $department->id,
                    'completed_at' => now()->startOfMonth(),
                ])->answers()->create(['survey_template_question_id' => $fx->question->id, 'value' => 4]);
            }
        }

        app(SurveyResultCalculator::class)->recalculate($fx->survey->fresh());

        return $fx;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Survey $survey): array
    {
        return app(PsychosocialDashboardBuilder::class)->build($survey->fresh());
    }

    public function test_item_cards_carry_the_five_band_distribution_and_the_favorable_split(): void
    {
        $campaign = $this->v11Campaign();
        $items = collect($this->build($campaign->survey)['items']);

        $this->assertCount(39, $items);
        $this->assertSame(range(1, 39), $items->pluck('position')->all());

        $q7 = $items->firstWhere('id', $campaign->questionIds[7]);
        $this->assertSame([1 => 0.0, 2 => 2.5, 3 => 5.0, 4 => 15.0, 5 => 77.5], $q7['distribution']);
        $this->assertSame(37, $q7['counts'][4] + $q7['counts'][5], 'Q7: 37/40.');
        $this->assertSame('92,5%', $q7['favorable_pct_label']);
        $this->assertSame('2,5%', $q7['unfavorable_pct_label']);

        $q8 = $items->firstWhere('id', $campaign->questionIds[8]);
        $this->assertSame(30, $q8['counts'][4] + $q8['counts'][5], 'Q8: 30/40.');
        $this->assertSame(75.0, $q8['favorable_pct']);
        $this->assertSame('75,0%', $q8['favorable_pct_label']);

        foreach ($items as $item) {
            $this->assertSame(40, $item['n']);
            $this->assertEqualsWithDelta(100.0, array_sum($item['distribution']), 0.2, 'Distribuição fecha em 100%.');
            $this->assertEqualsWithDelta(100.0, $item['favorable_pct'] + $item['unfavorable_pct'] + $item['distribution'][3], 0.2);
            $this->assertArrayHasKey('section_id', $item);
            $this->assertNotSame('', $item['section_title']);
        }
    }

    public function test_item_cards_of_an_empty_campaign_have_no_invented_percentages(): void
    {
        $campaign = $this->v11Campaign(withResponses: false);
        $dashboard = $this->build($campaign->survey);

        foreach ($dashboard['items'] as $item) {
            $this->assertSame(0, $item['n']);
            $this->assertNull($item['favorable_pct_label']);
            $this->assertNull($item['unfavorable_pct_label']);
            $this->assertSame([1 => null, 2 => null, 3 => null, 4 => null, 5 => null], $item['distribution']);
        }
        $this->assertSame(['items' => [], 'note' => null], $dashboard['groups']);
    }

    public function test_groups_show_sectors_and_others_with_n_and_percent_and_account_for_every_response(): void
    {
        $campaign = $this->groupCampaign(['Grande' => 12, 'S1' => 2, 'S2' => 2, 'S3' => 2]);
        $groups = $this->build($campaign->survey)['groups'];

        $this->assertCount(2, $groups['items']);
        [$sector, $others] = $groups['items'];
        $this->assertSame(['sector', 'Grande', 12, '66,7%'], [$sector['kind'], $sector['name'], $sector['n'], $sector['percent_label']]);
        $this->assertSame(['others', 'Outros Setores', 6, '33,3%'], [$others['kind'], $others['name'], $others['n'], $others['percent_label']]);
        $this->assertSame(18, array_sum(array_column($groups['items'], 'n')), 'Nenhuma resposta fica de fora dos grupos.');
        $this->assertSame(PsychosocialDashboardBuilder::OTHERS_NOTE, $groups['note']);
    }

    public function test_d8_composition_is_hidden_by_default(): void
    {
        $campaign = $this->groupCampaign(['Grande' => 12, 'Alfa' => 2, 'Beta' => 2, 'Gama' => 2]);
        $dashboard = $this->build($campaign->survey);

        $this->assertNull($dashboard['groups']['items'][1]['composition']);
        $json = json_encode($dashboard, JSON_UNESCAPED_UNICODE);
        foreach (['Alfa', 'Beta', 'Gama'] as $hiddenSector) {
            $this->assertStringNotContainsString($hiddenSector, $json);
        }
    }

    public function test_d8_composition_needs_the_setting_three_sectors_and_the_group_at_the_floor(): void
    {
        // Ligada + 3 setores + grupo no piso (6 ≥ 5): mostra os nomes, em ordem alfabética.
        $ok = $this->groupCampaign(['Grande' => 12, 'Gama' => 2, 'Alfa' => 2, 'Beta' => 2], showComposition: true);
        $this->assertSame(['Alfa', 'Beta', 'Gama'], $this->build($ok->survey)['groups']['items'][1]['composition']);

        // Ligada, mas só 2 setores no grupo: não mostra.
        $two = $this->groupCampaign(['Grande' => 12, 'Alfa' => 3, 'Beta' => 3], showComposition: true);
        $this->assertSame(6, $this->build($two->survey)['groups']['items'][1]['n']);
        $this->assertNull($this->build($two->survey)['groups']['items'][1]['composition']);

        // Ligada e 3 setores, mas o grupo fica abaixo do piso (3 < 5): nem o grupo nem os nomes saem.
        $low = $this->groupCampaign(['Alfa' => 1, 'Beta' => 1, 'Gama' => 1], showComposition: true);
        $dashboard = $this->build($low->survey);
        $this->assertSame([], $dashboard['groups']['items']);
        $this->assertNull($dashboard['others']);
        $this->assertSame(PsychosocialDashboardBuilder::SMALL_GROUPS_NOTE, $dashboard['groups']['note']);
        $json = json_encode($dashboard, JSON_UNESCAPED_UNICODE);
        foreach (['Alfa', 'Beta', 'Gama'] as $hiddenSector) {
            $this->assertStringNotContainsString($hiddenSector, $json);
        }

        // Desligada mesmo com tudo o mais satisfeito.
        $off = $this->groupCampaign(['Grande' => 12, 'Alfa' => 2, 'Beta' => 2, 'Gama' => 2], showComposition: false);
        $this->assertNull($this->build($off->survey)['groups']['items'][1]['composition']);
    }

    public function test_t13_small_sector_cannot_be_inferred_from_page_exports_or_the_cascade(): void
    {
        // Minúsculo (1) é oculto; a cascata também absorve o menor setor visível (Médio) para não sair por subtração.
        $campaign = $this->groupCampaign(['Grande' => 12, 'Médio' => 6, 'Minúsculo' => 1]);
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $page = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard', $campaign->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('dashboard.groups.items', 2)
                ->where('dashboard.groups.items.0.name', 'Grande')
                ->where('dashboard.groups.items.1.n', 7));
        $props = json_encode($page->viewData('page')['props'], JSON_UNESCAPED_UNICODE);

        $csv = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.export.csv', $campaign->survey))
            ->assertOk()->streamedContent();
        $json = $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard.export.json', $campaign->survey))
            ->assertOk()->getContent();

        foreach (['Minúsculo', 'Médio'] as $hiddenSector) {
            $this->assertStringNotContainsString($hiddenSector, $props, "{$hiddenSector} apareceu na página.");
            $this->assertStringNotContainsString($hiddenSector, $csv, "{$hiddenSector} apareceu no CSV.");
            $this->assertStringNotContainsString($hiddenSector, $json, "{$hiddenSector} apareceu no JSON.");
        }

        // Nenhum recorte por setor chega aos cartões: eles são da campanha inteira (N = total).
        foreach ($this->build($campaign->survey)['items'] as $item) {
            $this->assertSame(19, $item['n']);
        }
    }

    public function test_page_receives_groups_and_the_item_cards_data(): void
    {
        $campaign = $this->v11Campaign();
        $user = User::factory()->companyAdmin($campaign->company->id)->create();

        $this->actingAs($user)
            ->get(route('client.surveys.psychosocial-dashboard', $campaign->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard.groups.items')
                ->has('dashboard.items', 39)
                ->has('dashboard.items.0', fn (Assert $item) => $item
                    ->hasAll(['id', 'position', 'section_id', 'section_title', 'body', 'n', 'quality_alert', 'counts', 'statement', 'favorable_pct', 'unfavorable_pct', 'favorable_pct_label', 'unfavorable_pct_label', 'distribution'])));
    }

    public function test_v11_aggregates_keep_sum_and_n_so_four_decimals_can_be_audited(): void
    {
        $campaign = $this->v11Campaign();
        $overall = SurveyResult::query()
            ->where('survey_id', $campaign->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->sole();

        $this->assertSame(4052, (int) $overall->score_numerator);
        $this->assertSame(1560, (int) $overall->score_denominator);
        $this->assertEqualsWithDelta(2.5974, (int) $overall->score_numerator / (int) $overall->score_denominator, 0.00005);

        $rows = SurveyResult::query()->where('survey_id', $campaign->survey->id)->get();
        $this->assertGreaterThanOrEqual(8, $rows->count(), 'Geral + 7 dimensões.');
        foreach ($rows as $row) {
            $this->assertNotNull($row->score_numerator);
            $this->assertGreaterThan(0, (int) $row->score_denominator);
            $stored = Nr1PooledItemScore::roundHalfUpToHundredths((int) $row->score_numerator, (int) $row->score_denominator) / 100;
            $this->assertEqualsWithDelta($stored, (float) $row->getRawOriginal('average_score'), 0.0001, 'O índice gravado é a soma ÷ N arredondada a 2 casas.');
        }
    }

    public function test_v11_sector_dimension_and_others_aggregates_also_keep_sum_and_n(): void
    {
        $campaign = $this->groupCampaign(['Grande' => 12, 'Alfa' => 2, 'Beta' => 2, 'Gama' => 2]);

        $sectorRows = SurveyResult::query()->where('survey_id', $campaign->survey->id)->whereNotNull('department_id')->get();
        $this->assertNotEmpty($sectorRows);
        foreach ($sectorRows as $row) {
            $this->assertNotNull($row->score_numerator);
            $this->assertNotNull($row->score_denominator);
        }

        $others = SurveyGroupResult::query()->where('survey_id', $campaign->survey->id)->where('group_key', SurveyGroupResult::GROUP_OTHERS)->get();
        $this->assertNotEmpty($others);
        foreach ($others as $row) {
            $this->assertNotNull($row->score_numerator);
            $this->assertGreaterThan(0, (int) $row->score_denominator);
        }
    }
}
