<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\CompanyContactTopic;
use App\Enums\EmploymentCycleEventType;
use App\Enums\EmploymentCycleStatus;
use App\Enums\FollowupMoment;
use App\Models\CompanyContact;
use App\Models\CompanyEmployee;
use App\Models\EmploymentCycle;
use App\Models\EmploymentCycleEvent;
use App\Models\HiringApplication;
use App\Models\HiringFollowupMilestone;
use App\Support\Hiring\FollowupMilestoneStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admissão e ciclos de vínculo do pós-contratação:
 * - sem data de admissão confirmada, o ciclo fica em «admissão pendente» e não tem marcos;
 * - confirmar a admissão cria os marcos D15/D30/D60/D90 (admissão + N dias). Admissão antiga gera marcos
 *   já vencidos: eles só pedem revisão, nada é enviado sozinho (não há envio agendado);
 * - só um ciclo em aberto (admissão pendente, ativo ou pausado) por colaborador e empresa;
 * - readmissão exige data válida e cria um ciclo novo, preservando os anteriores.
 * Toda mudança fica em employment_cycle_events (só inserção).
 */
final class EmploymentCycleService
{
    /** Campos do vínculo editáveis no detalhe do ciclo. */
    private const DETAIL_FIELDS = ['role_title', 'manager_contact_id', 'collaborator_email', 'collaborator_phone', 'collaborator_email_opt_in'];

    /**
     * «Registrar admissão» numa candidatura na etapa de contratado.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerAdmission(HiringApplication $application, array $data, ?int $userId): EmploymentCycle
    {
        $application->loadMissing(['stage', 'vacancy', 'candidate']);

        if (! $application->stage?->is_hired) {
            throw ValidationException::withMessages(['admission' => 'A candidatura precisa estar na etapa de contratado para registrar a admissão.']);
        }
        if (EmploymentCycle::query()->where('hiring_application_id', $application->id)->exists()) {
            throw ValidationException::withMessages(['admission' => 'A admissão desta candidatura já foi registrada.']);
        }

        $companyId = (int) $application->vacancy->company_id;

        return DB::transaction(function () use ($application, $data, $userId, $companyId): EmploymentCycle {
            $employee = $this->resolveEmployee($companyId, $application, $data);
            $this->assertNoOpenCycle($employee);
            $manager = $this->resolveManager($companyId, $data['manager_contact_id'] ?? null);

            if ($employee->talent_candidate_id === null && $application->talent_candidate_id !== null) {
                $employee->talent_candidate_id = $application->talent_candidate_id;
                $employee->save();
            }

            $cycle = EmploymentCycle::query()->create([
                'company_id' => $companyId,
                'company_employee_id' => $employee->id,
                'hiring_application_id' => $application->id,
                'status' => EmploymentCycleStatus::PendingAdmission,
                'role_title' => $this->clean($data['role_title'] ?? null) ?? $application->vacancy->title,
                'manager_contact_id' => $manager?->id,
                'collaborator_email' => $this->clean($data['collaborator_email'] ?? null) ?? $employee->email ?? $application->candidate?->email,
                'collaborator_phone' => $this->clean($data['collaborator_phone'] ?? null) ?? $employee->phone ?? $application->candidate?->phone,
                'collaborator_email_opt_in' => (bool) ($data['collaborator_email_opt_in'] ?? false),
                'created_by' => $userId,
            ]);

            $this->event($cycle, EmploymentCycleEventType::Created, $userId, meta: ['hiring_application_id' => $application->id]);

            if (! empty($data['admission_date'])) {
                $this->activate($cycle, $this->date($data['admission_date']), $userId);
            }

            return $cycle->fresh();
        });
    }

    /** Confirma a admissão com a data: o ciclo fica ativo e ganha os marcos. */
    public function confirmAdmission(EmploymentCycle $cycle, string $admissionDate, ?int $userId): EmploymentCycle
    {
        if ($cycle->status !== EmploymentCycleStatus::PendingAdmission) {
            throw ValidationException::withMessages(['admission_date' => 'Só um ciclo com admissão pendente pode ter a admissão confirmada.']);
        }

        return DB::transaction(function () use ($cycle, $admissionDate, $userId): EmploymentCycle {
            $this->activate($cycle, $this->date($admissionDate), $userId);

            return $cycle->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmploymentCycle $cycle, array $data, ?int $userId): EmploymentCycle
    {
        return DB::transaction(function () use ($cycle, $data, $userId): EmploymentCycle {
            if (array_key_exists('manager_contact_id', $data)) {
                $data['manager_contact_id'] = $this->resolveManager((int) $cycle->company_id, $data['manager_contact_id'])?->id;
            }
            $cycle->fill(array_intersect_key($data, array_flip(self::DETAIL_FIELDS)));
            $cycle->save();

            $changed = array_values(array_diff(array_keys($cycle->getChanges()), ['updated_at']));
            if ($changed !== []) {
                $this->event($cycle, EmploymentCycleEventType::Updated, $userId, meta: ['fields' => $changed]);
            }

            return $cycle;
        });
    }

    public function pause(EmploymentCycle $cycle, string $reason, ?int $userId): EmploymentCycle
    {
        if ($cycle->status !== EmploymentCycleStatus::Active) {
            throw ValidationException::withMessages(['cycle' => 'Só um ciclo ativo pode ser pausado.']);
        }

        return DB::transaction(function () use ($cycle, $reason, $userId): EmploymentCycle {
            $cycle->update(['status' => EmploymentCycleStatus::Paused, 'paused_at' => now(), 'pause_reason' => trim($reason)]);
            $this->event($cycle, EmploymentCycleEventType::Paused, $userId, trim($reason));

            return $cycle;
        });
    }

    public function resume(EmploymentCycle $cycle, ?int $userId): EmploymentCycle
    {
        if ($cycle->status !== EmploymentCycleStatus::Paused) {
            throw ValidationException::withMessages(['cycle' => 'Só um ciclo pausado pode ser retomado.']);
        }

        return DB::transaction(function () use ($cycle, $userId): EmploymentCycle {
            $cycle->update(['status' => EmploymentCycleStatus::Active, 'paused_at' => null, 'pause_reason' => null]);
            $this->event($cycle, EmploymentCycleEventType::Resumed, $userId);

            return $cycle;
        });
    }

    /** Desligamento: data opcional. Convites em aberto do ciclo são revogados. */
    public function terminate(EmploymentCycle $cycle, ?string $terminatedOn, ?string $note, ?int $userId): EmploymentCycle
    {
        if (! $cycle->status->isOpen()) {
            throw ValidationException::withMessages(['cycle' => 'Este ciclo já está encerrado.']);
        }

        return DB::transaction(function () use ($cycle, $terminatedOn, $note, $userId): EmploymentCycle {
            $date = $terminatedOn !== null && $terminatedOn !== '' ? $this->date($terminatedOn, 'terminated_on') : null;
            if ($date !== null && $cycle->admission_date !== null && $date->lt($cycle->admission_date)) {
                throw ValidationException::withMessages(['terminated_on' => 'A data de desligamento não pode ser anterior à admissão.']);
            }

            $cycle->update([
                'status' => EmploymentCycleStatus::Terminated,
                'terminated_on' => $date?->toDateString(),
                'termination_note' => $this->clean($note),
                'paused_at' => null,
            ]);

            app(FollowupInvitationService::class)->revokeOpenInvitations($cycle, 'Desligamento registrado.', $userId);
            $this->event($cycle, EmploymentCycleEventType::Terminated, $userId, $this->clean($note), [
                'terminated_on' => $date?->toDateString(),
            ]);

            return $cycle;
        });
    }

    /** Readmissão: data obrigatória; cria um ciclo novo e preserva os anteriores. */
    public function readmit(EmploymentCycle $previous, ?string $admissionDate, ?int $userId): EmploymentCycle
    {
        if ($previous->status !== EmploymentCycleStatus::Terminated) {
            throw ValidationException::withMessages(['cycle' => 'Só é possível readmitir a partir de um ciclo encerrado.']);
        }
        if ($admissionDate === null || trim($admissionDate) === '') {
            throw ValidationException::withMessages(['admission_date' => 'Informe a data da readmissão.']);
        }

        return DB::transaction(function () use ($previous, $admissionDate, $userId): EmploymentCycle {
            $date = $this->date($admissionDate);
            if ($previous->terminated_on !== null && $date->lte($previous->terminated_on)) {
                throw ValidationException::withMessages(['admission_date' => 'A readmissão precisa ser depois do desligamento anterior.']);
            }

            $employee = $previous->employee;
            $this->assertNoOpenCycle($employee);

            $cycle = EmploymentCycle::query()->create([
                'company_id' => $previous->company_id,
                'company_employee_id' => $previous->company_employee_id,
                'hiring_application_id' => null,
                'status' => EmploymentCycleStatus::PendingAdmission,
                'role_title' => $previous->role_title,
                'manager_contact_id' => $previous->manager_contact_id,
                'collaborator_email' => $previous->collaborator_email,
                'collaborator_phone' => $previous->collaborator_phone,
                'collaborator_email_opt_in' => $previous->collaborator_email_opt_in,
                'created_by' => $userId,
            ]);

            $this->event($cycle, EmploymentCycleEventType::Readmitted, $userId, meta: ['previous_cycle_id' => $previous->id]);
            $this->event($previous, EmploymentCycleEventType::Readmitted, $userId, meta: ['new_cycle_id' => $cycle->id]);
            $this->activate($cycle, $date, $userId);

            return $cycle->fresh();
        });
    }

    public function addNote(EmploymentCycle $cycle, string $note, ?int $userId): EmploymentCycleEvent
    {
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Escreva a anotação.']);
        }

        return $this->event($cycle, EmploymentCycleEventType::Note, $userId, $note);
    }

    /** Pular o marco (com motivo) conta como concluído; convites em aberto dele são revogados. */
    public function skipMilestone(HiringFollowupMilestone $milestone, string $reason, ?int $userId): HiringFollowupMilestone
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['skip_reason' => 'Informe o motivo para pular o marco.']);
        }
        if ($milestone->isDone()) {
            throw ValidationException::withMessages(['skip_reason' => 'Este marco já está concluído.']);
        }

        return DB::transaction(function () use ($milestone, $reason, $userId): HiringFollowupMilestone {
            $milestone->update(['skipped_at' => now(), 'skip_reason' => $reason]);
            app(FollowupInvitationService::class)->revokeOpenInvitations($milestone->cycle, 'Marco pulado.', $userId, $milestone);
            $this->event($milestone->cycle, EmploymentCycleEventType::MilestoneSkipped, $userId, $reason, ['moment' => $milestone->moment->value]);

            return $milestone;
        });
    }

    /**
     * Gestor do vínculo: contato da empresa com o tópico pós-contratação (pode ainda não estar conferido;
     * a elegibilidade para receber convite é decidida no envio).
     */
    public function resolveManager(int $companyId, mixed $contactId): ?CompanyContact
    {
        if ($contactId === null || $contactId === '') {
            return null;
        }

        $manager = CompanyContact::query()->whereKey((int) $contactId)->where('company_id', $companyId)->first();
        if ($manager === null) {
            throw ValidationException::withMessages(['manager_contact_id' => 'Escolha um gestor entre os contatos da empresa.']);
        }
        if ($manager->isArchived() || ! in_array(CompanyContactTopic::PosContratacao->value, $manager->topicValues(), true)) {
            throw ValidationException::withMessages(['manager_contact_id' => 'O gestor precisa ser um contato ativo da empresa com o tópico «Pós-contratação».']);
        }

        return $manager;
    }

    private function activate(EmploymentCycle $cycle, Carbon $admissionDate, ?int $userId): void
    {
        $cycle->update([
            'status' => EmploymentCycleStatus::Active,
            'admission_date' => $admissionDate->toDateString(),
            'active_confirmed_at' => now(),
        ]);

        foreach (FollowupMoment::ordered() as $moment) {
            HiringFollowupMilestone::query()->firstOrCreate(
                ['employment_cycle_id' => $cycle->id, 'moment' => $moment->value],
                ['due_on' => FollowupMilestoneStatus::dueOn($admissionDate, $moment)->toDateString()],
            );
        }

        $this->event($cycle, EmploymentCycleEventType::AdmissionConfirmed, $userId, meta: ['admission_date' => $admissionDate->toDateString()]);
    }

    /**
     * Colaborador da empresa da vaga: o escolhido ou um novo, deduplicado por e-mail dentro da empresa.
     * O nome não identifica a pessoa.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveEmployee(int $companyId, HiringApplication $application, array $data): CompanyEmployee
    {
        if (! empty($data['company_employee_id'])) {
            $employee = CompanyEmployee::query()->whereKey((int) $data['company_employee_id'])->where('company_id', $companyId)->first();
            if ($employee === null || $employee->merged_into_employee_id !== null) {
                throw ValidationException::withMessages(['company_employee_id' => 'Escolha um colaborador desta empresa.']);
            }

            return $employee;
        }

        $email = mb_strtolower(trim((string) ($data['employee_email'] ?? $application->candidate?->email ?? '')));
        if ($email !== '') {
            $existing = CompanyEmployee::query()
                ->where('company_id', $companyId)
                ->whereNull('merged_into_employee_id')
                ->whereRaw('lower(email) = ?', [$email])
                ->orderBy('id')
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $name = $this->clean($data['employee_name'] ?? null) ?? $application->candidate?->name;
        if ($name === null) {
            throw ValidationException::withMessages(['employee_name' => 'Informe o nome do colaborador.']);
        }

        return CompanyEmployee::query()->create([
            'company_id' => $companyId,
            'talent_candidate_id' => $application->talent_candidate_id,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $this->clean($data['employee_phone'] ?? null) ?? $application->candidate?->phone,
            'is_active' => true,
        ]);
    }

    private function assertNoOpenCycle(CompanyEmployee $employee): void
    {
        $open = EmploymentCycle::query()
            ->where('company_id', $employee->company_id)
            ->where('company_employee_id', $employee->id)
            ->where('status', '!=', EmploymentCycleStatus::Terminated->value)
            ->lockForUpdate()
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'admission' => 'Este colaborador já tem um vínculo em aberto nesta empresa. Registre o desligamento antes de abrir outro.',
            ]);
        }
    }

    private function date(string $value, string $field = 'admission_date'): Carbon
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, 'America/Sao_Paulo');
        } catch (\Throwable) {
            $date = false;
        }
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Informe uma data válida.']);
        }
        if ($date->gt(now('America/Sao_Paulo')->addYear())) {
            throw ValidationException::withMessages([$field => 'A data está muito no futuro.']);
        }

        return $date;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function event(EmploymentCycle $cycle, EmploymentCycleEventType $type, ?int $userId, ?string $note = null, array $meta = []): EmploymentCycleEvent
    {
        return EmploymentCycleEvent::query()->create([
            'employment_cycle_id' => $cycle->id,
            'type' => $type,
            'note' => $note,
            'meta' => $meta === [] ? null : $meta,
            'user_id' => $userId,
        ]);
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
