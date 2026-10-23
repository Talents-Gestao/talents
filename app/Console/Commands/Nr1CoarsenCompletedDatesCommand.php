<?php

namespace App\Console\Commands;

use App\Models\SurveyResponse;
use Illuminate\Console\Command;

/**
 * Reduz a data de conclusão de respostas antigas ao primeiro dia do mês. É IRREVERSÍVEL (a hora e o
 * dia exatos são perdidos), por isso nunca roda sozinho: exige --force e mostra o impacto antes.
 */
class Nr1CoarsenCompletedDatesCommand extends Command
{
    protected $signature = 'nr1:coarsen-completed-dates {--force : Confirma a perda irreversível do dia e da hora exatos}';

    protected $description = 'Reduz completed_at das respostas antigas ao mês (irreversível; exige --force).';

    public function handle(): int
    {
        $pending = SurveyResponse::query()
            ->whereNotNull('completed_at')
            ->get(['id', 'completed_at'])
            ->filter(fn (SurveyResponse $r) => ! $r->completed_at->equalTo($r->completed_at->copy()->startOfMonth()));

        $this->info("Respostas com data/hora exata: {$pending->count()}.");

        if ($pending->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('Nada foi alterado. Rode com --force depois de confirmar a decisão e de ter backup.');

            return self::SUCCESS;
        }

        $pending->each(fn (SurveyResponse $r) => SurveyResponse::query()
            ->whereKey($r->id)
            ->update(['completed_at' => $r->completed_at->copy()->startOfMonth()]));

        $this->info('Datas reduzidas ao mês.');

        return self::SUCCESS;
    }
}
