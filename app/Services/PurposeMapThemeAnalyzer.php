<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiSetting;
use App\Models\PurposeMapCampaign;
use App\Models\PurposeMapInsight;
use App\Models\PurposeMapResponse;
use App\Models\PurposeMapTheme;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Extrai temas das respostas (IA quando disponível; fallback por frequência de palavras).
 */
final class PurposeMapThemeAnalyzer
{
    public const PENDING_CACHE_PREFIX = 'purpose_map_themes_pending:';

    public static function pendingCacheKey(int $campaignId): string
    {
        return self::PENDING_CACHE_PREFIX.$campaignId;
    }

    public function analyze(PurposeMapCampaign $campaign): void
    {
        $responses = PurposeMapResponse::query()
            ->where('purpose_map_campaign_id', $campaign->id)
            ->get();

        PurposeMapTheme::query()->where('purpose_map_campaign_id', $campaign->id)->delete();
        PurposeMapInsight::query()->where('purpose_map_campaign_id', $campaign->id)->delete();

        if ($responses->isEmpty()) {
            $campaign->forceFill(['themes_analyzed_at' => now()])->save();

            return;
        }

        $questionKeys = array_keys(config('purpose_map.questions', []));
        $usedAi = false;

        $setting = AiSetting::current();
        if ($setting && $setting->is_enabled && $setting->safeApiKey() !== null) {
            try {
                $usedAi = $this->analyzeWithAi($campaign, $responses, $questionKeys, $setting);
            } catch (\Throwable $e) {
                Log::warning('PurposeMapThemeAnalyzer AI failed; using fallback.', [
                    'campaign_id' => $campaign->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (! $usedAi) {
            $this->analyzeWithWordFrequency($campaign, $responses, $questionKeys);
        }

        $this->buildRuleInsights($campaign, $responses);
        $campaign->forceFill(['themes_analyzed_at' => now()])->save();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PurposeMapResponse>  $responses
     * @param  list<string>  $questionKeys
     */
    private function analyzeWithAi(PurposeMapCampaign $campaign, $responses, array $questionKeys, AiSetting $setting): bool
    {
        $payload = [];
        foreach ($responses as $r) {
            $row = ['sector' => $r->sector];
            foreach ($questionKeys as $key) {
                $row[$key] = Str::limit((string) $r->{$key}, 400);
            }
            $payload[] = $row;
        }

        $system = 'Você analisa respostas anônimas do Mapa de Propósito (gestão de pessoas). '
            .'Retorne APENAS JSON válido no formato: '
            .'{"themes":[{"question_key":"why_work|dream|pain_self|pain_sector|pain_company","sector":null|"NOME_SETOR","theme":"string","count":n,"sample_excerpts":["..."]}],'
            .'"insights":[{"kind":"alignment|gap|concentration","message":"string em português do Brasil"}]} '
            .'Agrupe temas curtos (2–5 palavras). sector null = visão geral. Máximo 8 temas por question_key na visão geral.';

        $user = 'Respostas (JSON):\n'.json_encode($payload, JSON_UNESCAPED_UNICODE);

        $base = rtrim((string) ($setting->base_url ?: 'https://api.openai.com/v1'), '/');
        $model = $setting->model ?: 'gpt-4o-mini';

        $response = Http::withToken($setting->safeApiKey())
            ->timeout(120)
            ->post($base.'/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if (! $response->successful()) {
            return false;
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            return false;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return false;
        }

        foreach ($decoded['themes'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qKey = (string) ($item['question_key'] ?? '');
            if (! in_array($qKey, $questionKeys, true)) {
                continue;
            }
            $theme = trim((string) ($item['theme'] ?? ''));
            if ($theme === '') {
                continue;
            }
            PurposeMapTheme::query()->create([
                'purpose_map_campaign_id' => $campaign->id,
                'question_key' => $qKey,
                'sector' => isset($item['sector']) && is_string($item['sector']) && $item['sector'] !== ''
                    ? $item['sector']
                    : null,
                'theme' => Str::limit($theme, 255, ''),
                'count' => max(1, (int) ($item['count'] ?? 1)),
                'sample_excerpts' => array_values(array_slice(
                    array_map(static fn ($e) => Str::limit((string) $e, 160), (array) ($item['sample_excerpts'] ?? [])),
                    0,
                    3
                )),
            ]);
        }

        $sort = 0;
        foreach ($decoded['insights'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $message = trim((string) ($item['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            PurposeMapInsight::query()->create([
                'purpose_map_campaign_id' => $campaign->id,
                'kind' => Str::limit((string) ($item['kind'] ?? 'general'), 64, ''),
                'message' => $message,
                'sort_order' => $sort++,
            ]);
        }

        return PurposeMapTheme::query()->where('purpose_map_campaign_id', $campaign->id)->exists();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PurposeMapResponse>  $responses
     * @param  list<string>  $questionKeys
     */
    private function analyzeWithWordFrequency(PurposeMapCampaign $campaign, $responses, array $questionKeys): void
    {
        $stop = $this->stopWords();

        foreach ($questionKeys as $key) {
            $this->persistFrequencyThemes($campaign, $responses, $key, null, $stop);
            foreach ($responses->groupBy('sector') as $sector => $group) {
                $this->persistFrequencyThemes($campaign, $group, $key, (string) $sector, $stop);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PurposeMapResponse>  $responses
     * @param  array<string, true>  $stop
     */
    private function persistFrequencyThemes(PurposeMapCampaign $campaign, $responses, string $key, ?string $sector, array $stop): void
    {
        $freq = [];
        $excerpts = [];

        foreach ($responses as $r) {
            $text = (string) $r->{$key};
            $tokens = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text)) ?: [];
            $seen = [];
            foreach ($tokens as $token) {
                if (mb_strlen($token) < 4 || isset($stop[$token])) {
                    continue;
                }
                if (isset($seen[$token])) {
                    continue;
                }
                $seen[$token] = true;
                $freq[$token] = ($freq[$token] ?? 0) + 1;
                if (! isset($excerpts[$token])) {
                    $excerpts[$token] = [];
                }
                if (count($excerpts[$token]) < 2) {
                    $excerpts[$token][] = Str::limit(trim($text), 140);
                }
            }
        }

        arsort($freq);
        $i = 0;
        foreach ($freq as $word => $count) {
            if ($i >= 8 || $count < 1) {
                break;
            }
            PurposeMapTheme::query()->create([
                'purpose_map_campaign_id' => $campaign->id,
                'question_key' => $key,
                'sector' => $sector,
                'theme' => Str::title($word),
                'count' => $count,
                'sample_excerpts' => $excerpts[$word] ?? [],
            ]);
            $i++;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PurposeMapResponse>  $responses
     */
    private function buildRuleInsights(PurposeMapCampaign $campaign, $responses): void
    {
        if (PurposeMapInsight::query()->where('purpose_map_campaign_id', $campaign->id)->exists()) {
            return;
        }

        $catalog = config('purpose_map.sectors', []);
        $covered = $responses->pluck('sector')->unique()->all();
        $missing = array_values(array_diff($catalog, $covered));

        $sort = 0;
        if ($missing !== []) {
            PurposeMapInsight::query()->create([
                'purpose_map_campaign_id' => $campaign->id,
                'kind' => 'gap',
                'message' => 'Setores ainda sem resposta: '.implode(', ', array_slice($missing, 0, 6))
                    .(count($missing) > 6 ? '…' : '').'.',
                'sort_order' => $sort++,
            ]);
        }

        $top = $responses->groupBy('sector')->map->count()->sortDesc();
        if ($top->isNotEmpty()) {
            $sector = (string) $top->keys()->first();
            $count = (int) $top->first();
            PurposeMapInsight::query()->create([
                'purpose_map_campaign_id' => $campaign->id,
                'kind' => 'concentration',
                'message' => "Maior participação até agora: {$sector} ({$count} resposta".($count === 1 ? '' : 's').').',
                'sort_order' => $sort++,
            ]);
        }

        PurposeMapInsight::query()->create([
            'purpose_map_campaign_id' => $campaign->id,
            'kind' => 'alignment',
            'message' => 'Compare as colunas “eu / setor / empresa” no alinhamento de dores para ver se a narrativa de valor está coerente entre os níveis.',
            'sort_order' => $sort,
        ]);
    }

    /**
     * @return array<string, true>
     */
    private function stopWords(): array
    {
        $words = [
            'para', 'como', 'mais', 'menos', 'porque', 'quando', 'onde', 'quem', 'qual', 'quais',
            'esta', 'este', 'isso', 'aqui', 'ali', 'pela', 'pelo', 'pelo', 'uma', 'uns', 'umas',
            'dos', 'das', 'com', 'sem', 'sobre', 'entre', 'depois', 'antes', 'muito', 'muita',
            'fazer', 'sendo', 'sendo', 'também', 'sempre', 'nunca', 'todo', 'toda', 'todos',
            'todas', 'nosso', 'nossa', 'seus', 'suas', 'meu', 'minha', 'seus', 'elas', 'eles',
            'você', 'vocês', 'trabalho', 'empresa', 'setor', 'pessoas', 'pessoa', 'cliente',
            'clientes', 'equipe', 'time', 'vida', 'dia', 'dias', 'ano', 'anos', 'tudo', 'nada',
        ];

        return array_fill_keys($words, true);
    }
}
