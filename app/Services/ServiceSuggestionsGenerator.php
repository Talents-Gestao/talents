<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\CommercialProduct;
use App\Models\Survey;
use App\Models\SurveyServiceSuggestion;
use App\Models\User;
use App\Support\Nr1GroupPrivacy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Uma geração de serviços do catálogo por campanha encerrada. A empresa só vê o que a Talents liberar.
 */
final class ServiceSuggestionsGenerator
{
    public function __construct(private readonly Nr1AiAnalyzer $analyzer) {}

    public static function pendingCacheKey(int $surveyId): string
    {
        return Nr1AiAnalyzer::pendingCacheKey($surveyId, Nr1AiAnalyzer::TYPE_NR1_SERVICE_SUGGESTIONS);
    }

    public function generate(Survey $survey, AiSetting $setting, ?User $actor = null): int
    {
        $catalog = $this->catalog();
        if ($catalog === [] || ! Nr1GroupPrivacy::hasMinimumSample($survey) || ! $survey->results()->exists()) {
            $survey->forceFill(['service_suggestions_generated_at' => now()])->save();

            return 0;
        }

        $result = $this->analyzer->generateServiceSuggestions($survey, $setting, $catalog);
        $rows = $this->parseSuggestions($result['content'], $catalog);
        if ($rows === [] && ! $this->explicitlyEmpty($result['content'])) {
            throw new RuntimeException('A IA não devolveu sugestões utilizáveis.');
        }

        DB::transaction(function () use ($survey, $actor, $rows, $result): void {
            foreach ($rows as $index => $row) {
                SurveyServiceSuggestion::query()->create([
                    'survey_id' => $survey->id,
                    'company_id' => $survey->company_id,
                    'commercial_product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'rationale' => $row['rationale'],
                    'status' => SurveyServiceSuggestion::STATUS_PENDING,
                    'sort_order' => $index,
                ]);
            }

            AiAnalysis::query()->create([
                'survey_id' => $survey->id,
                'type' => Nr1AiAnalyzer::TYPE_NR1_SERVICE_SUGGESTIONS,
                'content' => $result['content'],
                'prompt_tokens' => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'model_used' => $result['model_used'],
                'generated_by' => $actor?->id,
            ]);

            $survey->forceFill(['service_suggestions_generated_at' => now()])->save();
        });

        return count($rows);
    }

    /**
     * @param  list<array{id: int, name: string, description: ?string}>  $catalog
     * @return list<array{product_id: int, product_name: string, rationale: string}>
     */
    public function parseSuggestions(string $content, array $catalog): array
    {
        $decoded = json_decode($this->jsonSlice($content), true);
        if (! is_array($decoded) || ! is_array($decoded['suggestions'] ?? null)) {
            return [];
        }

        $byId = collect($catalog)->keyBy('id');
        $rows = [];
        $used = [];
        foreach ($decoded['suggestions'] as $item) {
            if (! is_array($item) || count($rows) >= 4) {
                continue;
            }
            $product = $byId->get((int) ($item['product_id'] ?? 0));
            $rationale = trim((string) ($item['rationale'] ?? ''));
            if ($product === null || $rationale === '' || isset($used[$product['id']])) {
                continue;
            }
            $used[$product['id']] = true;
            $rows[] = [
                'product_id' => (int) $product['id'],
                'product_name' => (string) $product['name'],
                'rationale' => mb_substr($rationale, 0, 2000),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, name: string, description: ?string}>
     */
    private function catalog(): array
    {
        return CommercialProduct::query()
            ->active()
            ->ordered()
            ->limit(40)
            ->get(['id', 'name', 'description'])
            ->map(fn (CommercialProduct $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => filled($product->description) ? mb_substr(trim(strip_tags((string) $product->description)), 0, 400) : null,
            ])
            ->all();
    }

    private function explicitlyEmpty(string $content): bool
    {
        $decoded = json_decode($this->jsonSlice($content), true);

        return is_array($decoded) && is_array($decoded['suggestions'] ?? null) && $decoded['suggestions'] === [];
    }

    private function jsonSlice(string $content): string
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end < $start) {
            return $content;
        }

        return substr($content, $start, $end - $start + 1);
    }
}
