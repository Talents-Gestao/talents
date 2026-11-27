<?php

declare(strict_types=1);

namespace Tests\Unit\Hiring;

use App\Enums\EmploymentCycleStatus;
use App\Enums\FollowupInvitationState;
use App\Enums\FollowupMoment;
use App\Enums\FollowupStatus;
use App\Support\Hiring\FollowupMilestoneStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FollowupMilestoneStatusTest extends TestCase
{
    public function test_due_dates_are_admission_plus_n_days(): void
    {
        $admission = CarbonImmutable::parse('2026-01-31');

        $this->assertSame('2026-02-15', FollowupMilestoneStatus::dueOn($admission, FollowupMoment::D15)->toDateString());
        $this->assertSame('2026-03-02', FollowupMilestoneStatus::dueOn($admission, FollowupMoment::D30)->toDateString());
        $this->assertSame('2026-04-01', FollowupMilestoneStatus::dueOn($admission, FollowupMoment::D60)->toDateString());
        $this->assertSame('2026-05-01', FollowupMilestoneStatus::dueOn($admission, FollowupMoment::D90)->toDateString());
    }

    public function test_each_milestone_status(): void
    {
        $due = CarbonImmutable::parse('2026-03-10');
        $active = EmploymentCycleStatus::Active;
        $status = fn (string $today, bool $done = false, EmploymentCycleStatus $cycle = EmploymentCycleStatus::Active) => FollowupMilestoneStatus::forMilestone($cycle, $due, $done, CarbonImmutable::parse($today));

        $this->assertSame(FollowupStatus::Scheduled, $status('2026-03-09'));
        $this->assertSame(FollowupStatus::Due, $status('2026-03-10'));
        $this->assertSame(FollowupStatus::Due, $status('2026-03-17'));
        $this->assertSame(FollowupStatus::Overdue, $status('2026-03-18'));
        $this->assertSame(FollowupStatus::Completed, $status('2026-03-30', true));
        $this->assertSame(FollowupStatus::Paused, $status('2026-03-30', false, EmploymentCycleStatus::Paused));
        $this->assertSame(FollowupStatus::Terminated, $status('2026-03-30', false, EmploymentCycleStatus::Terminated));
        $this->assertSame(FollowupStatus::Completed, $status('2026-03-30', true, EmploymentCycleStatus::Terminated));
        $this->assertSame(FollowupStatus::PendingAdmission, $status('2026-03-30', false, EmploymentCycleStatus::PendingAdmission));
        $this->assertSame(FollowupStatus::Scheduled, FollowupMilestoneStatus::forMilestone($active, $due, false, CarbonImmutable::parse('2026-03-09 23:59')));
    }

    public function test_cycle_status_in_the_queue(): void
    {
        $active = EmploymentCycleStatus::Active;

        $this->assertSame(FollowupStatus::Overdue, FollowupMilestoneStatus::forCycle($active, [
            'd15' => FollowupStatus::Completed, 'd30' => FollowupStatus::Overdue, 'd60' => FollowupStatus::Scheduled, 'd90' => FollowupStatus::Scheduled,
        ]));
        $this->assertSame(FollowupStatus::Due, FollowupMilestoneStatus::forCycle($active, [
            'd15' => FollowupStatus::Due, 'd30' => FollowupStatus::Scheduled,
        ]));
        $this->assertSame(FollowupStatus::Scheduled, FollowupMilestoneStatus::forCycle($active, ['d15' => FollowupStatus::Scheduled]));
        // Depois do D90 concluído, o acompanhamento do ciclo fica concluído.
        $this->assertSame(FollowupStatus::Completed, FollowupMilestoneStatus::forCycle($active, [
            'd15' => FollowupStatus::Completed, 'd30' => FollowupStatus::Overdue, 'd60' => FollowupStatus::Completed, 'd90' => FollowupStatus::Completed,
        ]));
        $this->assertSame(FollowupStatus::PendingAdmission, FollowupMilestoneStatus::forCycle(EmploymentCycleStatus::PendingAdmission, []));
        $this->assertSame(FollowupStatus::Paused, FollowupMilestoneStatus::forCycle(EmploymentCycleStatus::Paused, ['d15' => FollowupStatus::Paused]));
        $this->assertSame(FollowupStatus::Terminated, FollowupMilestoneStatus::forCycle(EmploymentCycleStatus::Terminated, ['d15' => FollowupStatus::Terminated]));
    }

    public function test_invitation_states_are_never_confused(): void
    {
        $now = CarbonImmutable::parse('2026-03-10 12:00');
        $future = $now->addDays(10);
        $past = $now->subDay();
        $state = fn (...$args) => FollowupMilestoneStatus::invitationState(...[...$args, $now]);

        $this->assertSame(FollowupInvitationState::Generated, $state(null, null, $future, null, null, null));
        $this->assertSame(FollowupInvitationState::Copied, $state(null, null, $future, null, null, $now));
        $this->assertSame(FollowupInvitationState::Emailed, $state(null, null, $future, $now, null, $now));
        $this->assertSame(FollowupInvitationState::Failed, $state(null, null, $future, null, 'SMTP fora', null));
        $this->assertSame(FollowupInvitationState::Answered, $state(null, $now, $future, $now, null, null));
        $this->assertSame(FollowupInvitationState::Expired, $state(null, null, $past, $past, null, null));
        $this->assertSame(FollowupInvitationState::Revoked, $state($now, null, $future, $now, null, $now));
        $this->assertSame('Link copiado', FollowupInvitationState::Copied->label());
        $this->assertSame('Enviado ao servidor de e-mail', FollowupInvitationState::Emailed->label());
    }
}
