<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\SafelyDecryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Gravação de uma sessão de feedback: transcrição exportada do Plaud. Guarda o texto original extraído
 * (imutável, criptografado) e o hash do arquivo; o arquivo enviado não é armazenado.
 */
class FeedbackRecording extends Model
{
    use SafelyDecryptsAttributes;
    use SoftDeletes;

    public const FORMATS = ['txt', 'srt', 'docx', 'pdf'];

    /** Estado do processamento por IA: enviado → processando → pronto | falhou. */
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /** full: limpeza + relatório; report: só um novo relatório a partir da transcrição limpa atual. */
    public const MODE_FULL = 'full';

    public const MODE_REPORT = 'report';

    public const STAGE_CLEANING = 'cleaning';

    public const STAGE_REPORT = 'report';

    public const ROLE_LEADER = 'leader';

    public const ROLE_EMPLOYEE = 'employee';

    public const ROLE_OTHER = 'other';

    public const ROLES = [self::ROLE_LEADER, self::ROLE_EMPLOYEE, self::ROLE_OTHER];

    protected $fillable = [
        'company_id',
        'feedback_session_id',
        'source_format',
        'original_filename',
        'file_sha256',
        'file_size_bytes',
        'original_text',
        'original_chars',
        'has_speaker_labels',
        'has_timestamps',
        'uploaded_by_user_id',
        'consent_by_user_id',
        'consent_confirmed_at',
        'speaker_map',
        'speakers_mapped_by_user_id',
        'speakers_mapped_at',
        'processing_status',
        'processing_mode',
        'processing_stage',
        'processing_chunks_done',
        'processing_chunks_total',
        'processing_error',
        'processing_attempts',
        'processing_requested_by_user_id',
        'processing_started_at',
        'processed_at',
        'clean_text',
        'clean_generated_at',
        'clean_revision',
        'clean_edited_by_user_id',
        'clean_edited_at',
    ];

    protected function casts(): array
    {
        return [
            'original_filename' => 'encrypted',
            'original_text' => 'encrypted',
            'file_size_bytes' => 'integer',
            'original_chars' => 'integer',
            'has_speaker_labels' => 'boolean',
            'has_timestamps' => 'boolean',
            'consent_confirmed_at' => 'datetime',
            'speaker_map' => 'array',
            'speakers_mapped_at' => 'datetime',
            'processing_chunks_done' => 'integer',
            'processing_chunks_total' => 'integer',
            'processing_attempts' => 'integer',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'clean_text' => 'encrypted',
            'clean_generated_at' => 'datetime',
            'clean_revision' => 'integer',
            'clean_edited_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FeedbackSession::class, 'feedback_session_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function consentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_by_user_id');
    }

    public function speakersMappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'speakers_mapped_by_user_id');
    }

    public function cleanEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clean_edited_by_user_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(FeedbackRecordingReport::class);
    }

    public function currentReport(): ?FeedbackRecordingReport
    {
        return $this->reports()->where('is_current', true)->latest('id')->first();
    }

    public function hasCleanText(): bool
    {
        return $this->hasStoredEncrypted('clean_text');
    }

    /** Transcrição limpa; null se ainda não gerada ou se a chave de criptografia mudou. */
    public function safeCleanText(): ?string
    {
        $text = $this->safeDecrypt('clean_text');

        return ($text === null || $text === '') ? null : $text;
    }

    public function statusLabel(): string
    {
        return match ($this->processing_status) {
            self::STATUS_PROCESSING => 'Processando',
            self::STATUS_READY => 'Pronto',
            self::STATUS_FAILED => 'Falhou',
            default => 'Enviado',
        };
    }

    /** Texto original; placeholder se a chave de criptografia mudou. */
    public function safeOriginalText(): string
    {
        return $this->safeDecrypt('original_text') ?? '[Conteúdo indisponível: chave de criptografia alterada.]';
    }

    public function safeOriginalFilename(): string
    {
        return $this->safeDecrypt('original_filename') ?? '—';
    }

    public function formatLabel(): string
    {
        return match ($this->source_format) {
            'txt' => 'Texto (TXT)',
            'srt' => 'Legenda com tempo (SRT)',
            'docx' => 'Word (DOCX)',
            'pdf' => 'PDF',
            default => strtoupper((string) $this->source_format),
        };
    }
}
