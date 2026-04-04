<?php

namespace App\Models;

use App\Concerns\SafelyDecryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSetting extends Model
{
    use SafelyDecryptsAttributes;

    protected $fillable = [
        'provider',
        'api_key',
        'model',
        'is_enabled',
        'max_tokens',
        'temperature',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'max_tokens' => 'integer',
            'temperature' => 'float',
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

    /**
     * Há valor persistido no banco (não descriptografa).
     */
    public function hasStoredApiKey(): bool
    {
        return $this->hasStoredEncrypted('api_key');
    }

    /**
     * Chave descriptografada para uso em runtime; null se vazia ou se o ciphertext não bate com APP_KEY.
     */
    public function safeApiKey(): ?string
    {
        $key = $this->safeDecrypt('api_key');

        return ($key !== null && $key !== '') ? $key : null;
    }
}
