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
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'birth_date' => 'date',
            'admission_date' => 'date',
        ];
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
}
