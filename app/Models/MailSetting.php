<?php

namespace App\Models;

use App\Concerns\SafelyDecryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class MailSetting extends Model
{
    use SafelyDecryptsAttributes;

    protected $fillable = [
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'is_enabled',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_enabled' => 'boolean',
            'port' => 'integer',
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
     * Senha SMTP descriptografada; null se vazia ou ilegível com a APP_KEY atual.
     */
    public function safePassword(): ?string
    {
        $password = $this->safeDecrypt('password');

        return ($password !== null && $password !== '') ? $password : null;
    }

    public static function applyToRuntimeConfig(): void
    {
        if (! Schema::hasTable('mail_settings')) {
            return;
        }

        $row = static::current();
        if (! $row || ! $row->is_enabled || empty($row->host)) {
            return;
        }

        $password = $row->safePassword();
        if ($password === null) {
            return;
        }

        $encryption = $row->encryption;
        if ($encryption === '') {
            $encryption = null;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $row->host,
            'mail.mailers.smtp.port' => (int) $row->port,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username' => $row->username,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.timeout' => null,
            'mail.from.address' => $row->from_address ?: config('mail.from.address'),
            'mail.from.name' => $row->from_name ?: config('mail.from.name'),
        ]);
    }
}
