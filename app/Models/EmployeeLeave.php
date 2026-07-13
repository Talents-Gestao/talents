<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployeeLeaveStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    protected $fillable = [
        'company_id',
        'company_employee_id',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => EmployeeLeaveStatus::class,
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

    public function daysCount(): int
    {
        /** @var CarbonInterface $start */
        $start = $this->start_date;
        /** @var CarbonInterface $end */
        $end = $this->end_date;

        return (int) $start->diffInDays($end) + 1;
    }
}
