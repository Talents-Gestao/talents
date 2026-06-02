<?php

namespace App\Console\Commands;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResult;
use App\Models\SurveyTemplateQuestion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Nr1AuditScoringCommand extends Command
{
    protected $signature = 'nr1:audit-scoring {survey : ID da pesquisa}';

    protected $description = 'Audita pontuação NR-1: dimensões, reverse_score e distribuição de respostas';

    /** @var list<string> */
    private array $protectiveKeywords = [
        'apoio', 'respeito', 'autonomia', 'influência', 'influencia', 'aprender',
        'reconhecido', 'reconhecimento', 'transparência', 'transparencia', 'seguro',
        'segurança', 'seguranca', 'claras', 'promoções', 'promocoes', 'decisões', 'decisoes',
    ];

    /** @var list<string> */
    private array $riskKeywords = [
        'sobrecarga', 'rápido', 'rapido', 'pressão', 'pressao', 'excessiva', 'prazos',
        'conflito', 'assédio', 'assedio', 'violência', 'violencia', 'esgotado', 'estresse',
        'inseguro', 'humilhação', 'humilhacao',
    ];

    public function handle(): int
    {
        $survey = Survey::query()
            ->with(['template.sections.questions', 'company'])
            ->find($this->argument('survey'));

        if (! $survey) {
            $this->error('Pesquisa não encontrada.');

            return self::FAILURE;
        }

        $this->info("Pesquisa [{$survey->id}] {$survey->title}");
        $this->line("  Empresa: {$survey->company?->name}");
        $this->line('  Template: '.($survey->template?->title ?? '—').' (#'.$survey->survey_template_id.')');

        if ($survey->answers_reconstructed_at) {
            $this->warn('  ATENÇÃO: respostas foram reconstruídas em '.$survey->answers_reconstructed_at);
        }

        $answerCount = SurveyAnswer::query()
            ->whereIn('survey_response_id', $survey->completedResponses()->pluck('id'))
            ->count();

        $this->line("  Respostas individuais: {$answerCount}");
        $this->newLine();

        $this->info('=== RESULTADOS CALCULADOS (survey_results) ===');
        $results = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->orderBy('survey_template_section_id')
            ->orderBy('department_id')
            ->get();

        if ($results->isEmpty()) {
            $this->warn('  Nenhum resultado. Execute: php artisan nr1:recalculate {survey} ou recalcule no portal.');
        } else {
            foreach ($results as $r) {
                if ($r->survey_template_section_id === null && $r->department_id === null) {
                    $this->line(sprintf(
                        '  GERAL | score=%.1f | risco=%s | n=%d',
                        $r->average_score,
                        $r->risk_level,
                        $r->respondent_count
                    ));
                } elseif ($r->department_id === null) {
                    $title = $r->meta['section_title'] ?? 'Dimensão';
                    $this->line(sprintf(
                        '  %s | score=%.1f | risco=%s | n=%d',
                        $title,
                        $r->average_score,
                        $r->risk_level,
                        $r->respondent_count
                    ));
                }
            }
        }

        $this->newLine();
        $this->info('=== FAIXAS DO SISTEMA (COPSOQ / tercis na escala Likert 1–5) ===');
        $greenMax = config('nr1.risk_thresholds.green_max', 2.33);
        $yellowMax = config('nr1.risk_thresholds.yellow_max', 3.66);
        $this->line("  Verde  1,00–{$greenMax}  (situação favorável, sem risco aparente)");
        $this->line('  Amarelo '.number_format($greenMax + 0.01, 2, ',', '')."–{$yellowMax} (risco intermediário, monitorar)");
        $this->line('  Vermelho '.number_format($yellowMax + 0.01, 2, ',', '').'–5,00 (risco elevado, ação imediata)');
        $this->line('  Média ponderada das respostas Likert 1–5 (maior = maior risco psicossocial)');

        $this->newLine();
        $this->info('=== AUDITORIA reverse_score POR PERGUNTA ===');

        $issues = 0;
        foreach ($survey->template?->sections ?? [] as $section) {
            $this->line('');
            $this->line("  [{$section->title}]");

            foreach ($section->questions as $question) {
                $suggestion = $this->suggestReverseScore($question);
                $flag = $suggestion !== null && $suggestion !== (bool) $question->reverse_score;

                $avgLikert = $this->averageLikertForQuestion($survey->id, $question->id);
                $avgStr = $avgLikert !== null ? sprintf(' | média Likert=%.2f', $avgLikert) : '';

                $line = sprintf(
                    '    %s invert=%s peso=%s%s',
                    Str::limit($question->body, 55),
                    $question->reverse_score ? 'sim' : 'não',
                    $question->weight,
                    $avgStr
                );

                if ($flag) {
                    $issues++;
                    $line .= ' | ⚠ sugerido invert='.($suggestion ? 'sim' : 'não');
                    $this->warn($line);
                } else {
                    $this->line($line);
                }

                if ($avgLikert !== null) {
                    $dist = $this->distributionForQuestion($survey->id, $question->id);
                    $this->line('      dist: '.$this->formatDistribution($dist));
                }
            }
        }

        $this->newLine();
        if ($issues > 0) {
            $this->warn("  {$issues} pergunta(s) com possível reverse_score inconsistente (revisar no admin).");
        } else {
            $this->info('  Nenhuma inconsistência óbvia de reverse_score detectada por palavras-chave.');
        }

        $this->newLine();
        $this->line('Referência: COPSOQ usa tercis em 2,33 e 3,66 na escala Likert 1–5 (NR-1).');

        return self::SUCCESS;
    }

    private function suggestReverseScore(SurveyTemplateQuestion $question): ?bool
    {
        $body = Str::lower($question->body);

        foreach ($this->riskKeywords as $word) {
            if (str_contains($body, $word)) {
                return false;
            }
        }

        foreach ($this->protectiveKeywords as $word) {
            if (str_contains($body, $word)) {
                return true;
            }
        }

        return null;
    }

    private function averageLikertForQuestion(int $surveyId, int $questionId): ?float
    {
        $avg = SurveyAnswer::query()
            ->join('survey_responses', 'survey_responses.id', '=', 'survey_answers.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereNotNull('survey_responses.completed_at')
            ->where('survey_answers.survey_template_question_id', $questionId)
            ->avg('survey_answers.value');

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    /**
     * @return array<int, int>
     */
    private function distributionForQuestion(int $surveyId, int $questionId): array
    {
        $rows = SurveyAnswer::query()
            ->join('survey_responses', 'survey_responses.id', '=', 'survey_answers.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereNotNull('survey_responses.completed_at')
            ->where('survey_answers.survey_template_question_id', $questionId)
            ->selectRaw('survey_answers.value, COUNT(*) as total')
            ->groupBy('survey_answers.value')
            ->pluck('total', 'value');

        $dist = [];
        for ($i = 1; $i <= 5; $i++) {
            $dist[$i] = (int) ($rows[$i] ?? 0);
        }

        return $dist;
    }

    /**
     * @param  array<int, int>  $dist
     */
    private function formatDistribution(array $dist): string
    {
        $total = array_sum($dist);
        if ($total === 0) {
            return 'sem dados';
        }

        $parts = [];
        for ($i = 1; $i <= 5; $i++) {
            $pct = round(($dist[$i] / $total) * 100);
            $parts[] = "{$i}:{$pct}%";
        }

        return implode(' ', $parts);
    }
}
