<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExitInterviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitInterview extends Model
{
    protected $fillable = [
        'company_id',
        'company_employee_id',
        'interview_date',
        'status',
        'answers',
        'consultant_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
            'status' => ExitInterviewStatus::class,
            'answers' => 'array',
            'consultant_notes' => 'array',
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
}
