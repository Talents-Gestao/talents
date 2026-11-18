<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\SafelyDecryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Relatório estruturado de uma gravação de feedback, gerado por IA. Cada geração é uma linha nova (nunca
 * sobrescrita); a mais recente fica com is_current. O JSON validado fica criptografado; a versão legível é
 * montada a partir dele (FeedbackReportPresenter). Apoio à conversa: não alimenta termômetro, 9-box nem respostas.
 */
class FeedbackRecordingReport extends Model
{
    use SafelyDecryptsAttributes;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'feedback_session_id',
        'feedback_recording_id',
        'report_json',
        'prompt_version',
        'schema_version',
        'provider',
        'model',
        'clean_revision',
        'generated_by_user_id',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'report_json' => 'encrypted:array',
            'clean_revision' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(FeedbackRecording::class, 'feedback_recording_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /** @return array<string, mixed>|null */
    public function safeReport(): ?array
    {
        $value = $this->safeDecrypt('report_json');

        return is_array($value) ? $value : null;
    }
}
