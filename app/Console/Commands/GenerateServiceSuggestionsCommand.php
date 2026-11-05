<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateServiceSuggestionsJob;
use App\Models\Survey;
use App\Services\ServiceSuggestionsGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateServiceSuggestionsCommand extends Command
{
    protected $signature = 'nr1:generate-service-suggestions';

    protected $description = 'Reprocessa pesquisas já encerradas que ainda não tiveram a sugestão de serviços. O disparo normal ocorre ao marcar a pesquisa como encerrada.';

    public function handle(): int
    {
        $queued = 0;

        Survey::query()
            ->whereNull('service_suggestions_generated_at')
            ->where(function ($query): void {
                $query->where('status', 'closed')
                    ->orWhere(function ($ended): void {
                        $ended->whereNotNull('ends_at')->where('ends_at', '<', now());
                    });
            })
            ->whereHas('results')
            ->orderBy('id')
            ->each(function (Survey $survey) use (&$queued): void {
                $key = ServiceSuggestionsGenerator::pendingCacheKey($survey->id);
                if (Cache::has($key)) {
                    return;
                }

                Cache::put($key, true, now()->addMinutes(15));
                GenerateServiceSuggestionsJob::dispatch($survey->id);
                $queued++;
            });

        $this->info("Pesquisas enfileiradas para sugestões de serviço: {$queued}.");

        return self::SUCCESS;
    }
}
