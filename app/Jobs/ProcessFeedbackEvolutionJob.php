<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FeedbackEvolutionReport;
use App\Models\User;
use App\Services\Feedback\Ai\FeedbackAiException;
use App\Services\Feedback\Ai\FeedbackEvolutionGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Gera a narrativa de Evolução na fila: processing → ready | failed, com mensagem em português ao usuário. */
class ProcessFeedbackEvolutionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public const GENERIC_ERROR = 'Não foi possível gerar a evolução. Tente de novo; se continuar, fale com a Talents.';

    public function __construct(public readonly int $evolutionId, public readonly ?int $actorId = null) {}

    public function handle(FeedbackEvolutionGenerator $generator): void
    {
        $evolution = FeedbackEvolutionReport::query()->with('employee.company')->find($this->evolutionId);
        if ($evolution === null || $evolution->status !== FeedbackEvolutionReport::STATUS_PROCESSING) {
            return;
        }

        try {
            $generator->generate($evolution, $this->actorId ? User::query()->find($this->actorId) : null);
        } catch (FeedbackAiException $e) {
            Log::warning('Feedbacks IA: evolução falhou', ['evolution_id' => $evolution->id, 'code' => $e->errorCode]);
            $this->markFailed($evolution, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($evolution, self::GENERIC_ERROR);
        }
    }

    public function failed(?Throwable $e): void
    {
        $evolution = FeedbackEvolutionReport::query()->find($this->evolutionId);
        if ($evolution !== null && $evolution->status === FeedbackEvolutionReport::STATUS_PROCESSING) {
            $this->markFailed($evolution, self::GENERIC_ERROR);
        }
    }

    private function markFailed(FeedbackEvolutionReport $evolution, string $message): void
    {
        $evolution->forceFill(['status' => FeedbackEvolutionReport::STATUS_FAILED, 'error' => mb_substr($message, 0, 500)])->save();
    }
}
