<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskBoard extends Model
{
    protected $fillable = [
        'company_id',
        'process_template_id',
        'name',
        'description',
        'cover_color',
        'is_archived',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskProcessTemplate::class, 'process_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class, 'board_id')->orderBy('position')->orderBy('id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(TaskLabel::class, 'board_id')->orderBy('position')->orderBy('id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_board_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TaskActivityLog::class, 'board_id')->orderByDesc('created_at');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function isInternalTalentsBoard(): bool
    {
        return $this->company_id === null;
    }
}
