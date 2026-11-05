<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateActionMeasuresJob;
use App\Models\Survey;
use App\Services\ActionMeasuresGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateActionMeasuresCommand extends Command
{
    protected $signature = 'nr1:generate-action-measures';

    protected $description = 'Enfileira uma única geração de medidas por IA para pesquisas já encerradas.';

    public function handle(): int
    {
        $queued = 0;

        Survey::query()
            ->whereNull('action_measures_generated_at')
            ->where(function ($query): void {
                $query->where('status', 'closed')
                    ->orWhere(function ($ended): void {
                        $ended->whereNotNull('ends_at')->where('ends_at', '<', now());
                    });
            })
            ->whereHas('results')
            ->orderBy('id')
            ->each(function (Survey $survey) use (&$queued): void {
                $key = ActionMeasuresGenerator::pendingCacheKey($survey->id);
                if (Cache::has($key)) {
                    return;
                }

                Cache::put($key, true, now()->addMinutes(15));
                GenerateActionMeasuresJob::dispatch($survey->id);
                $queued++;
            });

        $this->info("Pesquisas enfileiradas para medidas: {$queued}.");

        return self::SUCCESS;
    }
}
