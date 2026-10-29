<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Models\ActionPlan;
use App\Models\Survey;
use App\Models\SurveyResult;
use App\Support\Nr1GroupPrivacy;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;

/**
 * Fatos da tela «Campanha NR-1» comuns à V11 e ao modelo anterior: etapas, período, grupo mínimo e publicação, todos
 * lidos do que já está gravado (datas e status da campanha, resultados calculados, publicação). Só leitura.
 */
final class Nr1ShellCampaignFacts
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Survey $survey): array
    {
        $reason = $survey->publicParticipationClosureReason();
        $hasResults = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->exists();
        $publication = self::publication($survey);

        $collection = match ($reason) {
            null => 'current',
            'not_started' => 'todo',
            'ended' => 'done',
            default => $hasResults ? 'done' : 'todo',
        };

        return [
            'steps' => [
                ['key' => 'config', 'label' => 'Configuração', 'state' => 'done'],
                ['key' => 'coleta', 'label' => 'Coleta', 'state' => $collection],
                ['key' => 'resultados', 'label' => 'Resultados', 'state' => $hasResults ? ($collection === 'current' ? 'current' : 'done') : 'todo'],
                ['key' => 'publicacao', 'label' => 'Publicação', 'state' => $publication['published'] ? 'done' : ($publication['required'] ? ($hasResults && $collection === 'done' ? 'current' : 'todo') : 'na')],
            ],
            'period' => [
                'starts' => $survey->starts_at?->format('d/m/Y'),
                'ends' => $survey->ends_at?->format('d/m/Y'),
            ],
            'minGroup' => Nr1GroupPrivacy::minFor($survey),
            'publication' => $publication,
        ];
    }

    /**
     * V11: a publicação da campanha (gate). Modelo anterior: a publicação do Laudo (parecer e plano) para a empresa.
     *
     * @return array{required: bool, published: bool, at: string|null, label: string}
     */
    private static function publication(Survey $survey): array
    {
        if (Nr1ShellLegacy::isLegacy($survey)) {
            $at = ActionPlan::query()->where('survey_id', $survey->id)->where('company_id', $survey->company_id)->value('admin_published_at');
            $at = $at !== null ? Carbon::parse($at) : null;

            return [
                'required' => true,
                'published' => $at !== null,
                'at' => $at?->format('d/m/Y H:i'),
                'label' => $at !== null ? 'Parecer e plano publicados para a empresa' : 'Parecer e plano ainda não publicados',
            ];
        }

        if (! $survey->requires_publication) {
            return ['required' => false, 'published' => false, 'at' => null, 'label' => 'Resultados liberados sem publicação'];
        }

        return [
            'required' => true,
            'published' => $survey->published_at !== null,
            'at' => $survey->published_at?->format('d/m/Y H:i'),
            'label' => $survey->published_at !== null ? 'Resultados publicados para a empresa' : 'A empresa vê os resultados depois da publicação',
        ];
    }

    /** QR do link público em SVG (data URI), o mesmo desenho da rota client.surveys.audiences.qr. */
    public static function qr(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url));
    }
}
