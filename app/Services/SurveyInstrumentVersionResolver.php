<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SurveyInstrumentVersion;
use App\Support\Nr1InstrumentMatrix;
use Illuminate\Support\Facades\DB;

/**
 * Congela a matriz do template no início da campanha. Reutiliza a versão existente
 * quando matriz (hash) e instrumento coincidem, preservando o estado de auditoria dela.
 */
final class SurveyInstrumentVersionResolver
{
    public function resolveForTemplate(int $templateId): SurveyInstrumentVersion
    {
        $matrix = Nr1InstrumentMatrix::forTemplate($templateId);
        $matrixHash = Nr1InstrumentMatrix::hash($matrix);
        $title = (string) DB::table('survey_templates')->where('id', $templateId)->value('title');
        $code = Nr1InstrumentMatrix::instrumentCodeForTitle($title);

        $existing = SurveyInstrumentVersion::query()
            ->where('survey_template_id', $templateId)
            ->where('item_matrix_sha256', $matrixHash)
            ->where('instrument_code', $code)
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $versionCount = SurveyInstrumentVersion::query()->where('survey_template_id', $templateId)->count();

        return SurveyInstrumentVersion::query()->create([
            'survey_template_id' => $templateId,
            'instrument_code' => $code,
            'version_label' => 'v'.($versionCount + 1),
            'source' => Nr1InstrumentMatrix::SOURCE_CAMPAIGN_START,
            'item_matrix' => $matrix,
            'item_matrix_sha256' => $matrixHash,
            'polarity_audit_status' => Nr1InstrumentMatrix::initialAuditStatus($code),
            'frozen_at' => now(),
        ]);
    }
}
