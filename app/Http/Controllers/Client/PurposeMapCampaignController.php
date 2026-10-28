<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePurposeMapThemesJob;
use App\Models\PurposeMapCampaign;
use App\Services\PurposeMapDashboardBuilder;
use App\Services\PurposeMapThemeAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PurposeMapCampaignController extends Controller
{
    private function companyId(Request $request): int
    {
        return (int) $request->user()->company_id;
    }

    private function resolveActiveCampaign(Request $request): PurposeMapCampaign
    {
        $companyId = $this->companyId($request);

        $existing = PurposeMapCampaign::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            if ($existing->ends_at !== null) {
                $existing->ends_at = null;
                $existing->save();
            }

            return $existing;
        }

        return PurposeMapCampaign::query()->create([
            'company_id' => $companyId,
            'title' => 'Mapa de Propósito',
            'public_token' => (string) Str::uuid(),
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ]);
    }

    /**
     * Painel do cliente: dashboard / controlo (mesma visão do Admin).
     */
    public function index(Request $request, PurposeMapDashboardBuilder $builder): Response
    {
        return $this->renderDashboard($request, $builder);
    }

    public function show(Request $request, PurposeMapDashboardBuilder $builder): Response
    {
        return $this->renderDashboard($request, $builder);
    }

    private function renderDashboard(Request $request, PurposeMapDashboardBuilder $builder): Response
    {
        $campaign = $this->resolveActiveCampaign($request);

        $sector = (string) $request->input('sector', 'all');
        if ($sector === '') {
            $sector = 'all';
        }

        $dashboard = $builder->build(
            $campaign,
            $sector === 'all' ? null : $sector
        );

        $pending = Cache::has(PurposeMapThemeAnalyzer::pendingCacheKey($campaign->id));
        // Absolute: o link anónimo é partilhado fora da app (WhatsApp, e-mail).
        $questionsUrl = route('purpose-map.public', ['token' => $campaign->public_token]);

        return Inertia::render('Client/PurposeMap/Show', [
            'campaign' => $campaign,
            'publicUrl' => $questionsUrl,
            'questionsUrl' => $questionsUrl,
            'sectorFilter' => $sector,
            'dashboard' => $dashboard,
            'themesPending' => $pending,
            'product' => [
                'name' => 'Mapa de Propósito',
                'subtitle' => 'Por que trabalhamos, o que sonhamos e quais dores resolvemos — por setor.',
            ],
            'actionUrls' => [
                'show' => route('client.mapa-proposito.index', absolute: false),
                'analyze' => route('client.mapa-proposito.analyze', absolute: false),
            ],
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        $campaign = $this->resolveActiveCampaign($request);

        $cacheKey = PurposeMapThemeAnalyzer::pendingCacheKey($campaign->id);
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            GeneratePurposeMapThemesJob::dispatch($campaign->id);
        }

        return redirect()
            ->route('client.mapa-proposito.index')
            ->with('success', 'Análise de temas enfileirada. Atualize a página em instantes.');
    }
}
