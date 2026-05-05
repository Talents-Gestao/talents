<?php

namespace App\Models;

use App\Concerns\SafelyDecryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolidesSetting extends Model
{
    use SafelyDecryptsAttributes;

    protected $fillable = [
        'base_url',
        'locale',
        'api_token',
        'is_enabled',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'is_enabled' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function safeApiToken(): ?string
    {
        $token = $this->safeDecrypt('api_token');

        return ($token !== null && $token !== '') ? $token : null;
    }

    public function effectiveBaseUrl(): string
    {
        $base = rtrim((string) ($this->base_url ?: config('solides.base_url')), '/');
        $locale = $this->locale ?: config('solides.locale', 'pt-BR');

        return $base.'/'.trim((string) $locale, '/').'/api/v1';
    }
}
