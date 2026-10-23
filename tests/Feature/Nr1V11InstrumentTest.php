<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\SurveyTemplateLockedException;
use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyInstrumentVersion;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\SurveyTemplate;
use App\Models\SurveyTemplateQuestion;
use App\Models\SurveyTemplateSection;
use App\Models\User;
use App\Services\Nr1AiAnalyzer;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\SurveyResultCalculator;
use App\Services\SurveyTemplateVersioningService;
use App\Support\Nr1BandLabels;
use App\Support\Nr1InstrumentMatrix;
use App\Support\Nr1Scoring;
use Database\Seeders\Talents39InstrumentData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\Nr1V11Dataset;
use Tests\TestCase;

/**
 * Etapa 1: instrumento V11 (39 itens), bloqueio de edição destrutiva e rótulos de faixa V11.
 */
class Nr1V11InstrumentTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    /** Dimensão => [numerador, denominador exatos do Apêndice A, índice gravado com 2 casas meio para cima]. */
    private const DIMENSIONS = [
        'Demandas e Sobrecarga' => [793, 240, 3.30],
        'Autonomia e Organização' => [381, 200, 1.91],
        'Liderança e Gestão' => [615, 240, 2.56],
        'Segurança Psicológica' => [659, 240, 2.75],
        'Relacionamentos e Clima' => [588, 240, 2.45],
        'Esgotamento e Saúde Emocional' => [528, 200, 2.64],
        'Percepção Organizacional' => [488, 200, 2.44],
    ];

    /**
     * Campanha V11 completa: importa o instrumento e cria 40 respondentes a partir do Apêndice A.
     *
     * @return object{company: Company, template: SurveyTemplate, survey: Survey, questionIds: array<int, int>}
     */
    private function v11Campaign(bool $withResponses = true): object
    {
        $this->artisan('nr1:import-instrument-v11')->assertSuccessful();
        $template = SurveyTemplate::query()->where('title', Talents39InstrumentData::TEMPLATE_TITLE)->firstOrFail();

        $company = Company::query()->create([
            'name' => 'Empresa V11',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $this->subscribeCompanyToNr1($company);
        $company->surveyTemplates()->attach($template->id);

        $survey = Survey::query()->create([
            'company_id' => $company->id,
            'survey_template_id' => $template->id,
            'title' => 'Campanha V11',
            'public_token' => (string) Str::uuid(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
            'method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1),
        ]);

        $questionIds = [];
        foreach (Nr1InstrumentMatrix::forTemplate((int) $template->id) as $row) {
            $questionIds[$row['item_number']] = $row['question_id'];
        }

        if ($withResponses) {
            $this->seedAppendixResponses($survey, $questionIds);
            app(SurveyResultCalculator::class)->recalculate($survey->fresh());
        }

        return (object) compact('company', 'template', 'survey', 'questionIds');
    }

    /**
     * @param  array<int, int>  $questionIds  item => question_id
     */
    private function seedAppendixResponses(Survey $survey, array $questionIds): void
    {
        $values = [];
        foreach (Nr1V11Dataset::COUNTS as $item => $counts) {
            $list = [];
            foreach ($counts as $index => $count) {
                $list = array_merge($list, array_fill(0, $count, $index + 1));
            }
            $values[$item] = $list;
        }

        for ($respondent = 0; $respondent < Nr1V11Dataset::RESPONDENTS; $respondent++) {
            $response = SurveyResponse::query()->create([
                'survey_id' => $survey->id,
                'session_token' => (string) Str::uuid(),
                'completed_at' => now()->startOfMonth(),
            ]);

            SurveyAnswer::query()->insert(array_map(fn (int $item) => [
                'survey_response_id' => $response->id,
                'survey_template_question_id' => $questionIds[$item],
                'value' => $values[$item][$respondent],
                'created_at' => now(),
                'updated_at' => now(),
            ], array_keys($questionIds)));
        }
    }

    public function test_instrument_data_matches_the_appendix_structure(): void
    {
        $items = Talents39InstrumentData::items();

        $this->assertCount(39, $items);
        $this->assertSame(range(1, 39), array_column($items, 'id'));
        $this->assertCount(39, array_unique(array_column($items, 'body')));
        $this->assertCount(7, Talents39InstrumentData::dimensions());
        $this->assertSame(11, count(array_filter($items, fn (array $i) => ! $i['reverse_score'])));
        $this->assertSame(28, count(array_filter($items, fn (array $i) => $i['reverse_score'])));

        $perDimension = array_count_values(array_column($items, 'dimension'));
        $this->assertSame(6, $perDimension['Demandas e Sobrecarga']);
        $this->assertSame(5, $perDimension['Autonomia e Organização']);
        $this->assertSame(6, $perDimension['Liderança e Gestão']);
        $this->assertSame(6, $perDimension['Segurança Psicológica']);
        $this->assertSame(6, $perDimension['Relacionamentos e Clima']);
        $this->assertSame(5, $perDimension['Esgotamento e Saúde Emocional']);
        $this->assertSame(5, $perDimension['Percepção Organizacional']);

        // Direta = desfavorável: itens 1–6 (Demandas) e 30–34 (Esgotamento); o restante é Invertida.
        foreach ($items as $item) {
            $isDirect = $item['id'] <= 6 || ($item['id'] >= 30 && $item['id'] <= 34);
            $this->assertSame(! $isDirect, $item['reverse_score'], "Polaridade do item {$item['id']}");
        }

        $this->assertCount(39, Nr1V11Dataset::COUNTS);
        foreach (Nr1V11Dataset::COUNTS as $item => $counts) {
            $this->assertSame(Nr1V11Dataset::RESPONDENTS, array_sum($counts), "Item {$item}");
        }
    }

    public function test_import_creates_the_versioned_template_and_is_idempotent(): void
    {
        $this->artisan('nr1:import-instrument-v11')->assertSuccessful();
        $this->artisan('nr1:import-instrument-v11')->assertSuccessful();

        $template = SurveyTemplate::query()->where('title', Talents39InstrumentData::TEMPLATE_TITLE)->firstOrFail();
        $this->assertSame(1, SurveyTemplate::query()->where('title', Talents39InstrumentData::TEMPLATE_TITLE)->count());
        $this->assertSame(7, SurveyTemplateSection::query()->where('survey_template_id', $template->id)->count());

        $matrix = Nr1InstrumentMatrix::forTemplate((int) $template->id);
        $this->assertCount(39, $matrix);
        $this->assertSame(
            array_column(Talents39InstrumentData::items(), 'body'),
            array_column($matrix, 'body'),
        );
        $this->assertSame(
            array_column(Talents39InstrumentData::items(), 'reverse_score'),
            array_column($matrix, 'reverse_score'),
        );
        $this->assertSame(['frequency'], array_values(array_unique(array_column($matrix, 'response_scale'))));
        $this->assertSame(['1.00'], array_values(array_unique(array_column($matrix, 'weight'))));

        $versions = SurveyInstrumentVersion::query()->where('survey_template_id', $template->id)->get();
        $this->assertCount(1, $versions);
        $this->assertSame(Nr1InstrumentMatrix::CODE_TALENTS39, $versions[0]->instrument_code);
        $this->assertSame(Nr1InstrumentMatrix::hash($matrix), $versions[0]->item_matrix_sha256);
        $this->assertSame(Nr1InstrumentMatrix::AUDIT_PENDING, $versions[0]->polarity_audit_status);
        $this->assertSame(Nr1InstrumentMatrix::SOURCE_IMPORT, $versions[0]->source);
    }

    public function test_campaign_on_the_v11_template_reuses_the_imported_instrument_version(): void
    {
        $campaign = $this->v11Campaign(withResponses: false);

        $this->assertSame(
            1,
            SurveyInstrumentVersion::query()->where('survey_template_id', $campaign->template->id)->count(),
        );
        $this->assertSame(
            Nr1InstrumentMatrix::CODE_TALENTS39,
            $campaign->survey->instrumentVersion->instrument_code,
        );
        $this->assertFalse($campaign->survey->collectsDemographics());
    }

    public function test_acceptance_values_reproduce_from_the_appendix_dataset(): void
    {
        $campaign = $this->v11Campaign();

        $overall = SurveyResult::query()
            ->where('survey_id', $campaign->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->firstOrFail();

        // Índice geral = 4052 / 1560 = 2,5974 → exibido 2,60; faixa atenção.
        $this->assertSame(4052, (int) $overall->score_numerator);
        $this->assertSame(1560, (int) $overall->score_denominator);
        $this->assertEqualsWithDelta(2.5974, 4052 / 1560, 0.00005);
        $this->assertEqualsWithDelta(2.60, (float) $overall->getRawOriginal('average_score'), 0.0001);
        $this->assertSame('yellow', $overall->risk_level);
        $this->assertSame(40, (int) $overall->respondent_count);
        $this->assertSame('Atenção', Nr1BandLabels::label($overall->risk_level, SurveyMethodVersion::CODE_TALENTS39_V1));

        $rows = SurveyResult::query()
            ->where('survey_id', $campaign->survey->id)
            ->whereNotNull('survey_template_section_id')
            ->whereNull('department_id')
            ->with('section')
            ->get()
            ->keyBy(fn (SurveyResult $row) => $row->section->title);

        $this->assertCount(7, $rows);
        $expectedMeans = [
            'Demandas e Sobrecarga' => 3.3042,
            'Autonomia e Organização' => 1.9050,
            'Liderança e Gestão' => 2.5625,
            'Segurança Psicológica' => 2.7458,
            'Relacionamentos e Clima' => 2.4500,
            'Esgotamento e Saúde Emocional' => 2.6400,
            'Percepção Organizacional' => 2.4400,
        ];

        foreach (self::DIMENSIONS as $title => [$numerator, $denominator, $stored]) {
            $row = $rows[$title];
            $this->assertSame($numerator, (int) $row->score_numerator, $title);
            $this->assertSame($denominator, (int) $row->score_denominator, $title);
            $this->assertEqualsWithDelta($expectedMeans[$title], $numerator / $denominator, 0.00005, $title);
            $this->assertEqualsWithDelta($stored, (float) $row->getRawOriginal('average_score'), 0.0001, $title);
        }
    }

    public function test_attention_dimensions_are_those_from_2_34_in_descending_order(): void
    {
        $campaign = $this->v11Campaign();
        $dashboard = app(PsychosocialDashboardBuilder::class)->build($campaign->survey->fresh());

        $attention = collect($dashboard['dimensions'])
            ->filter(fn (array $dimension) => Nr1BandLabels::isAttention($dimension['score']))
            ->sortByDesc(fn (array $dimension) => (float) $dimension['score'])
            ->values();

        // Seis de sete; só Autonomia e Organização (1,905) fica fora, na faixa menor sinalização.
        $this->assertSame([
            'Demandas e Sobrecarga',
            'Segurança Psicológica',
            'Esgotamento e Saúde Emocional',
            'Liderança e Gestão',
            'Relacionamentos e Clima',
            'Percepção Organizacional',
        ], $attention->pluck('title')->all());

        $autonomy = collect($dashboard['dimensions'])->firstWhere('title', 'Autonomia e Organização');
        $this->assertFalse(Nr1BandLabels::isAttention($autonomy['score']));
        $this->assertSame('Menor sinalização', $autonomy['band_label']);
        $this->assertTrue(Nr1BandLabels::isAttention('2.34'));
        $this->assertFalse(Nr1BandLabels::isAttention('2.33'));
        $this->assertTrue(Nr1BandLabels::isAttention('3.67'));
    }

    public function test_favorable_counts_for_q7_and_q8_come_from_the_appendix(): void
    {
        // Q7 e Q8 são Invertida: favorável = Frequentemente + Sempre, sobre a base de 40.
        $q7 = Nr1V11Dataset::COUNTS[7];
        $q8 = Nr1V11Dataset::COUNTS[8];

        $this->assertSame(37, $q7[3] + $q7[4]);
        $this->assertSame(30, $q8[3] + $q8[4]);
        $this->assertSame(92.5, ($q7[3] + $q7[4]) / 40 * 100);
        $this->assertSame(75.0, ($q8[3] + $q8[4]) / 40 * 100);
    }

    public function test_dashboard_for_a_v11_campaign_uses_perception_labels(): void
    {
        $campaign = $this->v11Campaign();
        $dashboard = app(PsychosocialDashboardBuilder::class)->build($campaign->survey->fresh());

        $this->assertEqualsWithDelta(2.60, (float) $dashboard['overall']['score'], 0.0001);
        $this->assertSame('Atenção', $dashboard['overall']['band_label']);
        $this->assertSame(
            '1,00–2,33 Menor sinalização · 2,34–3,66 Atenção · 3,67–5,00 Maior sinalização',
            $dashboard['method']['legend'],
        );
        $this->assertSame(PsychosocialDashboardBuilder::POLARITY_WARNING, $dashboard['polarity_warning']);
        // O valor gravado continua green|yellow|red.
        $this->assertSame('yellow', $dashboard['overall']['risk_level']);
    }

    public function test_polarity_acceptance_clears_the_warning(): void
    {
        $campaign = $this->v11Campaign();
        $version = $campaign->survey->fresh()->instrumentVersion;
        $user = User::factory()->superAdmin()->create(['is_owner' => true]);

        $this->artisan('nr1:accept-polarity', ['instrumentVersion' => $version->id])->assertFailed();
        $this->artisan('nr1:accept-polarity', ['instrumentVersion' => $version->id, '--user' => $user->id, '--reference' => 'Parecer Suzane 2026'])
            ->assertSuccessful();

        $version->refresh();
        $this->assertSame(Nr1InstrumentMatrix::AUDIT_ACCEPTED, $version->polarity_audit_status);
        $this->assertSame($user->id, (int) $version->polarity_audit_accepted_by);
        $this->assertNotNull($version->polarity_audit_accepted_at);
        $this->assertNull(app(PsychosocialDashboardBuilder::class)->build($campaign->survey->fresh())['polarity_warning']);
    }

    public function test_labels_change_only_for_v11_and_never_the_stored_level(): void
    {
        $this->assertSame('Leve', Nr1Scoring::riskLabel('green'));
        $this->assertSame('Moderado', Nr1Scoring::riskLabel('yellow', SurveyMethodVersion::CODE_PRODUCTION_V1));
        $this->assertSame('Grave', Nr1Scoring::riskLabel('red'));
        $this->assertSame('Menor sinalização', Nr1Scoring::riskLabel('green', SurveyMethodVersion::CODE_TALENTS39_V1));
        $this->assertSame('Atenção', Nr1Scoring::riskLabel('yellow', SurveyMethodVersion::CODE_TALENTS39_V1));
        $this->assertSame('Maior sinalização', Nr1Scoring::riskLabel('red', SurveyMethodVersion::CODE_TALENTS39_V1));
        $this->assertSame('—', Nr1Scoring::riskLabel(null, SurveyMethodVersion::CODE_TALENTS39_V1));
    }

    public function test_ai_payload_uses_the_labels_of_the_campaign_method(): void
    {
        $v11 = $this->v11Campaign();
        $payload = app(Nr1AiAnalyzer::class)->buildAggregatedPayload($v11->survey->fresh());

        $this->assertSame('Atenção', $payload['overall']['risk_level']);
        $this->assertSame(['Menor sinalização', 'Atenção', 'Maior sinalização'], array_keys($payload['risk_level_names']));
        $this->assertStringContainsString('não classificação de risco ocupacional', $payload['score_interpretation']);

        $legacy = $this->createSurveyFixture();
        $legacyPayload = app(Nr1AiAnalyzer::class)->buildAggregatedPayload($legacy->survey->fresh());
        $this->assertSame(['Leve', 'Moderado', 'Grave'], array_keys($legacyPayload['risk_level_names']));
    }

    public function test_started_campaign_with_responses_locks_destructive_edits(): void
    {
        $fx = $this->createSurveyFixture();
        SurveyResponse::query()->create([
            'survey_id' => $fx->survey->id,
            'session_token' => 'tok',
            'completed_at' => now(),
        ])->answers()->create(['survey_template_question_id' => $fx->question->id, 'value' => 3]);

        foreach ([
            fn () => $fx->question->update(['body' => 'Outro texto?']),
            fn () => $fx->question->update(['reverse_score' => true]),
            fn () => $fx->question->update(['response_scale' => 'agreement']),
            fn () => $fx->question->update(['sort_order' => 9]),
            fn () => $fx->question->delete(),
            fn () => SurveyTemplateQuestion::query()->create(['survey_template_section_id' => $fx->section->id, 'body' => 'Nova?', 'reverse_score' => false, 'sort_order' => 1]),
            fn () => $fx->section->update(['title' => 'Outro nome']),
            fn () => SurveyTemplateSection::query()->create(['survey_template_id' => $fx->template->id, 'title' => 'Nova seção', 'sort_order' => 5]),
        ] as $index => $attempt) {
            try {
                $attempt();
                $this->fail("A edição destrutiva #{$index} não foi bloqueada.");
            } catch (SurveyTemplateLockedException) {
                $this->addToAssertionCount(1);
            }
        }

        $section = $fx->section->fresh();
        $section->update(['description' => 'Texto livre da seção']);
        $this->assertSame('Texto livre da seção', $section->fresh()->description);
        $this->assertSame('Pergunta teste?', $fx->question->fresh()->body);
        $this->assertTrue(app(SurveyTemplateVersioningService::class)->shouldFork($fx->template));
    }

    public function test_template_without_started_campaign_remains_editable(): void
    {
        $template = SurveyTemplate::query()->create(['title' => 'Livre', 'is_active' => true]);
        $section = SurveyTemplateSection::query()->create(['survey_template_id' => $template->id, 'title' => 'S', 'sort_order' => 0]);
        $question = SurveyTemplateQuestion::query()->create(['survey_template_section_id' => $section->id, 'body' => 'P?', 'reverse_score' => false, 'sort_order' => 0]);

        $question->update(['body' => 'P2?', 'reverse_score' => true]);
        $section->update(['title' => 'S2']);

        $this->assertSame('P2?', $question->fresh()->body);
        $this->assertFalse(app(SurveyTemplateVersioningService::class)->shouldFork($template));
    }

    public function test_v11_template_locks_when_the_campaign_opens_even_without_responses(): void
    {
        $campaign = $this->v11Campaign(withResponses: false);
        $question = SurveyTemplateQuestion::query()->findOrFail($campaign->questionIds[7]);

        try {
            $question->update(['reverse_score' => false]);
            $this->fail('A polaridade do V11 não pode mudar com a campanha aberta.');
        } catch (SurveyTemplateLockedException) {
            $this->addToAssertionCount(1);
        }

        $campaign->survey->update(['status' => 'draft']);
        $question->update(['reverse_score' => true]);
        $this->assertTrue($question->fresh()->reverse_score);
    }

    public function test_fork_creates_a_new_version_and_keeps_the_original_intact(): void
    {
        $campaign = $this->v11Campaign();
        $service = app(SurveyTemplateVersioningService::class);
        $this->assertTrue($service->shouldFork($campaign->template));

        $data = [
            'title' => 'Talents NR-1 V11 (39 itens) rev. 2',
            'description' => null,
            'sections' => [[
                'title' => 'Demandas e Sobrecarga',
                'questions' => [['body' => 'Texto revisado.', 'reverse_score' => false, 'response_scale' => 'frequency']],
            ]],
        ];
        $fork = $service->createFork($campaign->template, $data);

        $this->assertSame($campaign->template->id, (int) $fork->forked_from_id);
        $this->assertSame(39, SurveyTemplateQuestion::query()
            ->whereIn('survey_template_section_id', $campaign->template->sections()->pluck('id'))
            ->count());
        $this->assertSame('Minha rotina de trabalho exige um ritmo excessivo de produtividade.', SurveyTemplateQuestion::query()->findOrFail($campaign->questionIds[1])->body);
    }
}
