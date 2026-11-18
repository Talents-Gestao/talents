<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyEmployee extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'birth_date',
        'phone',
        'address_zip',
        'address_street',
        'address_number',
        'address_complement',
        'address_neighborhood',
        'address_city',
        'address_state',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'department_id',
        'position_id',
        'leader_user_id',
        'admission_date',
        'work_schedule',
        'cpf',
        'rg',
        'is_active',
        'notes',
        'merged_into_employee_id',
        'merged_at',
        'merged_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'birth_date' => 'date',
            'admission_date' => 'date',
            'merged_at' => 'datetime',
        ];
    }

    /** Cadastro que ficou, quando este foi absorvido numa união (Feedbacks, Fase 2). */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_employee_id');
    }

    /** Segue a cadeia de uniões até o cadastro que ficou (protege contra ciclos). */
    public function resolveMerged(): self
    {
        $current = $this;
        for ($i = 0; $i < 10 && $current->merged_into_employee_id !== null; $i++) {
            $next = self::query()->find($current->merged_into_employee_id);
            if ($next === null || $next->company_id !== $this->company_id) {
                break;
            }
            $current = $next;
        }

        return $current;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function feedbackSessions(): HasMany
    {
        return $this->hasMany(FeedbackSession::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    public function exitInterviews(): HasMany
    {
        return $this->hasMany(ExitInterview::class);
    }

    public function evolutionReports(): HasMany
    {
        return $this->hasMany(FeedbackEvolutionReport::class);
    }
}
