<?php

declare(strict_types=1);

namespace App\Services\Feedback\Ai;

use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\FeedbackRecording;
use App\Models\User;
use App\Support\Feedback\Ai\FeedbackAiCostEstimator;
use App\Support\Feedback\Ai\FeedbackAiGate;
use App\Support\Feedback\Ai\FeedbackAiMonthlyLimit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Única porta de saída de dados de Feedbacks para IA. A cada chamada: confere a trava do provedor aprovado e o
 * limite mensal da empresa, chama o provedor com limite de saída próprio da tarefa (não o max_tokens global da
 * Mia) e grava uma linha em ai_usage_logs (com ou sem sucesso). Erros chegam ao usuário em português; o detalhe
 * técnico vai só para o log.
 */
class FeedbackAiClient
{
    public const TIMEOUT_SECONDS = 180;

    public const TEMPERATURE = 0.2;

    public function __construct(
        private readonly FeedbackAiGate $gate,
        private readonly FeedbackAiMonthlyLimit $limit,
        private readonly DemoFeedbackAiProvider $demo,
    ) {}

    /**
     * @param  (callable(string): mixed)|null  $validate  interpreta a saída; FeedbackAiException vira falha registrada
     * @return array{text: string, parsed: mixed, provider: string, model: string}
     *
     * @throws FeedbackAiException
     */
    public function complete(
        FeedbackRecording $recording,
        ?User $actor,
        string $purpose,
        string $system,
        string $userContent,
        int $maxTokens,
        bool $json = false,
        ?callable $validate = null,
    ): array {
        ['provider' => $provider, 'model' => $model] = $this->gate->assertAllowed();
        $recording->loadMissing('session.company');
        $this->limit->assertWithin($recording->session->company);

        $started = hrtime(true);
        $log = fn (bool $success, int $in, int $out, ?string $error) => AiUsageLog::query()->create([
            'company_id' => $recording->company_id,
            'user_id' => $actor?->id,
            'purpose' => $purpose,
            'subject_type' => 'feedback_recording',
            'subject_id' => $recording->id,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $in,
            'output_tokens' => $out,
            'estimated_cost_cents' => FeedbackAiCostEstimator::cents($provider, $model, $in, $out),
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'success' => $success,
            'error_code' => $error === null ? null : mb_substr($error, 0, 64),
        ]);

        try {
            $result = match ($provider) {
                FeedbackAiGate::PROVIDER_DEMO => $this->demo->complete($purpose, $userContent),
                'anthropic' => $this->anthropic($model, $system, $userContent, $maxTokens),
                'openai' => $this->openAi($model, $system, $userContent, $maxTokens, $json),
                default => throw new FeedbackAiException(FeedbackAiGate::LOCKED_MESSAGE, 'unknown_provider'),
            };
        } catch (FeedbackAiException $e) {
            $log(false, $e instanceof FeedbackAiUsageException ? $e->inputTokens : 0, $e instanceof FeedbackAiUsageException ? $e->outputTokens : 0, $e->errorCode);

            throw $e;
        }

        // Saída inutilizável (ex.: relatório fora do esquema) conta como chamada com falha, mas com os tokens cobrados.
        $parsed = null;
        if ($validate !== null) {
            try {
                $parsed = $validate($result['text']);
            } catch (FeedbackAiException $e) {
                $log(false, $result['input_tokens'], $result['output_tokens'], $e->errorCode);

                throw $e;
            }
        }

        $log(true, $result['input_tokens'], $result['output_tokens'], null);

        return ['text' => $result['text'], 'parsed' => $parsed, 'provider' => $provider, 'model' => $model];
    }

    /** @return array{text: string, input_tokens: int, output_tokens: int} */
    private function openAi(string $model, string $system, string $userContent, int $maxTokens, bool $json): array
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => self::TEMPERATURE,
        ];
        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->send(fn () => Http::timeout(self::TIMEOUT_SECONDS)
            ->withToken((string) $this->apiKey())
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', $payload), 'OpenAI');

        $in = (int) $response->json('usage.prompt_tokens', 0);
        $out = (int) $response->json('usage.completion_tokens', 0);

        if ($response->json('choices.0.finish_reason') === 'length') {
            throw new FeedbackAiUsageException('A resposta da IA ficou incompleta. Tente de novo.', 'truncated', $in, $out);
        }

        return ['text' => trim((string) $response->json('choices.0.message.content', '')), 'input_tokens' => $in, 'output_tokens' => $out];
    }

    /** @return array{text: string, input_tokens: int, output_tokens: int} */
    private function anthropic(string $model, string $system, string $userContent, int $maxTokens): array
    {
        $response = $this->send(fn () => Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders([
                'x-api-key' => (string) $this->apiKey(),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => self::TEMPERATURE,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $userContent]],
            ]), 'Anthropic');

        $in = (int) $response->json('usage.input_tokens', 0);
        $out = (int) $response->json('usage.output_tokens', 0);

        if ($response->json('stop_reason') === 'max_tokens') {
            throw new FeedbackAiUsageException('A resposta da IA ficou incompleta. Tente de novo.', 'truncated', $in, $out);
        }

        $text = '';
        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return ['text' => trim($text), 'input_tokens' => $in, 'output_tokens' => $out];
    }

    /** @param callable(): Response $request */
    private function send(callable $request, string $providerName): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            Log::warning('Feedbacks IA: falha de conexão', ['provider' => $providerName, 'error' => $e->getMessage()]);

            throw new FeedbackAiException('O provedor de IA demorou mais que o esperado ou não respondeu. Tente de novo.', 'timeout');
        }

        if ($response->successful()) {
            return $response;
        }

        // Só status e mensagem de erro do provedor; o conteúdo enviado nunca vai para o log.
        Log::warning('Feedbacks IA: erro do provedor', [
            'provider' => $providerName,
            'status' => $response->status(),
            'error' => mb_substr((string) ($response->json('error.message') ?? $response->json('error.type') ?? ''), 0, 300),
        ]);

        throw match (true) {
            $response->status() === 429 => new FeedbackAiException('O provedor de IA está no limite de uso agora. Tente de novo em alguns minutos.', 'http_429'),
            $response->status() >= 500 => new FeedbackAiException('O provedor de IA está instável no momento. Tente de novo em alguns minutos.', 'http_'.$response->status()),
            default => new FeedbackAiException('O provedor de IA recusou o pedido. Tente de novo; se continuar, fale com a Talents.', 'http_'.$response->status()),
        };
    }

    private function apiKey(): ?string
    {
        return AiSetting::current()?->safeApiKey();
    }
}
