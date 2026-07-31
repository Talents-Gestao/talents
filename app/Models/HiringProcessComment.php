<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiringProcessComment extends Model
{
    protected $fillable = [
        'hiring_process_id',
        'user_id',
        'body',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(HiringProcess::class, 'hiring_process_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{id: int, body: string, author_name: string, author_role: string, created_at: string|null}
     */
    public function toFrontend(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author_name' => $this->user?->name ?? '—',
            'author_role' => $this->user?->role === UserRole::SuperAdmin ? 'talents' : 'company',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
