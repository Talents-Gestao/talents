<?php

declare(strict_types=1);

namespace App\Support\Hiring;

use App\Enums\EmploymentCycleStatus;
use App\Enums\FollowupInvitationState;
use App\Enums\FollowupMoment;
use App\Enums\FollowupStatus;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Cálculo puro (sem banco) das datas e dos status do pós-contratação:
 * - due_on = admissão + N dias (D15, D30, D60, D90);
 * - programado: antes da data; devido: da data até a data + 7 dias; vencido · revisar: depois disso;
 * - concluído: com resposta ou pulado; pausado: ciclo pausado; desligado: ciclo encerrado;
 * - o ciclo fica concluído depois do D90 concluído.
 * Nada aqui envia convite: status vencido só pede revisão humana.
 */
final class FollowupMilestoneStatus
{
    /** Dias, a partir de due_on, em que o marco ainda está «devido». */
    public const DUE_WINDOW_DAYS = 7;

    public static function dueOn(DateTimeInterface $admissionDate, FollowupMoment $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($admissionDate)->startOfDay()->addDays($moment->days());
    }

    public static function forMilestone(
        EmploymentCycleStatus $cycleStatus,
        DateTimeInterface $dueOn,
        bool $completed,
        DateTimeInterface $today,
    ): FollowupStatus {
        if ($cycleStatus === EmploymentCycleStatus::Terminated) {
            return $completed ? FollowupStatus::Completed : FollowupStatus::Terminated;
        }
        if ($completed) {
            return FollowupStatus::Completed;
        }
        if ($cycleStatus === EmploymentCycleStatus::PendingAdmission) {
            return FollowupStatus::PendingAdmission;
        }
        if ($cycleStatus === EmploymentCycleStatus::Paused) {
            return FollowupStatus::Paused;
        }

        $day = CarbonImmutable::instance($today)->startOfDay();
        $due = CarbonImmutable::instance($dueOn)->startOfDay();

        if ($day->lt($due)) {
            return FollowupStatus::Scheduled;
        }
        if ($day->lte($due->addDays(self::DUE_WINDOW_DAYS))) {
            return FollowupStatus::Due;
        }

        return FollowupStatus::Overdue;
    }

    /**
     * Status do ciclo na fila: admissão pendente, pausado e desligado vêm do ciclo; com o D90 concluído, concluído;
     * senão, o mais urgente dos marcos em aberto (vencido > devido > programado).
     *
     * @param  array<string, FollowupStatus>  $milestoneStatuses  momento => status
     */
    public static function forCycle(EmploymentCycleStatus $cycleStatus, array $milestoneStatuses): FollowupStatus
    {
        if ($cycleStatus === EmploymentCycleStatus::PendingAdmission) {
            return FollowupStatus::PendingAdmission;
        }
        if ($cycleStatus === EmploymentCycleStatus::Terminated) {
            return FollowupStatus::Terminated;
        }
        if (($milestoneStatuses[FollowupMoment::D90->value] ?? null) === FollowupStatus::Completed) {
            return FollowupStatus::Completed;
        }
        if ($cycleStatus === EmploymentCycleStatus::Paused) {
            return FollowupStatus::Paused;
        }

        foreach ([FollowupStatus::Overdue, FollowupStatus::Due, FollowupStatus::Scheduled] as $candidate) {
            if (in_array($candidate, $milestoneStatuses, true)) {
                return $candidate;
            }
        }

        return $milestoneStatuses === [] ? FollowupStatus::Scheduled : FollowupStatus::Completed;
    }

    /**
     * Estado principal do convite. Os fatos (copiado, aceito pelo servidor, respondido) continuam separados
     * nas datas do registro; aqui só se escolhe o que aparece primeiro.
     */
    public static function invitationState(
        ?DateTimeInterface $revokedAt,
        ?DateTimeInterface $answeredAt,
        DateTimeInterface $expiresAt,
        ?DateTimeInterface $emailedAt,
        ?string $emailFailureReason,
        ?DateTimeInterface $copiedAt,
        DateTimeInterface $now,
    ): FollowupInvitationState {
        if ($revokedAt !== null) {
            return FollowupInvitationState::Revoked;
        }
        if ($answeredAt !== null) {
            return FollowupInvitationState::Answered;
        }
        if (CarbonImmutable::instance($now)->gte(CarbonImmutable::instance($expiresAt))) {
            return FollowupInvitationState::Expired;
        }
        if ($emailedAt !== null) {
            return FollowupInvitationState::Emailed;
        }
        if ($emailFailureReason !== null && $emailFailureReason !== '') {
            return FollowupInvitationState::Failed;
        }
        if ($copiedAt !== null) {
            return FollowupInvitationState::Copied;
        }

        return FollowupInvitationState::Generated;
    }
}
