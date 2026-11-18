<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Jobs\ProcessFeedbackEvolutionJob;
use App\Models\CompanyEmployee;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackEvolutionReport;
use App\Models\User;
use App\Services\Feedback\Ai\FeedbackAiException;
use App\Services\Feedback\Ai\FeedbackEvolutionGenerator;
use App\Services\Feedback\FeedbackEvolutionBuilder;
use App\Support\Feedback\Ai\FeedbackAiGate;
use App\Support\Feedback\Ai\FeedbackAiMonthlyLimit;
use App\Support\Feedback\Ai\FeedbackEvolutionSchema;
use Illuminate\Support\Facades\DB;

/**
 * Pede a narrativa de Evolução por IA para as sessões que o usuário vê: confere a trava do provedor, o limite
 * mensal e o mínimo de dois relatórios; cria a versão em «processando» e enfileira. Uma por vez por pessoa.
 */
class StartFeedbackEvolution
{
    public function __construct(
        private readonly FeedbackAiGate $gate,
        private readonly FeedbackAiMonthlyLimit $limit,
        private readonly FeedbackEvolutionBuilder $builder,
    ) {}

    /** @throws FeedbackAiException */
    public function execute(CompanyEmployee $employee, User $actor): FeedbackEvolutionReport
    {
        $this->gate->assertAllowed();
        $employee->loadMissing('company');
        $this->limit->assertWithin($employee->company);

        $view = $this->builder->build($employee, $actor);
        if (! $view['ready']) {
            throw new FeedbackAiException('É preciso ter pelo menos dois feedbacks com relatório pronto para gerar a evolução.', 'not_enough_sessions');
        }

        return DB::transaction(function () use ($employee, $actor, $view) {
            CompanyEmployee::query()->whereKey($employee->id)->lockForUpdate()->first();

            if (FeedbackEvolutionReport::query()->where('company_employee_id', $employee->id)->where('status', FeedbackEvolutionReport::STATUS_PROCESSING)->exists()) {
                throw new FeedbackAiException('Já há uma evolução sendo gerada para esta pessoa. Aguarde.', 'already_processing');
            }

            $evolution = FeedbackEvolutionReport::query()->create([
                'company_id' => $employee->company_id,
                'company_employee_id' => $employee->id,
                'session_ids' => $view['eligible_session_ids'],
                'report_ids' => $view['eligible_report_ids'],
                'status' => FeedbackEvolutionReport::STATUS_PROCESSING,
                'prompt_version' => FeedbackEvolutionGenerator::PROMPT_VERSION,
                'schema_version' => FeedbackEvolutionSchema::VERSION,
                'generated_by_user_id' => $actor->id,
            ]);

            FeedbackAuditLog::query()->create([
                'company_id' => $employee->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_EVOLUTION_REQUESTED,
                'subject_type' => FeedbackAuditLog::SUBJECT_EMPLOYEE,
                'subject_id' => $employee->id,
                'subject_label' => $employee->name,
                'sessions_count' => count($view['eligible_session_ids']),
                'details' => ['evolution_id' => $evolution->id, 'feedback_session_ids' => $view['eligible_session_ids']] + $this->gate->assertAllowed(),
            ]);

            ProcessFeedbackEvolutionJob::dispatch($evolution->id, $actor->id)->afterCommit();

            return $evolution;
        });
    }
}
