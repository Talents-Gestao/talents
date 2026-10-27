<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Segredo TOTP do usuário, cifrado com a APP_KEY. Se não decifrar (chave trocada, dado corrompido), o segredo é tratado
 * como inexistente: a verificação falha e a pessoa precisa se inscrever de novo. Nunca libera o acesso.
 */
class UserMfaCredential extends Model
{
    protected $fillable = ['user_id', 'secret', 'confirmed_at', 'last_used_timestamp'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'last_used_timestamp' => 'integer',
        ];
    }

    /** Guarda o segredo já cifrado. */
    public function setPlainSecret(string $secret): void
    {
        $this->attributes['secret'] = Crypt::encryptString($secret);
    }

    /** Segredo em claro, ou null se não decifrar. */
    public function plainSecret(): ?string
    {
        try {
            $secret = Crypt::decryptString((string) $this->attributes['secret']);
        } catch (DecryptException) {
            return null;
        }

        return $secret !== '' ? $secret : null;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
