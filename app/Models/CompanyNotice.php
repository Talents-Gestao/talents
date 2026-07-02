<?php

namespace App\Models;

use App\Enums\CompanyNoticeEventKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyNotice extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'body',
        'source_type',
        'source_id',
        'event_kind',
        'published_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'event_kind' => CompanyNoticeEventKind::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(CompanyNoticeRead::class);
    }

    public function isReadByUser(int $userId): bool
    {
        return $this->reads()->where('user_id', $userId)->exists();
    }
}
