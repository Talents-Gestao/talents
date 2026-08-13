<?php

namespace App\Models;

use App\Enums\LandingInterestSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingInterestSubmission extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'message',
        'admin_notes',
        'source',
        'created_by',
        'mail_sent_at',
        'mail_error',
    ];

    protected function casts(): array
    {
        return [
            'mail_sent_at' => 'datetime',
            'source' => LandingInterestSource::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceEnum(): LandingInterestSource
    {
        if ($this->source instanceof LandingInterestSource) {
            return $this->source;
        }

        return LandingInterestSource::tryFrom((string) $this->source) ?? LandingInterestSource::Site;
    }
}
