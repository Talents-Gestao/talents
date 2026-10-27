<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Código de recuperação de uso único. Só o hash existe aqui; o código em claro é mostrado uma única vez.
 */
class UserMfaRecoveryCode extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'code_hash', 'used_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    public static function hashFor(string $code): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
