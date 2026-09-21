<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePurposeMapThemesJob;
use App\Models\Company;
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
    private function productMeta(): array
    {
        return [
            'name' => 'Mapa de Propósito',
            'subtitle' => 'Por que trabalhamos, o que sonhamos e quais dores resolvemos — por setor.',
        ];
    }

    /**
     * Garante um mapa ativo (sem CRUD de campanha) e usa a primeira empresa ativa se precisar criar.
     */
    private function resolveActiveCampaign(): PurposeMapCampaign
    {
        $existing = PurposeMapCampaign::query()
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

        $company = Company::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        return PurposeMapCampaign::query()->create([
            'company_id' => $company->id,
            'title' => 'Mapa de Propósito',
            'public_token' => (string) Str::uuid(),
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ]);
    }

    /**
     * Menu Admin → Voz do Time: dashboard / controlo (visão operacional).
     */
    public function index(Request $request, PurposeMapDashboardBuilder $builder): Response
    {
        return $this->renderDashboard($request, $builder);
    }

    /**
     * Alias estável para resultados (mesmo dashboard do index).
     */
    public function show(Request $request, PurposeMapDashboardBuilder $builder): Response
    {
        return $this->renderDashboard($request, $builder);
    }

    private function renderDashboard(Request $request, PurposeMapDashboardBuilder $builder): Response
    {
        $campaign = $this->resolveActiveCampaign()->load(['company:id,name']);

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

        return Inertia::render('Admin/PurposeMap/Show', [
            'company' => $campaign->company?->only(['id', 'name']),
            'campaign' => $campaign,
            'publicUrl' => $questionsUrl,
            'questionsUrl' => $questionsUrl,
            'sectorFilter' => $sector,
            'dashboard' => $dashboard,
            'themesPending' => $pending,
            'product' => $this->productMeta(),
            'actionUrls' => [
                'show' => route('admin.mapa-proposito.index', absolute: false),
                'analyze' => route('admin.mapa-proposito.analyze', absolute: false),
            ],
        ]);
    }

    public function analyze(): RedirectResponse
    {
        $campaign = $this->resolveActiveCampaign();

        $cacheKey = PurposeMapThemeAnalyzer::pendingCacheKey($campaign->id);
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            GeneratePurposeMapThemesJob::dispatch($campaign->id);
        }

        return redirect()
            ->route('admin.mapa-proposito.index')
            ->with('success', 'Análise de temas enfileirada. Atualize a página em instantes.');
    }
}
