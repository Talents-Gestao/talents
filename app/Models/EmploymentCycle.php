<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentCycleStatus;
use App\Enums\FollowupStatus;
use App\Support\Hiring\FollowupMilestoneStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ciclo de vínculo do colaborador com a empresa: da admissão (confirmada ou pendente) ao desligamento.
 * Readmissão cria um ciclo novo; os anteriores ficam. Só um ciclo em aberto por colaborador e empresa.
 * Uso interno da Talents: nada disto vai ao portal da empresa.
 */
class EmploymentCycle extends Model
{
    protected $fillable = [
        'company_id',
        'company_employee_id',
        'hiring_application_id',
        'status',
        'admission_date',
        'active_confirmed_at',
        'role_title',
        'manager_contact_id',
        'collaborator_email',
        'collaborator_phone',
        'collaborator_email_opt_in',
        'paused_at',
        'pause_reason',
        'terminated_on',
        'termination_note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmploymentCycleStatus::class,
            'admission_date' => 'date',
            'active_confirmed_at' => 'datetime',
            'collaborator_email_opt_in' => 'boolean',
            'paused_at' => 'datetime',
            'terminated_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EmploymentCycle $cycle): void {
            $email = mb_strtolower(trim((string) $cycle->collaborator_email));
            $cycle->collaborator_email = $email !== '' ? $email : null;
            $phone = trim((string) $cycle->collaborator_phone);
            $cycle->collaborator_phone = $phone !== '' ? $phone : null;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(HiringApplication::class, 'hiring_application_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'manager_contact_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(HiringFollowupMilestone::class)->orderBy('due_on');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmploymentCycleEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Status do ciclo na fila (precisa dos marcos carregados). */
    public function followupStatus(?\DateTimeInterface $today = null): FollowupStatus
    {
        $today ??= now('America/Sao_Paulo');
        $statuses = [];
        foreach ($this->milestones as $milestone) {
            $statuses[$milestone->moment->value] = $milestone->status($this->status, $today);
        }

        return FollowupMilestoneStatus::forCycle($this->status, $statuses);
    }

    /** Próximo marco em aberto (ainda não concluído nem pulado). */
    public function nextMilestone(): ?HiringFollowupMilestone
    {
        return $this->milestones->first(fn (HiringFollowupMilestone $m) => ! $m->isDone());
    }
}
