<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDiagnostic extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'company_name',
        'cnpj',
        'segment',
        'employee_count',
        'responsible_name',
        'email',
        'phone',
        'company_history',
        'biggest_challenge',
        'hr_maturity',
    ];

    protected function casts(): array
    {
        return [
            'hr_maturity' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
