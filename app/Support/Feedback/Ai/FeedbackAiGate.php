<?php

declare(strict_types=1);

namespace App\Support\Feedback\Ai;

use App\Models\AiSetting;
use App\Services\Feedback\Ai\FeedbackAiException;

/**
 * Trava de conformidade da IA em Feedbacks (não é interruptor de produto). Só há chamada de IA com dados de
 * Feedbacks quando a Talents aprovou, em /admin/settings, o provedor que vai receber os dados — e ele continua
 * sendo o provedor configurado da Mia. O provedor de demonstração (sem IA externa) só vale com APP_ENV=local.
 */
final class FeedbackAiGate
{
    public const PROVIDER_DEMO = 'demo';

    public const DEMO_MODEL = 'demonstracao-local';

    public const LOCKED_MESSAGE = 'Processamento por IA ainda não liberado.';

    public static function demoAvailable(): bool
    {
        return app()->environment('local');
    }

    public static function providerLabel(?string $provider): string
    {
        return match ($provider) {
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            self::PROVIDER_DEMO => 'Demonstração local (sem IA externa)',
            default => '—',
        };
    }

    /**
     * @return array{allowed: bool, provider: ?string, provider_label: string, model: ?string, reason: ?string, message: ?string}
     */
    public function status(?AiSetting $setting = null): array
    {
        $setting ??= AiSetting::current();
        $approved = $setting?->feedback_approved_provider;

        if ($setting === null || $approved === null || $approved === '') {
            return $this->locked('not_approved', 'Nenhum provedor foi aprovado para dados de Feedbacks.');
        }

        if ($approved === self::PROVIDER_DEMO) {
            if (! self::demoAvailable()) {
                return $this->locked('demo_outside_local', 'O provedor de demonstração só funciona no ambiente local.');
            }

            return $this->allowed(self::PROVIDER_DEMO, self::DEMO_MODEL);
        }

        if ($approved !== $setting->provider) {
            return $this->locked('provider_changed', 'O provedor da Mia mudou depois da aprovação. Aprove de novo.');
        }

        if (! $setting->is_enabled) {
            return $this->locked('ai_disabled', 'A Mia (IA) está desabilitada nas configurações.');
        }

        if ($setting->safeApiKey() === null) {
            return $this->locked('missing_key', 'A chave da API da Mia não está configurada ou não pode ser lida.');
        }

        return $this->allowed((string) $setting->provider, (string) $setting->model);
    }

    /**
     * @return array{provider: string, model: string}
     *
     * @throws FeedbackAiException
     */
    public function assertAllowed(): array
    {
        $status = $this->status();

        if (! $status['allowed']) {
            throw new FeedbackAiException(self::LOCKED_MESSAGE, 'not_approved');
        }

        return ['provider' => (string) $status['provider'], 'model' => (string) $status['model']];
    }

    private function allowed(string $provider, string $model): array
    {
        return [
            'allowed' => true,
            'provider' => $provider,
            'provider_label' => self::providerLabel($provider),
            'model' => $model,
            'reason' => null,
            'message' => null,
        ];
    }

    private function locked(string $reason, string $detail): array
    {
        return [
            'allowed' => false,
            'provider' => null,
            'provider_label' => '—',
            'model' => null,
            'reason' => $reason,
            'message' => $detail,
        ];
    }
}
