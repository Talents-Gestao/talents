<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\SurveyTemplateLockedException;
use App\Models\Survey;
use App\Models\SurveyInstrumentVersion;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueio de edição destrutiva: depois que a campanha começa a coletar (ou, no instrumento V11,
 * depois que ela é aberta), texto, dimensão, polaridade, escala, peso e ordem não mudam no lugar.
 * A alteração vira nova versão (SurveyTemplateVersioningService::createFork).
 */
final class SurveyTemplateLock
{
    public static function isLocked(int $templateId): bool
    {
        // Campanha com qualquer resposta (inclusive arquivada ou com soft delete).
        $hasResponses = Survey::withTrashed()
            ->where('survey_template_id', $templateId)
            ->whereHas('responses')
            ->exists();

        if ($hasResponses) {
            return true;
        }

        // Instrumento V11: campanha já aberta (não rascunho) trava, mesmo sem respostas.
        $v11Version = SurveyInstrumentVersion::query()
            ->where('survey_template_id', $templateId)
            ->where('instrument_code', Nr1InstrumentMatrix::CODE_TALENTS39)
            ->exists();

        if (! $v11Version) {
            return false;
        }

        return Survey::withTrashed()
            ->where('survey_template_id', $templateId)
            ->where('status', '!=', 'draft')
            ->where('starts_at', '<=', now())
            ->exists();
    }

    public static function templateIdForSection(int $sectionId): ?int
    {
        $id = DB::table('survey_template_sections')->where('id', $sectionId)->value('survey_template_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @throws SurveyTemplateLockedException
     */
    public static function assertEditable(?int $templateId): void
    {
        if ($templateId !== null && self::isLocked($templateId)) {
            throw new SurveyTemplateLockedException(
                'Este instrumento já está em uso por uma campanha iniciada. Crie uma nova versão para alterar texto, dimensão, polaridade, escala ou ordem.'
            );
        }
    }
}
