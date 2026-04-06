<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhidAuditLog extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'endpoint',
        'http_status',
        'request_summary',
        'response_summary',
    ];

    protected function casts(): array
    {
        return [
            'request_summary' => 'array',
            'response_summary' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
