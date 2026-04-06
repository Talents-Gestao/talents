<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Survey;
use App\Models\SurveyResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class Nr1AiAnalyzer
{
    public const TYPE_NR1_GUIDANCE = 'nr1_guidance';

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
Você é a Mia, assistente de inteligência artificial da plataforma Talents, especializada em riscos psicossociais no trabalho conforme a NR-1 (Portaria MTE nº 1.419/2024) e boas práticas de saúde mental organizacional no Brasil.

Regras obrigatórias:
- Use apenas os dados agregados fornecidos (médias, níveis de risco, contagens por dimensão/setor). Não invente números nem respondentes individuais.
- Não identifique pessoas. Não solicite nem suponha dados pessoais.
- Escreva em linguagem natural e acolhedora, como uma colega de SST falando com gestores, mas mantenha rigor técnico. No texto final, não diga que você é uma IA, assistente virtual ou modelo de linguagem — apresente-se como uma análise técnica direta.
- Formate em Markdown: use ## para títulos de seção (sem numerar com 1., 2., 3.), **negrito** para destaques e subtópicos importantes, e listas quando fizer sentido. Não use enumeração automática do tipo "1." "2." no início das seções.
- Sua função é **apenas avaliar o cenário** com base nos dados: panorama geral; dimensões mais críticas ou em atenção; diferenças entre setores (se houver dados). Não elabore plano de ação, lista de medidas corretivas, cronograma, orientações práticas detalhadas nem recomendações operacionais passo a passo — isso fica com a equipe de especialistas da Talents.
- Ao final do texto, inclua um parágrafo claro recomendando que a empresa **entre em contato com a Talents** para obter parecer técnico com especialista e, se aplicável, plano de ação personalizado conforme a realidade da organização.
- Deixe uma frase clara de que a análise é apoio à decisão e não substitui avaliação por profissionais habilitados nem obrigações legais da empresa.
- Separe cada bloco (parágrafo ou seção) com espaço visual confortável: use linha em branco entre parágrafos e após cada título ##.

Se os dados forem insuficientes, diga isso objetivamente e indique o que falta em termos agregados (ex.: mais respondentes por setor).
PROMPT;
    }

    /**
     * @return array{content: string, prompt_tokens: int|null, completion_tokens: int|null, model_used: string}
     */
    public function generateNarrative(Survey $survey, AiSetting $setting): array
    {
        $payload = $this->buildAggregatedPayload($survey);
        $userContent = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return match ($setting->provider) {
            'anthropic' => $this->callAnthropic($setting, $userContent),
            default => $this->callOpenAi($setting, $userContent),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAggregatedPayload(Survey $survey): array
    {
        $survey->load(['template.sections', 'company']);

        $results = SurveyResult::query()
            ->where('survey_id', $survey->id)
            ->with(['section', 'department'])
            ->orderBy('survey_template_section_id')
            ->orderBy('department_id')
            ->get();

        $overall = $results->first(fn ($r) => $r->survey_template_section_id === null && $r->department_id === null);

        $bySection = $results
            ->filter(fn ($r) => $r->survey_template_section_id !== null && $r->department_id === null)
            ->values()
            ->map(fn ($r) => [
                'dimension' => data_get($r->meta, 'section_title') ?? $r->section?->title ?? 'Dimensão',
                'average_score' => round((float) $r->average_score, 2),
                'risk_level' => $r->risk_level,
                'respondent_count' => $r->respondent_count,
            ])
            ->all();

        $deptOveralls = $results
            ->filter(fn ($r) => $r->department_id !== null && $r->survey_template_section_id === null)
            ->map(fn ($r) => [
                'department' => $r->department?->name ?? 'Setor',
                'average_score' => round((float) $r->average_score, 2),
                'risk_level' => $r->risk_level,
                'respondent_count' => $r->respondent_count,
            ])
            ->values()
            ->all();

        $deptSectionResults = $results->filter(
            fn ($r) => $r->department_id !== null && $r->survey_template_section_id !== null
        );

        $byDeptAndDimension = $deptSectionResults->map(fn ($r) => [
            'department' => $r->department?->name ?? 'Setor',
            'dimension' => data_get($r->meta, 'section_title') ?? $r->section?->title ?? 'Dimensão',
            'average_score' => round((float) $r->average_score, 2),
            'risk_level' => $r->risk_level,
            'respondent_count' => $r->respondent_count,
        ])->values()->all();

        return [
            'survey_title' => $survey->title,
            'company' => $survey->company?->name ?? 'Empresa',
            'min_responses_for_breakdown' => $survey->min_responses_for_breakdown,
            'overall' => $overall ? [
                'average_score' => round((float) $overall->average_score, 2),
                'risk_level' => $overall->risk_level,
                'respondent_count' => $overall->respondent_count,
            ] : null,
            'by_dimension' => $bySection,
            'by_department_summary' => $deptOveralls,
            'by_department_and_dimension' => $byDeptAndDimension,
        ];
    }

    /**
     * @return array{content: string, prompt_tokens: int|null, completion_tokens: int|null, model_used: string}
     */
    private function callOpenAi(AiSetting $setting, string $userContent): array
    {
        $key = $setting->safeApiKey();
        if ($key === null || $key === '') {
            throw new RuntimeException('API key não configurada.');
        }

        $response = Http::timeout(120)
            ->withToken($key)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $setting->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'max_tokens' => $setting->max_tokens,
                'temperature' => $setting->temperature,
            ]);

        if (! $response->successful()) {
            Log::warning('OpenAI API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Falha na API OpenAI: HTTP '.$response->status());
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        return [
            'content' => is_string($text) ? $text : '',
            'prompt_tokens' => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            'completion_tokens' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            'model_used' => (string) ($data['model'] ?? $setting->model),
        ];
    }

    /**
     * @return array{content: string, prompt_tokens: int|null, completion_tokens: int|null, model_used: string}
     */
    private function callAnthropic(AiSetting $setting, string $userContent): array
    {
        $key = $setting->safeApiKey();
        if ($key === null || $key === '') {
            throw new RuntimeException('API key não configurada.');
        }

        $response = Http::timeout(120)
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $setting->model,
                'max_tokens' => $setting->max_tokens,
                'temperature' => $setting->temperature,
                'system' => $this->systemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Anthropic API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('Falha na API Anthropic: HTTP '.$response->status());
        }

        $data = $response->json();
        $blocks = $data['content'] ?? [];
        $text = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text .= $block['text'];
            }
        }
        $usage = $data['usage'] ?? [];

        return [
            'content' => $text,
            'prompt_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            'completion_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
            'model_used' => (string) ($data['model'] ?? $setting->model),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(AiSetting $setting): array
    {
        try {
            if ($setting->provider === 'anthropic') {
                return $this->testAnthropic($setting);
            }

            return $this->testOpenAi($setting);
        } catch (\Throwable $e) {
            Log::warning('AI test connection failed', ['e' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function testOpenAi(AiSetting $setting): array
    {
        $key = $setting->safeApiKey();
        if ($key === null || $key === '') {
            return ['ok' => false, 'message' => 'Informe a chave da API.'];
        }

        $response = Http::timeout(30)
            ->withToken($key)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $setting->model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Responda apenas: OK'],
                ],
                'max_tokens' => 5,
                'temperature' => 0,
            ]);

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'HTTP '.$response->status().': '.Str::limit($response->body(), 200)];
        }

        return ['ok' => true, 'message' => 'Conexão com OpenAI OK.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function testAnthropic(AiSetting $setting): array
    {
        $key = $setting->safeApiKey();
        if ($key === null || $key === '') {
            return ['ok' => false, 'message' => 'Informe a chave da API.'];
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $setting->model,
                'max_tokens' => 10,
                'temperature' => 0,
                'messages' => [
                    ['role' => 'user', 'content' => 'Responda apenas: OK'],
                ],
            ]);

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'HTTP '.$response->status().': '.Str::limit($response->body(), 200)];
        }

        return ['ok' => true, 'message' => 'Conexão com Anthropic OK.'];
    }
}
