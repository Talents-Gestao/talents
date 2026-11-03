<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\Nr1AiAnalyzer;
use App\Support\Nr1GroupPrivacy;
use App\Support\TechnicalOpinionDisclaimerStripper;
use Illuminate\Support\Facades\Cache;

/**
 * Mia no painel NR-1: estado da análise para os cartões de Resultados e Documentos. A geração segue nas rotas e no job
 * de sempre (limite por hora, fila, payload só agregado).
 */
final class Nr1ShellAi
{
    /**
     * Análise guiada em Resultados. Null sem Mia configurada, sem resultados ou abaixo do grupo mínimo.
     *
     * @return array<string, mixed>|null
     */
    public static function payload(Survey $survey, bool $forTalents, User $user): ?array
    {
        if (! self::enabled() || ! SurveyResult::query()->where('survey_id', $survey->id)->exists() || ! Nr1GroupPrivacy::hasMinimumSample($survey)) {
            return null;
        }

        return self::state($survey, Nr1AiAnalyzer::TYPE_NR1_GUIDANCE) + [
            'postUrl' => $forTalents
                ? route('admin.companies.surveys.ai-analysis', [$survey->company_id, $survey->id])
                : route('client.surveys.ai-analysis', $survey->id),
            'canRequest' => $forTalents || $user->canAccess(PermissionModule::Pesquisas, PermissionAction::Edit),
        ];
    }

    /**
     * Sugestão de parecer da Mia, só para a Talents.
     *
     * @return array<string, mixed>|null
     */
    public static function technicalOpinion(Survey $survey, Company $company): ?array
    {
        if (! self::enabled() || ! Nr1GroupPrivacy::hasMinimumSample($survey)) {
            return null;
        }

        $state = self::state($survey, Nr1AiAnalyzer::TYPE_NR1_TECHNICAL_OPINION);
        $state['content'] = TechnicalOpinionDisclaimerStripper::strip($state['content']);

        return $state + ['postUrl' => route('admin.companies.surveys.technical-opinion', [$company->id, $survey->id])];
    }

    /** @return array{content: ?string, pending: bool} */
    private static function state(Survey $survey, string $type): array
    {
        return [
            'content' => AiAnalysis::query()->where('survey_id', $survey->id)->where('type', $type)->latest()->orderByDesc('id')->value('content'),
            'pending' => Cache::has(Nr1AiAnalyzer::pendingCacheKey($survey->id, $type)),
        ];
    }

    private static function enabled(): bool
    {
        $setting = AiSetting::current();

        return $setting !== null && $setting->is_enabled && $setting->safeApiKey() !== null;
    }
}
