<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PsychosocialDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabeçalho de um documento novo da campanha. Um por tipo; as versões ficam em psychosocial_document_versions.
 */
class PsychosocialDocument extends Model
{
    public const TYPE_EXECUTIVE_BRIEF = 'executive_brief';

    public const TYPE_PGR_SUBSIDY = 'pgr_subsidy';

    public const TYPE_EVIDENCE_DOSSIER = 'evidence_dossier';

    /** Relatório técnico + plano de ação: o segundo documento final do fluxo V11 (Caderno §12). */
    public const TYPE_TECHNICAL_REPORT = 'technical_report';

    public const TYPES = [
        self::TYPE_EXECUTIVE_BRIEF,
        self::TYPE_PGR_SUBSIDY,
        self::TYPE_EVIDENCE_DOSSIER,
        self::TYPE_TECHNICAL_REPORT,
    ];

    protected $fillable = [
        'survey_id',
        'company_id',
        'type',
        'current_version_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (PsychosocialDocument $document): void {
            if (! in_array($document->type, self::TYPES, true)) {
                throw new PsychosocialDocumentException("Tipo de documento inválido: «{$document->type}».");
            }

            if ($document->exists && $document->isDirty(['survey_id', 'company_id', 'type'])) {
                throw new PsychosocialDocumentException('Campanha, empresa e tipo do documento não mudam depois de gravados.');
            }

            if ($document->current_version_id !== null && $document->isDirty('current_version_id')) {
                $belongs = PsychosocialDocumentVersion::query()
                    ->whereKey($document->current_version_id)
                    ->where('document_id', $document->id)
                    ->exists();

                if (! $belongs) {
                    throw new PsychosocialDocumentException('A versão atual precisa pertencer ao próprio documento.');
                }
            }
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PsychosocialDocumentVersion::class, 'document_id')->orderBy('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(PsychosocialDocumentVersion::class, 'current_version_id');
    }
}
