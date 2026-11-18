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
        'openai_transcription_api_key',
        'model',
        'is_enabled',
        'max_tokens',
        'temperature',
        'updated_by',
        'feedback_approved_provider',
        'feedback_approved_by',
        'feedback_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'openai_transcription_api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'max_tokens' => 'integer',
            'temperature' => 'float',
            'feedback_approved_at' => 'datetime',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function feedbackApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feedback_approved_by');
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

    public function hasStoredTranscriptionApiKey(): bool
    {
        return $this->hasStoredEncrypted('openai_transcription_api_key');
    }

    /**
     * Chave OpenAI para Whisper. Se provider=openai, reutiliza api_key; senão usa openai_transcription_api_key.
     */
    public function safeTranscriptionApiKey(): ?string
    {
        if ($this->provider === 'openai') {
            return $this->safeApiKey();
        }

        $key = $this->safeDecrypt('openai_transcription_api_key');

        return ($key !== null && $key !== '') ? $key : null;
    }
}
