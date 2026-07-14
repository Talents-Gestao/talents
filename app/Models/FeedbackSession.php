<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackSessionStatus;
use App\Models\Concerns\HasRhidCollaborator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackSession extends Model
{
    use HasRhidCollaborator;

    protected $fillable = [
        'company_id',
        'feedback_template_id',
        'company_employee_id',
        'rhid_person_id',
        'employee_name',
        'employee_email',
        'leader_user_id',
        'created_by_user_id',
        'title',
        'status',
        'scheduled_at',
        'next_alignment_at',
        'completed_at',
        'section_extras',
    ];

    protected function casts(): array
    {
        return [
            'status' => FeedbackSessionStatus::class,
            'scheduled_at' => 'datetime',
            'next_alignment_at' => 'datetime',
            'completed_at' => 'datetime',
            'section_extras' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FeedbackTemplate::class, 'feedback_template_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackSessionAnswer::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(FeedbackSessionSignature::class);
    }

    public function isFullySigned(): bool
    {
        return $this->signatures()->whereNull('signed_at')->doesntExist()
            && $this->signatures()->count() >= 2;
    }
}
