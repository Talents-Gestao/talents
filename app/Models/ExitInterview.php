<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExitInterviewStatus;
use App\Models\Concerns\HasRhidCollaborator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExitInterview extends Model
{
    use HasRhidCollaborator;

    protected $fillable = [
        'company_id',
        'company_employee_id',
        'rhid_person_id',
        'employee_name',
        'employee_email',
        'interview_date',
        'status',
        'answers',
        'consultant_notes',
        'created_by',
        'public_token',
        'employee_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
            'status' => ExitInterviewStatus::class,
            'answers' => 'array',
            'consultant_notes' => 'array',
            'employee_submitted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ensurePublicToken(): string
    {
        if (filled($this->public_token)) {
            return (string) $this->public_token;
        }

        $this->forceFill([
            'public_token' => (string) Str::uuid(),
        ])->save();

        return (string) $this->public_token;
    }

    public function publicUrl(): ?string
    {
        if (! filled($this->public_token)) {
            return null;
        }

        return route('desligamento.public.show', $this->public_token);
    }

    public function acceptsEmployeeResponses(): bool
    {
        if (! filled($this->public_token)) {
            return false;
        }

        if ($this->employee_submitted_at !== null) {
            return false;
        }

        if ($this->status === ExitInterviewStatus::Completed) {
            return false;
        }

        $this->loadMissing('company');

        return $this->company !== null && $this->company->hasDesligamentoEnabled();
    }

    public function revokePublicToken(): void
    {
        $this->forceFill([
            'public_token' => null,
        ])->save();
    }
}
