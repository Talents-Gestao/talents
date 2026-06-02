<?php

namespace App\Console\Commands;

use App\Models\Survey;
use App\Services\SurveyProfileResponseSeeder;
use Illuminate\Console\Command;

class Nr1SeedProfileResponsesCommand extends Command
{
    protected $signature = 'nr1:seed-profile-responses
                            {survey : ID ou public_token (UUID) da pesquisa}
                            {--favorable=15 : Quantidade de respostas com perfil favorável (baixo risco)}
                            {--unfavorable=10 : Quantidade de respostas com perfil desfavorável (alto risco)}
                            {--replace : Remove todas as respostas existentes antes de inserir}
                            {--yes : Confirma sem perguntar}';

    protected $description = 'Insere respostas NR-1 com perfil favorável ou desfavorável (demonstração/testes)';

    public function handle(SurveyProfileResponseSeeder $seeder): int
    {
        $survey = $this->resolveSurvey($this->argument('survey'));

        if (! $survey) {
            $this->error('Pesquisa não encontrada (use ID numérico ou public_token UUID).');

            return self::FAILURE;
        }

        $favorable = max(0, (int) $this->option('favorable'));
        $unfavorable = max(0, (int) $this->option('unfavorable'));
        $replace = (bool) $this->option('replace');

        $survey->load('company');
        $completed = $survey->completedResponses()->count();

        $this->info("Pesquisa [{$survey->id}] {$survey->title}");
        $this->line('  Empresa: '.($survey->company?->name ?? '—'));
        $this->line("  Token público: {$survey->public_token}");
        $this->line("  Respondentes atuais: {$completed}");
        $this->line("  Inserir: {$favorable} favorável(is) + {$unfavorable} desfavorável(is) = ".($favorable + $unfavorable));

        if ($replace) {
            $this->warn('  Modo --replace: todas as respostas atuais serão apagadas.');
        } elseif ($completed > 0) {
            $this->line('  As novas respostas serão somadas às existentes (use --replace para substituir).');
        }

        if (! $this->option('yes') && ! $this->confirm('Continuar?')) {
            $this->line('Operação cancelada.');

            return self::SUCCESS;
        }

        try {
            $result = $seeder->seed($survey, $favorable, $unfavorable, $replace);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $survey->refresh();
        $this->newLine();
        $this->info('Respostas inseridas e resultados recalculados.');
        $this->line("  Favoráveis: {$result['favorable']}");
        $this->line("  Desfavoráveis: {$result['unfavorable']}");
        $this->line("  Linhas em survey_answers: {$result['total_answers']}");
        $this->line('  Total de respondentes: '.$survey->completedResponses()->count());
        $this->line('  Valide com: php artisan nr1:audit-scoring '.$survey->id);

        return self::SUCCESS;
    }

    private function resolveSurvey(string $key): ?Survey
    {
        if (ctype_digit($key)) {
            return Survey::query()->find((int) $key);
        }

        return Survey::query()->where('public_token', $key)->first();
    }
}
