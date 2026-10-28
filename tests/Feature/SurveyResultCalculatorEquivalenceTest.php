<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Survey;
use App\Models\SurveyGroupResult;
use App\Models\SurveyMethodVersion;
use App\Models\SurveyResult;
use App\Services\SurveyResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\Support\ReferenceSurveyResultCalculator;
use Tests\TestCase;

/**
 * O cálculo otimizado (consulta só com valores, nota por resposta calculada uma vez) tem de gravar EXATAMENTE as mesmas
 * linhas que o cálculo anterior (cópia congelada em Tests\Support): mesmos números, ordem de gravação, grupo mínimo,
 * cascata e «Outros Setores». Compara linha a linha, nos dois métodos (talents39_v1 e produção).
 */
class SurveyResultCalculatorEquivalenceTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    /** @return array{results: list<array<string, mixed>>, groups: list<array<string, mixed>>} linhas na ordem de gravação, sem id e datas */
    private function snapshot(Survey $survey): array
    {
        $strip = fn (array $row) => array_diff_key($row, array_flip(['id', 'created_at', 'updated_at']));

        return [
            'results' => SurveyResult::query()->where('survey_id', $survey->id)->orderBy('id')->get()->map(fn ($r) => $strip($r->getAttributes()))->all(),
            'groups' => SurveyGroupResult::query()->where('survey_id', $survey->id)->orderBy('id')->get()->map(fn ($r) => $strip($r->getAttributes()))->all(),
        ];
    }

    /** Roda o cálculo anterior e o novo sobre os mesmos dados e exige o mesmo resultado, linha a linha e na mesma ordem. */
    private function assertSameResults(Survey $survey): array
    {
        (new ReferenceSurveyResultCalculator)->recalculate($survey->fresh());
        $before = $this->snapshot($survey);

        app(SurveyResultCalculator::class)->recalculate($survey->fresh());
        $after = $this->snapshot($survey);

        $this->assertNotEmpty($before['results']);
        $this->assertSame(count($before['results']), count($after['results']));
        $this->assertSame(count($before['groups']), count($after['groups']));
        $this->assertEquals($before, $after);
        $this->assertSame(json_encode($before), json_encode($after), 'mesmos valores, tipos e ordem de gravação');

        return $after;
    }

    /**
     * Respondentes sintéticos: setores desiguais (grandes, pequenos e minúsculos para acionar cascata e «Outros»),
     * alguns sem setor, respostas incompletas, uma resposta sem nenhuma pergunta e um pedaço de respostas parciais.
     *
     * @param  array<int, int>  $questionIds
     * @param  list<int>  $sizes
     */
    private function synthetic(Survey $survey, array $questionIds, array $sizes, int $noDepartment, int $seed): void
    {
        mt_srand($seed);
        $departments = [];
        foreach ($sizes as $i => $size) {
            $departments[] = [Department::query()->create(['company_id' => $survey->company_id, 'name' => 'Setor '.($i + 1)])->id, $size];
        }
        $departments[] = [null, $noDepartment];

        $now = now();
        $questionList = array_values($questionIds);
        $responseRows = [];
        foreach ($departments as [$departmentId, $size]) {
            for ($i = 0; $i < $size; $i++) {
                $responseRows[] = ['survey_id' => $survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $departmentId, 'completed_at' => $now->copy()->startOfMonth(), 'created_at' => $now, 'updated_at' => $now];
            }
        }
        // Incompleta (fora do cálculo) e concluída sem nenhuma resposta.
        $responseRows[] = ['survey_id' => $survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $departments[0][0], 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now];
        $responseRows[] = ['survey_id' => $survey->id, 'session_token' => (string) Str::uuid(), 'department_id' => $departments[1][0], 'completed_at' => $now->copy()->startOfMonth(), 'created_at' => $now, 'updated_at' => $now];
        foreach (array_chunk($responseRows, 500) as $chunk) {
            DB::table('survey_responses')->insert($chunk);
        }

        $ids = DB::table('survey_responses')->where('survey_id', $survey->id)->orderBy('id')->pluck('id')->all();
        $answers = [];
        foreach ($ids as $index => $responseId) {
            if ($index >= count($ids) - 2) {
                // As duas últimas: a incompleta tem respostas (precisam ser ignoradas); a concluída sem respostas não tem nenhuma.
                if ($index === count($ids) - 2) {
                    foreach ($questionList as $qid) {
                        $answers[] = ['survey_response_id' => $responseId, 'survey_template_question_id' => $qid, 'value' => 5, 'created_at' => $now, 'updated_at' => $now];
                    }
                }

                continue;
            }
            foreach ($questionList as $qid) {
                if (mt_rand(1, 12) === 1) {
                    continue; // resposta em branco (parcial)
                }
                $answers[] = ['survey_response_id' => $responseId, 'survey_template_question_id' => $qid, 'value' => mt_rand(1, 5), 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($answers, 2000) as $chunk) {
            DB::table('survey_answers')->insert($chunk);
        }
    }

    public function test_the_appendix_a_dataset_gets_the_same_rows_and_order(): void
    {
        $c = $this->v11Campaign(true);
        $result = $this->assertSameResults($c->survey);

        $this->assertNotEmpty($result['results']);
    }

    public function test_three_thousand_respondents_with_small_groups_cascade_and_others_match_the_previous_calculation(): void
    {
        $c = $this->v11Campaign(false);
        // 3.000 concluídas: setores grandes + pequenos (6, 5, 4, 3, 2, 1) que a política de grupo reúne em «Outros Setores».
        $this->synthetic($c->survey, $c->questionIds, [1200, 900, 700, 120, 40, 6, 5, 4, 3, 2, 1], 18, 20261028);

        $result = $this->assertSameResults($c->survey);

        // O cenário exerce de fato as regras de privacidade: há setores no resultado e o grupo «Outros» foi gravado.
        $this->assertNotEmpty(array_filter($result['results'], fn ($r) => $r['department_id'] !== null));
        $this->assertNotEmpty($result['groups'], 'Outros Setores deveria existir');
        $this->assertSame(SurveyGroupResult::GROUP_OTHERS, $result['groups'][0]['group_key']);
        // Respondentes contados: 3.000 concluídas com respostas (a sem respostas e a incompleta ficam fora).
        $overall = collect($result['results'])->first(fn ($r) => $r['department_id'] === null && $r['survey_template_section_id'] === null);
        $this->assertGreaterThan(2990, $overall['respondent_count']);
    }

    public function test_the_production_formula_with_weights_and_reversed_items_matches_too(): void
    {
        $c = $this->v11Campaign(false);
        $c->survey->forceFill(['method_version_id' => SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_PRODUCTION_V1)])->save();
        // O instrumento está travado para edição pelo modelo; o ajuste vai direto na tabela (só neste teste).
        foreach (array_values($c->questionIds) as $i => $questionId) {
            DB::table('survey_template_questions')->where('id', $questionId)->update(['weight' => [1.0, 0.5, 2.0, 1.5][$i % 4], 'reverse_score' => $i % 5 === 0]);
        }
        $this->synthetic($c->survey->fresh(), $c->questionIds, [300, 200, 90, 7, 3, 2], 5, 424242);

        $result = $this->assertSameResults($c->survey);

        $this->assertNotEmpty($result['groups']);
        $this->assertNull($result['results'][0]['score_numerator'] ?? null, 'a fórmula de produção não grava numerador');
    }

    public function test_a_campaign_without_answers_still_clears_and_stores_nothing(): void
    {
        $c = $this->v11Campaign(true);
        DB::table('survey_answers')->delete();
        DB::table('survey_responses')->where('survey_id', $c->survey->id)->delete();

        app(SurveyResultCalculator::class)->recalculate($c->survey->fresh());

        $this->assertSame(0, SurveyResult::query()->where('survey_id', $c->survey->id)->count());
        $this->assertSame(0, SurveyGroupResult::query()->where('survey_id', $c->survey->id)->count());
    }
}
