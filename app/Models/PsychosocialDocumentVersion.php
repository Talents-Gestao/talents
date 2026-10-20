<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Versão congelada de um documento novo. Só inserção.
 *
 * A validated reutiliza o snapshot e o snapshot_sha256 da released de origem; só o content_sha256 muda,
 * porque o conteúdo passa a incluir o bloco de validação. Depois de gravada, apenas o estado do PDF evolui.
 */
class PsychosocialDocumentVersion extends Model
{
    public const UPDATED_AT = null;

    public const KIND_RELEASED = 'released';

    public const KIND_VALIDATED = 'validated';

    public const KINDS = [self::KIND_RELEASED, self::KIND_VALIDATED];

    public const SEAL_PENDING_VALIDATION = 'pending_validation';

    public const SEAL_VALIDATED = 'validated';

    public const SEAL_BY_KIND = [
        self::KIND_RELEASED => self::SEAL_PENDING_VALIDATION,
        self::KIND_VALIDATED => self::SEAL_VALIDATED,
    ];

    public const PDF_QUEUED = 'queued';

    public const PDF_READY = 'ready';

    public const PDF_FAILED = 'failed';

    public const PDF_STATUSES = [self::PDF_QUEUED, self::PDF_READY, self::PDF_FAILED];

    public const PDF_MUTABLE_FIELDS = ['pdf_status', 'pdf_path', 'pdf_sha256', 'pdf_error'];

    protected $fillable = [
        'document_id',
        'version_number',
        'public_identifier',
        'kind',
        'parent_version_id',
        'method_version_id',
        'instrument_version_id',
        'snapshot_schema',
        'snapshot',
        'snapshot_sha256',
        'validation_block',
        'content_sha256',
        'seal',
        'pdf_status',
        'pdf_path',
        'pdf_sha256',
        'pdf_error',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'snapshot' => 'array',
            'validation_block' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PsychosocialDocumentVersion $version): void {
            $version->assertKindAndSeal();
            $version->assertPdfStatus();
            $version->assertCampaignVersions();

            if ($version->kind === self::KIND_VALIDATED) {
                $version->assertReusesReleasedSnapshot();
            }
        });

        static::updating(function (PsychosocialDocumentVersion $version): void {
            $frozen = array_diff(array_keys($version->getDirty()), self::PDF_MUTABLE_FIELDS);
            if ($frozen !== []) {
                throw new LogicException('Versão de documento não pode ser alterada: '.implode(', ', $frozen).'.');
            }

            $version->assertPdfStatus();
        });

        static::deleting(function (): never {
            throw new LogicException('Versões de documento não podem ser apagadas.');
        });
    }

    private function assertKindAndSeal(): void
    {
        if (! in_array($this->kind, self::KINDS, true)) {
            throw new PsychosocialDocumentException("Tipo de versão inválido: «{$this->kind}».");
        }

        if ($this->seal !== self::SEAL_BY_KIND[$this->kind]) {
            throw new PsychosocialDocumentException("A versão {$this->kind} exige o selo «".self::SEAL_BY_KIND[$this->kind].'».');
        }

        $isValidated = $this->kind === self::KIND_VALIDATED;

        if ($isValidated !== ($this->parent_version_id !== null)) {
            throw new PsychosocialDocumentException('Só a versão validated aponta para a released de origem, e ela é obrigatória.');
        }

        if ($isValidated !== ($this->validation_block !== null)) {
            throw new PsychosocialDocumentException('O bloco de validação existe só na versão validated, e nela é obrigatório.');
        }
    }

    private function assertPdfStatus(): void
    {
        if ($this->pdf_status !== null && ! in_array($this->pdf_status, self::PDF_STATUSES, true)) {
            throw new PsychosocialDocumentException("Estado de PDF inválido: «{$this->pdf_status}».");
        }
    }

    private function assertCampaignVersions(): void
    {
        $survey = PsychosocialDocument::query()->findOrFail($this->document_id)->survey()->firstOrFail();

        if ((int) $this->method_version_id !== (int) $survey->method_version_id
            || (int) $this->instrument_version_id !== (int) $survey->instrument_version_id) {
            throw new PsychosocialDocumentException('Método e instrumento da versão precisam ser os da campanha.');
        }
    }

    private function assertReusesReleasedSnapshot(): void
    {
        $parent = self::query()->find($this->parent_version_id);

        if ($parent === null || $parent->kind !== self::KIND_RELEASED || (int) $parent->document_id !== (int) $this->document_id) {
            throw new PsychosocialDocumentException('A versão validated precisa apontar para uma released do mesmo documento.');
        }

        if ($this->snapshot_sha256 !== $parent->snapshot_sha256 || $this->snapshot !== $parent->snapshot
            || $this->snapshot_schema !== $parent->snapshot_schema) {
            throw new PsychosocialDocumentException('A versão validated reutiliza o snapshot da released de origem.');
        }

        if ($this->content_sha256 === $parent->content_sha256) {
            throw new PsychosocialDocumentException('O conteúdo da versão validated inclui o bloco de validação e precisa de hash próprio.');
        }
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PsychosocialDocument::class, 'document_id');
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    public function methodVersion(): BelongsTo
    {
        return $this->belongsTo(SurveyMethodVersion::class, 'method_version_id');
    }

    public function instrumentVersion(): BelongsTo
    {
        return $this->belongsTo(SurveyInstrumentVersion::class, 'instrument_version_id');
    }

    public function validation(): HasOne
    {
        return $this->hasOne(PsychosocialDocumentValidation::class, 'version_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PsychosocialDocumentAttachment::class, 'version_id');
    }
}
