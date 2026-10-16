<?php

declare(strict_types=1);

namespace App\Services\ClientEvaluation;

use App\Enums\ClientEvaluationStatus;
use App\Models\ClientEvaluation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientEvaluationInviteService
{
    public function expireOverdue(): void
    {
        ClientEvaluation::query()
            ->where('status', ClientEvaluationStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update(['status' => ClientEvaluationStatus::Expired->value]);
    }

    /**
     * @param  array{
     *     company_id: int,
     *     cycle_year: int,
     *     cycle_quarter: int,
     *     respondent_name: string,
     *     respondent_email: string,
     *     respondent_phone?: string|null,
     *     expires_at: string
     * }  $data
     */
    public function create(array $data, User $actor): ClientEvaluation
    {
        return DB::transaction(function () use ($data, $actor): ClientEvaluation {
            $this->expireCycle(
                (int) $data['company_id'],
                (int) $data['cycle_year'],
                (int) $data['cycle_quarter'],
            );

            $open = ClientEvaluation::query()
                ->where('company_id', $data['company_id'])
                ->where('cycle_year', $data['cycle_year'])
                ->where('cycle_quarter', $data['cycle_quarter'])
                ->whereIn('status', [
                    ClientEvaluationStatus::Pending->value,
                    ClientEvaluationStatus::Answered->value,
                ])
                ->lockForUpdate()
                ->get();

            if ($open->contains(fn (ClientEvaluation $row): bool => $row->status === ClientEvaluationStatus::Answered)) {
                throw ValidationException::withMessages([
                    'cycle_quarter' => 'Já existe uma avaliação respondida neste ciclo para esta empresa.',
                ]);
            }

            foreach ($open as $row) {
                if ($row->status === ClientEvaluationStatus::Pending) {
                    $row->update(['status' => ClientEvaluationStatus::Superseded]);
                }
            }

            try {
                return ClientEvaluation::query()->create([
                    'company_id' => $data['company_id'],
                    'created_by' => $actor->id,
                    'cycle_year' => $data['cycle_year'],
                    'cycle_quarter' => $data['cycle_quarter'],
                    'public_token' => (string) Str::uuid(),
                    'status' => ClientEvaluationStatus::Pending,
                    'respondent_name' => $data['respondent_name'],
                    'respondent_email' => $data['respondent_email'],
                    'respondent_phone' => filled($data['respondent_phone'] ?? null) ? $data['respondent_phone'] : null,
                    'expires_at' => Carbon::parse($data['expires_at'])->endOfDay(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'cycle_quarter' => 'Já existe uma avaliação em aberto neste ciclo para esta empresa.',
                ]);
            }
        });
    }

    /**
     * @param  array{
     *     nps_score: int,
     *     service_score: int,
     *     delivery_score: int,
     *     communication_score: int,
     *     comment?: string|null,
     *     answered_name: string,
     *     answered_role?: string|null
     * }  $data
     */
    public function answer(ClientEvaluation $evaluation, array $data): void
    {
        DB::transaction(function () use ($evaluation, $data): void {
            $locked = ClientEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ClientEvaluationStatus::Pending && $locked->expires_at->isPast()) {
                $locked->update(['status' => ClientEvaluationStatus::Expired]);
            }

            if ($locked->status !== ClientEvaluationStatus::Pending) {
                throw ValidationException::withMessages([
                    'nps_score' => $locked->status->unavailableMessage(),
                ]);
            }

            $locked->update([
                'status' => ClientEvaluationStatus::Answered,
                'nps_score' => $data['nps_score'],
                'service_score' => $data['service_score'],
                'delivery_score' => $data['delivery_score'],
                'communication_score' => $data['communication_score'],
                'comment' => filled($data['comment'] ?? null) ? $data['comment'] : null,
                'answered_name' => $data['answered_name'],
                'answered_role' => filled($data['answered_role'] ?? null) ? $data['answered_role'] : null,
                'answered_at' => now(),
            ]);
        });
    }

    public function refresh(ClientEvaluation $evaluation): ClientEvaluation
    {
        if ($evaluation->status === ClientEvaluationStatus::Pending && $evaluation->expires_at->isPast()) {
            $evaluation->update(['status' => ClientEvaluationStatus::Expired]);
        }

        return $evaluation->refresh();
    }

    private function expireCycle(int $companyId, int $year, int $quarter): void
    {
        ClientEvaluation::query()
            ->where('company_id', $companyId)
            ->where('cycle_year', $year)
            ->where('cycle_quarter', $quarter)
            ->where('status', ClientEvaluationStatus::Pending->value)
            ->where('expires_at', '<', now())
            ->update(['status' => ClientEvaluationStatus::Expired->value]);
    }
}
