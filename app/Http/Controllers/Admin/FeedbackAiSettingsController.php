<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use App\Models\Company;
use App\Support\Feedback\Ai\FeedbackAiGate;
use App\Support\Feedback\Ai\FeedbackAiMonthlyLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Conformidade da IA em Feedbacks, em Configurações → Mia (IA): aprovar o provedor que vai receber dados de
 * Feedbacks (com quem aprovou e quando), revogar a aprovação e definir o limite mensal de custo por empresa.
 * Não é interruptor de produto: sem aprovação, o servidor recusa toda chamada de IA de Feedbacks.
 */
class FeedbackAiSettingsController extends Controller
{
    /** @return array<string, mixed> */
    public static function props(?AiSetting $setting): array
    {
        $gate = app(FeedbackAiGate::class)->status($setting);
        $setting?->loadMissing('feedbackApprovedBy');

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Company $c) => $c->hasFeedbacksEnabled())
            ->map(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'limit_cents' => $c->feedback_ai_monthly_limit_cents,
                'effective_limit_cents' => FeedbackAiMonthlyLimit::limitCents($c),
                'used_cents' => FeedbackAiMonthlyLimit::usedCents($c->id),
            ])
            ->values();

        return [
            'status' => $gate,
            'approved_provider' => $setting?->feedback_approved_provider,
            'approved_provider_label' => FeedbackAiGate::providerLabel($setting?->feedback_approved_provider),
            'approved_by' => $setting?->feedbackApprovedBy?->name,
            'approved_at' => $setting?->feedback_approved_at?->toIso8601String(),
            'current_provider' => $setting?->provider,
            'current_provider_label' => FeedbackAiGate::providerLabel($setting?->provider),
            'current_model' => $setting?->model,
            'demo_available' => FeedbackAiGate::demoAvailable(),
            'default_limit_cents' => FeedbackAiMonthlyLimit::DEFAULT_LIMIT_CENTS,
            'companies' => $companies,
        ];
    }

    public function approve(Request $request): RedirectResponse
    {
        $setting = AiSetting::query()->firstOrFail();
        $allowed = array_filter([$setting->provider, FeedbackAiGate::demoAvailable() ? FeedbackAiGate::PROVIDER_DEMO : null]);

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in($allowed)],
            'confirmed' => ['accepted'],
        ], [
            'provider.in' => 'Escolha o provedor configurado na Mia.',
            'confirmed.accepted' => 'Confirme que contrato, retenção, região dos dados e não uso para treinamento foram verificados.',
        ]);

        $setting->forceFill([
            'feedback_approved_provider' => $data['provider'],
            'feedback_approved_by' => $request->user()->id,
            'feedback_approved_at' => now(),
        ])->save();

        return redirect()->route('admin.settings.edit', ['tab' => 'ia'])
            ->with('success', 'Provedor aprovado para dados de Feedbacks: '.FeedbackAiGate::providerLabel($data['provider']).'.');
    }

    public function revoke(Request $request): RedirectResponse
    {
        AiSetting::query()->firstOrFail()->forceFill([
            'feedback_approved_provider' => null,
            'feedback_approved_by' => $request->user()->id,
            'feedback_approved_at' => now(),
        ])->save();

        return redirect()->route('admin.settings.edit', ['tab' => 'ia'])
            ->with('success', 'Aprovação revogada. Nenhum dado de Feedbacks será enviado para IA.');
    }

    public function updateLimit(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->hasFeedbacksEnabled(), 404);

        $data = $request->validate(
            ['limit_cents' => ['nullable', 'integer', 'min:0', 'max:10000000']],
            ['limit_cents.integer' => 'Informe o limite em reais.', 'limit_cents.max' => 'O limite máximo é R$ 100.000,00.'],
        );

        $company->forceFill(['feedback_ai_monthly_limit_cents' => $data['limit_cents'] ?? null])->save();

        return redirect()->route('admin.settings.edit', ['tab' => 'ia'])
            ->with('success', "Limite mensal de IA de Feedbacks salvo para {$company->name}.");
    }
}
