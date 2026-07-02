<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiAnalysisJob;
use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\Company;
use App\Models\Survey;
use App\Models\SurveyNr1Report;
use App\Support\HtmlSanitizer;
use App\Support\Nr1RiskScenarioResolver;
use App\Services\ActionPlanGenerator;
use App\Services\Nr1AiAnalyzer;
use App\Services\SurveyResultsPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class ActionPlanAdminController extends Controller
{
    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless($survey->company_id === $company->id, 404);
    }

    public function edit(Company $company, Survey $survey): Response
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $presented = SurveyResultsPresenter::forSurvey($survey);

        $aiSetting = AiSetting::current();
        $aiEnabled = $aiSetting
            && $aiSetting->is_enabled
            && $aiSetting->safeApiKey() !== null;

        $latestAi = AiAnalysis::query()
            ->where('survey_id', $survey->id)
            ->where('type', Nr1AiAnalyzer::TYPE_NR1_GUIDANCE)
            ->latest()
            ->first();

        $latestTechnicalOpinionAi = AiAnalysis::query()
            ->where('survey_id', $survey->id)
            ->where('type', Nr1AiAnalyzer::TYPE_NR1_TECHNICAL_OPINION)
            ->latest()
            ->first();

        $aiAnalysisPending = Cache::has(Nr1AiAnalyzer::pendingCacheKey($survey->id, Nr1AiAnalyzer::TYPE_NR1_GUIDANCE));
        $technicalOpinionAiPending = Cache::has(
            Nr1AiAnalyzer::pendingCacheKey($survey->id, Nr1AiAnalyzer::TYPE_NR1_TECHNICAL_OPINION)
        );

        $plan = ActionPlan::query()
            ->where('company_id', $company->id)
            ->where('survey_id', $survey->id)
            ->with('items')
            ->first();

        $items = $plan?->items->map(fn (ActionPlanItem $i) => [
            'id' => $i->id,
            'title' => $i->title,
            'description' => $i->description ?? '',
        ])->values()->all() ?? [];

        $nr1Reports = $this->nr1ReportsPayload($company, $survey);

        return Inertia::render('Admin/ActionPlan/Edit', array_merge($presented, [
            'company' => $company->only(['id', 'name']),
            'riskScenario' => Nr1RiskScenarioResolver::forSurvey($survey),
            'riskScenarioLabel' => Nr1RiskScenarioResolver::scenarioConfig(
                Nr1RiskScenarioResolver::forSurvey($survey) ?? 'green'
            )['short_label'] ?? null,
            'nr1Reports' => $nr1Reports,
            'plan' => $plan ? [
                'id' => $plan->id,
                'admin_published_at' => $plan->admin_published_at?->format('d/m/Y H:i'),
                'technical_opinion' => $plan->technical_opinion ?? '',
                'technical_opinion_file_name' => $plan->technical_opinion_file_name,
                'technical_opinion_file_url' => $plan->technical_opinion_file_path
                    ? route('admin.companies.surveys.technical-opinion-file.download', [$company, $survey])
                    : null,
            ] : null,
            'technical_opinion' => $plan?->technical_opinion ?? '',
            'items' => $items,
            'aiEnabled' => $aiEnabled,
            'aiAnalysis' => $latestAi ? [
                'content' => $latestAi->content,
            ] : null,
            'aiAnalysisPending' => $aiAnalysisPending,
            'technicalOpinionAi' => $latestTechnicalOpinionAi ? [
                'content' => $latestTechnicalOpinionAi->content,
            ] : null,
            'technicalOpinionAiPending' => $technicalOpinionAiPending,
            'aiGeneratePostUrl' => url('/admin/companies/'.$company->getKey().'/surveys/'.$survey->getKey().'/ai-analysis'),
            'technicalOpinionGeneratePostUrl' => url('/admin/companies/'.$company->getKey().'/surveys/'.$survey->getKey().'/technical-opinion'),
            'generateSuggestedPlanUrl' => route('admin.companies.surveys.action-plan.generate-suggested', [$company, $survey]),
        ]));
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function nr1ReportsPayload(Company $company, Survey $survey): array
    {
        $reports = SurveyNr1Report::query()
            ->where('survey_id', $survey->id)
            ->where('company_id', $company->id)
            ->get()
            ->keyBy('type');

        $map = fn (string $type) => $this->formatNr1Report($reports->get($type), $company, $survey, $type);

        return [
            'executive' => $map(SurveyNr1Report::TYPE_EXECUTIVE),
            'technical_referral' => $map(SurveyNr1Report::TYPE_TECHNICAL_REFERRAL),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatNr1Report(?SurveyNr1Report $report, Company $company, Survey $survey, string $type): ?array
    {
        if ($report === null || ! filled($report->file_path)) {
            return null;
        }

        return [
            'file_name' => $report->file_name,
            'published_at' => $report->published_at?->format('d/m/Y H:i'),
            'download_url' => route('admin.companies.surveys.nr1-reports.download', [$company, $survey, $type]),
        ];
    }

    public function generateSuggestedPlan(Company $company, Survey $survey, ActionPlanGenerator $generator): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if (! $survey->results()->exists()) {
            return back()->with('error', 'Não há resultados calculados para gerar o plano sugerido.');
        }

        $plan = $generator->generate($survey);

        return redirect()
            ->route('admin.companies.surveys.action-plan.edit', [$company, $survey])
            ->with('success', 'Plano sugerido gerado com '.$plan->items->count().' ação(ões) conforme o cenário de risco. Revise e salve para publicar.');
    }

    public function generateAiAnalysis(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        return $this->dispatchAiGeneration(
            $request,
            $company,
            $survey,
            Nr1AiAnalyzer::TYPE_NR1_GUIDANCE,
            'admin-ai-analysis:',
            'Análise solicitada. Em alguns segundos atualize a página para ver o texto da Mia.'
        );
    }

    public function generateTechnicalOpinion(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        return $this->dispatchAiGeneration(
            $request,
            $company,
            $survey,
            Nr1AiAnalyzer::TYPE_NR1_TECHNICAL_OPINION,
            'admin-technical-opinion:',
            'Parecer solicitado. Em alguns segundos atualize a página e use "Inserir no editor".'
        );
    }

    private function dispatchAiGeneration(
        Request $request,
        Company $company,
        Survey $survey,
        string $type,
        string $rateKeyPrefix,
        string $successMessage
    ): RedirectResponse {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $setting = AiSetting::current();
        if (! $setting || ! $setting->is_enabled || $setting->safeApiKey() === null) {
            return back()->with('error', 'A análise por IA não está disponível. Configure a Mia em Configurações.');
        }

        if (! $survey->results()->exists()) {
            return back()->with('error', 'Não há resultados calculados para esta pesquisa.');
        }

        $rateKey = $rateKeyPrefix.$survey->id.':'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            return back()->with('error', 'Limite de gerações por hora atingido. Tente mais tarde.');
        }

        $pendingKey = Nr1AiAnalyzer::pendingCacheKey($survey->id, $type);
        if (Cache::has($pendingKey)) {
            return back()->with('info', 'Já existe uma geração em processamento. Atualize a página em instantes.');
        }

        Cache::put($pendingKey, true, now()->addMinutes(15));
        RateLimiter::hit($rateKey, 3600);

        GenerateAiAnalysisJob::dispatch($survey->id, (int) $request->user()->id, $type);

        return redirect()
            ->route('admin.companies.surveys.action-plan.edit', [$company, $survey])
            ->with('success', $successMessage);
    }

    public function update(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'technical_opinion' => ['nullable', 'string'],
            'technical_opinion_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'remove_technical_opinion_file' => ['nullable', 'boolean'],
            'executive_report_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'remove_executive_report_file' => ['nullable', 'boolean'],
            'technical_referral_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'remove_technical_referral_file' => ['nullable', 'boolean'],
        ], [
            'technical_opinion_file.mimes' => 'O arquivo do parecer deve ser PDF, DOC ou DOCX.',
            'technical_opinion_file.max' => 'O arquivo do parecer não pode exceder 20 MB.',
            'executive_report_file.mimes' => 'O relatório executivo deve ser PDF, DOC ou DOCX.',
            'executive_report_file.max' => 'O relatório executivo não pode exceder 20 MB.',
            'technical_referral_file.mimes' => 'O encaminhamento técnico deve ser PDF, DOC ou DOCX.',
            'technical_referral_file.max' => 'O encaminhamento técnico não pode exceder 20 MB.',
        ]);

        $technicalOpinion = HtmlSanitizer::sanitizeRichText($data['technical_opinion'] ?? null);
        $filteredItems = collect($data['items'])->filter(
            fn (array $row) => trim((string) ($row['title'] ?? '')) !== ''
        )->values()->all();

        $uploadedFile = $request->file('technical_opinion_file');
        $removeFile = $request->boolean('remove_technical_opinion_file');

        $plan = ActionPlan::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'survey_id' => $survey->id,
            ],
            ['status' => 'open']
        );

        $existingFilePath = $plan->technical_opinion_file_path;
        $newFilePath = $existingFilePath;
        $newFileName = $plan->technical_opinion_file_name;

        if ($uploadedFile !== null) {
            $newFilePath = $uploadedFile->store('action-plans/'.$plan->id.'/technical-opinion', 'local');
            $newFileName = $uploadedFile->getClientOriginalName();
        } elseif ($removeFile) {
            $newFilePath = null;
            $newFileName = null;
        }

        $hasPublishableContent = count($filteredItems) > 0
            || ($technicalOpinion !== null && trim(strip_tags($technicalOpinion)) !== '')
            || $newFilePath !== null;

        DB::transaction(function () use ($plan, $filteredItems, $technicalOpinion, $hasPublishableContent, $newFilePath, $newFileName) {
            $plan->items()->delete();

            foreach ($filteredItems as $index => $row) {
                ActionPlanItem::create([
                    'action_plan_id' => $plan->id,
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'status' => 'pending',
                    'sort_order' => $index,
                ]);
            }

            $plan->update([
                'technical_opinion' => $technicalOpinion,
                'technical_opinion_file_path' => $newFilePath,
                'technical_opinion_file_name' => $newFileName,
                'admin_published_at' => $hasPublishableContent ? now() : null,
            ]);
        });

        if (($uploadedFile !== null || $removeFile)
            && $existingFilePath
            && $existingFilePath !== $newFilePath
            && Storage::disk('local')->exists($existingFilePath)) {
            Storage::disk('local')->delete($existingFilePath);
        }

        $this->syncNr1ReportUpload(
            $request,
            $company,
            $survey,
            SurveyNr1Report::TYPE_EXECUTIVE,
            'executive_report_file',
            'remove_executive_report_file'
        );

        $this->syncNr1ReportUpload(
            $request,
            $company,
            $survey,
            SurveyNr1Report::TYPE_TECHNICAL_REFERRAL,
            'technical_referral_file',
            'remove_technical_referral_file'
        );

        return redirect()
            ->route('admin.companies.surveys.action-plan.edit', [$company, $survey])
            ->with('success', $hasPublishableContent
                ? 'Parecer e plano de ação salvos e disponibilizados para a empresa.'
                : 'Conteúdo removido — a empresa não verá parecer nem plano até você publicar novamente.');
    }

    public function downloadTechnicalOpinionFile(Company $company, Survey $survey): StreamedResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $plan = ActionPlan::query()
            ->where('company_id', $company->id)
            ->where('survey_id', $survey->id)
            ->first();

        abort_unless(
            $plan
            && $plan->technical_opinion_file_path
            && Storage::disk('local')->exists($plan->technical_opinion_file_path),
            404
        );

        return Storage::disk('local')->download(
            $plan->technical_opinion_file_path,
            $plan->technical_opinion_file_name ?? 'parecer-tecnico'
        );
    }

    public function downloadNr1Report(Company $company, Survey $survey, string $type): StreamedResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        abort_unless(in_array($type, [
            SurveyNr1Report::TYPE_EXECUTIVE,
            SurveyNr1Report::TYPE_TECHNICAL_REFERRAL,
        ], true), 404);

        $report = SurveyNr1Report::query()
            ->where('company_id', $company->id)
            ->where('survey_id', $survey->id)
            ->where('type', $type)
            ->first();

        abort_unless(
            $report
            && $report->file_path
            && Storage::disk('local')->exists($report->file_path),
            404
        );

        return Storage::disk('local')->download(
            $report->file_path,
            $report->file_name ?? $type
        );
    }

    private function syncNr1ReportUpload(
        Request $request,
        Company $company,
        Survey $survey,
        string $type,
        string $fileField,
        string $removeField
    ): void {
        $uploadedFile = $request->file($fileField);
        $removeFile = $request->boolean($removeField);

        if ($uploadedFile === null && ! $removeFile) {
            return;
        }

        $report = SurveyNr1Report::query()->firstOrCreate(
            [
                'survey_id' => $survey->id,
                'type' => $type,
            ],
            [
                'company_id' => $company->id,
            ]
        );

        $existingPath = $report->file_path;

        if ($uploadedFile !== null) {
            $newPath = $uploadedFile->store('survey-nr1-reports/'.$survey->id.'/'.$type, 'local');
            $report->update([
                'file_path' => $newPath,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'published_at' => now(),
                'uploaded_by' => $request->user()->id,
            ]);

            if ($existingPath && $existingPath !== $newPath && Storage::disk('local')->exists($existingPath)) {
                Storage::disk('local')->delete($existingPath);
            }

            return;
        }

        if ($removeFile) {
            if ($existingPath && Storage::disk('local')->exists($existingPath)) {
                Storage::disk('local')->delete($existingPath);
            }

            $report->update([
                'file_path' => null,
                'file_name' => null,
                'published_at' => null,
                'uploaded_by' => null,
            ]);
        }
    }
}
