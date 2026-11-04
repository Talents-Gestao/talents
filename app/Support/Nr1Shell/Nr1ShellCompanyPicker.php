<?php

declare(strict_types=1);

namespace App\Support\Nr1Shell;

use App\Models\Company;
use App\Models\PsychosocialReviewRound;
use App\Models\Survey;
use App\Models\SurveyMethodVersion;
use Illuminate\Support\Carbon;

/**
 * Cartões da escolha de empresa do painel NR-1 (Talents). Só identificação da campanha: nome e marca da empresa,
 * estado, método, quantidade e período das campanhas. Nenhum resultado, índice, resposta, setor ou dado de colaborador.
 * Consultas fixas (empresas com as campanhas em eager loading, a última rodada de revisão em lote e o id do método V11),
 * sem uma consulta por empresa ou por campanha.
 */
final class Nr1ShellCompanyPicker
{
    private const SURVEY_COLUMNS = ['id', 'company_id', 'title', 'starts_at', 'ends_at', 'status', 'requires_publication', 'published_at', 'method_version_id', 'created_at'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function cards(): array
    {
        $companies = Company::query()
            ->whereHas('surveys')
            ->with(['surveys' => fn ($query) => $query->select(self::SURVEY_COLUMNS)->orderByDesc('starts_at')->orderByDesc('id')])
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'brand_primary_color']);

        $v11Id = (int) SurveyMethodVersion::idForCode(SurveyMethodVersion::CODE_TALENTS39_V1);
        $surveys = $companies->flatMap(fn (Company $company) => $company->surveys);
        $pendingReview = $surveys->filter(fn (Survey $s) => (int) $s->method_version_id === $v11Id && $s->requires_publication && $s->published_at === null)->pluck('id');
        $rounds = $pendingReview->isEmpty() ? collect() : PsychosocialReviewRound::query()
            ->whereIn('survey_id', $pendingReview->all())
            ->orderByDesc('round_number')
            ->get(['survey_id', 'status', 'round_number'])
            ->unique('survey_id')
            ->keyBy('survey_id');

        $now = Carbon::now();

        return $companies->map(function (Company $company) use ($v11Id, $rounds, $now) {
            $campaigns = $company->surveys->map(function (Survey $survey) use ($v11Id, $rounds, $now) {
                $v11 = (int) $survey->method_version_id === $v11Id;
                $status = Nr1ShellCampaignStatus::badge($survey, $v11, $rounds->get($survey->id)?->status);

                return [
                    'id' => (int) $survey->id,
                    'title' => (string) $survey->title,
                    'year' => ($survey->starts_at ?? $survey->created_at)?->year,
                    'period' => self::period($survey),
                    'method' => $v11 ? 'v11' : 'legacy',
                    'status' => $status,
                    'collecting' => $survey->status === 'active' && ($survey->ends_at === null || $survey->ends_at->greaterThanOrEqualTo($now)),
                    'href' => route('nr1.visao-geral', ['empresa' => $survey->company_id, 'campanha' => $survey->id], false),
                ];
            })->values();

            $latest = $campaigns->first();

            return [
                'id' => (int) $company->id,
                'name' => (string) $company->name,
                'brandColor' => self::brandColor($company->brand_primary_color),
                'campaigns' => $campaigns->count(),
                'href' => route('nr1.visao-geral', ['empresa' => $company->id], false),
                'latest' => $latest === null ? null : collect($latest)->except('collecting')->all(),
                'others' => $campaigns->slice(1)->map(fn (array $c) => ['id' => $c['id'], 'title' => $c['title'], 'year' => $c['year'], 'method' => $c['method'], 'href' => $c['href']])->values()->all(),
                'flags' => [
                    'active' => $campaigns->contains('collecting', true),
                    'review' => $campaigns->contains(fn (array $c) => $c['status']['label'] === 'Em revisão'),
                    'published' => $campaigns->contains(fn (array $c) => $c['status']['label'] === 'Publicado'),
                    'legacy' => $campaigns->contains('method', 'legacy'),
                    'v11' => $campaigns->contains('method', 'v11'),
                ],
            ];
        })->values()->all();
    }

    private static function period(Survey $survey): ?string
    {
        $start = $survey->starts_at?->timezone('America/Sao_Paulo');
        $end = $survey->ends_at?->timezone('America/Sao_Paulo');
        if ($start === null) {
            return null;
        }

        return $end === null ? 'desde '.$start->format('d/m/Y') : $start->format('d/m/Y').' – '.$end->format('d/m/Y');
    }

    /** Cor da marca da empresa, só se for um hex válido (o front calcula o contraste). */
    private static function brandColor(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#?[0-9a-fA-F]{6}$/', $value) === 1 ? '#'.ltrim(strtolower($value), '#') : null;
    }
}
