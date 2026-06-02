<?php

namespace App\Console\Commands;

use App\Models\Survey;
use App\Services\SurveyResultCalculator;
use Illuminate\Console\Command;

class Nr1RecalculateCommand extends Command
{
    protected $signature = 'nr1:recalculate {survey? : ID da pesquisa (omitir para recalcular todas com respostas)}';

    protected $description = 'Recalcula survey_results com a metodologia COPSOQ (índice de risco 0–100)';

    public function handle(SurveyResultCalculator $calculator): int
    {
        $surveyId = $this->argument('survey');

        $query = Survey::query()->orderBy('id');

        if ($surveyId !== null) {
            $query->whereKey($surveyId);
        }

        $surveys = $query->get();

        if ($surveys->isEmpty()) {
            $this->error('Nenhuma pesquisa encontrada.');

            return self::FAILURE;
        }

        $processed = 0;

        foreach ($surveys as $survey) {
            $completed = $survey->completedResponses()->count();
            if ($completed === 0) {
                $this->line("  [{$survey->id}] {$survey->title} — ignorada (sem respostas completas)");

                continue;
            }

            $calculator->recalculate($survey);
            $processed++;
            $this->info("  [{$survey->id}] {$survey->title} — recalculada ({$completed} respondente(s))");
        }

        $this->newLine();
        $this->info("Concluído: {$processed} pesquisa(s) recalculada(s).");

        return self::SUCCESS;
    }
}
