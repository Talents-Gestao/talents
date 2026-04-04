<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Services\SurveyResultCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Gera respostas de teste (preenchimentos avulsos) para a campanha "teste"
 * do cliente ESCRITORIO CONTABIL PASQUALINO (ou nome contendo PASQUALINO).
 */
class PasqualinoTestCampaignResponsesSeeder extends Seeder
{
    private const TARGET_COUNT = 50;

    public function run(): void
    {
        $company = Company::query()
            ->where('name', 'ilike', '%PASQUALINO%')
            ->firstOrFail();

        $survey = Survey::query()
            ->where('company_id', $company->id)
            ->where('title', 'ilike', 'teste')
            ->with(['template.sections.questions', 'company.departments'])
            ->firstOrFail();

        $questionIds = $survey->template->sections
            ->flatMap(fn ($s) => $s->questions)
            ->pluck('id')
            ->values()
            ->all();

        if ($questionIds === []) {
            $this->command?->error('Nenhuma pergunta no template desta campanha.');

            return;
        }

        $ageRanges = ['18-24', '25-34', '35-44', '45-54', '55+'];
        $tenureRanges = ['0-1', '1-3', '3-5', '5+'];
        $departments = $survey->company->departments;

        for ($i = 0; $i < self::TARGET_COUNT; $i++) {
            $answers = $this->answersForPersona($questionIds, $i);

            $deptId = null;
            if ($departments->isNotEmpty() && ($i % 3) !== 0) {
                $deptId = $departments[$i % $departments->count()]->id;
            }

            $response = SurveyResponse::create([
                'survey_id' => $survey->id,
                'session_token' => Str::random(40),
                'department_id' => $deptId,
                'age_range' => $ageRanges[$i % count($ageRanges)],
                'tenure_range' => $tenureRanges[$i % count($tenureRanges)],
                'completed_at' => now()->subMinutes(self::TARGET_COUNT - $i),
            ]);

            foreach ($answers as $qid => $value) {
                SurveyAnswer::create([
                    'survey_response_id' => $response->id,
                    'survey_template_question_id' => $qid,
                    'value' => $value,
                ]);
            }
        }

        app(SurveyResultCalculator::class)->recalculate($survey->fresh());

        $this->command?->info(sprintf(
            'Inseridas %d respostas completas na campanha "%s" (empresa: %s). Resultados recalculados.',
            self::TARGET_COUNT,
            $survey->title,
            $company->name
        ));
    }

    /**
     * Cada índice 0..49 usa um perfil ligeiramente diferente (tendência + ruído)
     * para variar médias e distribuições sem ser tudo aleatório puro.
     *
     * @param  array<int, int>  $questionIds
     * @return array<int, int> question_id => 1..5
     */
    private function answersForPersona(array $questionIds, int $personaIndex): array
    {
        $trend = ($personaIndex % 11) - 5;

        $out = [];
        foreach ($questionIds as $offset => $qid) {
            $base = random_int(1, 5);
            $wobble = (($personaIndex + $offset * 3) % 7) - 3;
            $value = $base + (int) round($trend / 6) + $wobble;
            $value = max(1, min(5, $value));
            $out[$qid] = $value;
        }

        return $out;
    }
}
