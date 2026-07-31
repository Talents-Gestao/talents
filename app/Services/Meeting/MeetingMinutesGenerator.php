<?php

declare(strict_types=1);

namespace App\Services\Meeting;

use App\Models\AiSetting;
use App\Models\Meeting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MeetingMinutesGenerator
{
    public function generate(string $transcript, Meeting $meeting, AiSetting $setting): string
    {
        if (! $setting->is_enabled) {
            throw new RuntimeException('IA desabilitada nas configurações.');
        }

        if ($setting->safeApiKey() === null) {
            throw new RuntimeException('Chave da API de análise não configurada.');
        }

        $payload = [
            'title' => $meeting->title,
            'participants' => $meeting->participants_text,
            'company' => $meeting->company?->name,
            'transcript' => $transcript,
        ];

        $userContent = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return match ($setting->provider) {
            'anthropic' => $this->callAnthropic($setting, $userContent),
            default => $this->callOpenAi($setting, $userContent),
        };
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
Você é um assistente de RH da plataforma Talents, especializado em redigir atas de reunião em português do Brasil.

Sua tarefa: ler a transcrição completa de uma reunião e produzir uma ATA clara, objetiva e revisável.

Regras obrigatórias:
- Use APENAS informações presentes na transcrição e nos metadados fornecidos. Não invente.
- Se algo não foi mencionado, indique "Não mencionado" na seção correspondente.
- Escreva em português do Brasil, com acentuação correta.
- Responda SOMENTE com o texto da ata (sem JSON, sem markdown de code fence).
- Estruture exatamente nestas seções, nesta ordem, com estes títulos:

## Resumo
## Participantes mencionados
## Decisões
## Ações / próximos passos
## Observações

Nas ações, quando possível use o formato: "- [Responsável] — ação — prazo (se houver)".
PROMPT;
    }

    private function callOpenAi(AiSetting $setting, string $userContent): string
    {
        $key = $setting->safeApiKey();
        $timeout = (int) config('meeting.minutes_timeout', 180);

        $response = Http::timeout($timeout)
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
            Log::warning('Meeting minutes OpenAI error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Falha na geração da ata (OpenAI): HTTP '.$response->status());
        }

        $content = $response->json('choices.0.message.content') ?? '';

        return $this->normalizeMinutes(is_string($content) ? $content : '');
    }

    private function callAnthropic(AiSetting $setting, string $userContent): string
    {
        $key = $setting->safeApiKey();
        $timeout = (int) config('meeting.minutes_timeout', 180);

        $response = Http::timeout($timeout)
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
            Log::warning('Meeting minutes Anthropic error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Falha na geração da ata (Anthropic): HTTP '.$response->status());
        }

        $blocks = $response->json('content') ?? [];
        $text = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text .= $block['text'];
            }
        }

        return $this->normalizeMinutes($text);
    }

    private function normalizeMinutes(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:markdown|md|text)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        }

        $content = trim($content);
        if ($content === '') {
            throw new RuntimeException('ATA gerada vazia. Verifique a qualidade da transcrição.');
        }

        return $content;
    }
}
