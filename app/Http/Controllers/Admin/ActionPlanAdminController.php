<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiAnalysisJob;
use App\Models\ActionPlan;
use App\Models\AiAnalysis;
use App\Models\AiSetting;
use App\Models\Company;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\PsychosocialMeasure;
use App\Models\Survey;
use App\Models\SurveyNr1Report;
use App\Services\ActionMeasuresGenerator;
use App\Services\ActionPlanGenerator;
use App\Services\PsychosocialMeasureService;
use App\Services\Nr1AiAnalyzer;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\Psychosocial\ReviewRoundService;
use App\Services\ReportGenerator;
use App\Services\SurveyResultCalculator;
use App\Services\SurveyResultsPresenter;
use App\Support\HtmlSanitizer;
use App\Support\Nr1GroupPrivacy;
use App\Support\Nr1RiskScenarioResolver;
use App\Support\Nr1Shell\Nr1Shell;
use App\Support\PsychosocialMeasurePresenter;
use App\Support\TechnicalOpinionDisclaimerStripper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActionPlanAdminController extends Controller
{
    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless($survey->company_id === $company->id, 404);
    }

    public function pdf(Company $company, Survey $survey, ReportGenerator $generator): SymfonyResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        return $generator
            ->actionPlanPdf($survey)
            ->stream('plano-de-acao-'.$survey->id.'.pdf');
    }

    public function documentPdf(Request $request, Company $company, Survey $survey, ReportGenerator $generator): SymfonyResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $overrides = [];

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'technical_opinion' => ['nullable', 'string'],
                'is_draft' => ['sometimes', 'boolean'],
            ]);

            $overrides = [
                'technical_opinion' => HtmlSanitizer::sanitizeRichText($data['technical_opinion'] ?? null),
                'is_draft' => array_key_exists('is_draft', $data)
                    ? (bool) $data['is_draft']
                    : true,
            ];
        }

        return $generator
            ->actionPlanDocumentPdf($survey, $overrides)
            ->stream('plano-de-acao-'.$survey->id.'.pdf');
    }

    /**
     * Dados do Laudo («Parecer e publicação» no painel NR-1).
     *
     * @return array<string, mixed>
     */
    public function laudoProps(Company $company, Survey $survey): array
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
            ->first();

        $nr1Reports = $this->nr1ReportsPayload($company, $survey);

        return array_merge($presented, [
            'company' => $company->only(['id', 'name']),
            'riskScenario' => Nr1RiskScenarioResolver::forSurvey($survey),
            'riskScenarioLabel' => Nr1RiskScenarioResolver::scenarioConfig(
                Nr1RiskScenarioResolver::forSurvey($survey) ?? 'green'
            )['short_label'] ?? null,
            'nr1Reports' => $nr1Reports,
            'publication' => [
                'requires_publication' => (bool) $survey->requires_publication,
                'published_at' => $survey->published_at?->format('d/m/Y H:i'),
                // Campanha V11: documentos e publicação vivem na Central de documentos (fluxo de revisão).
                'uses_review_flow' => $survey->usesReviewFlow(),
                'documents_url' => $survey->usesReviewFlow()
                    ? Nr1Shell::campaignUrl('documentos', $survey)
                    : null,
            ],
            // Resumo executivo permanece no backend (V11 / rotas), mas a UI do parecer legado usa o relatório técnico.
            'executiveBrief' => null,
            'technicalReport' => $this->legacyDocumentUiPayload(
                $company,
                $survey,
                PsychosocialDocument::TYPE_TECHNICAL_REPORT,
                app(PsychosocialDocumentService::class)->currentVersionSummary(
                    $survey,
                    PsychosocialDocument::TYPE_TECHNICAL_REPORT
                ),
            ),
            'pgrSubsidy' => $this->legacyDocumentUiPayload(
                $company,
                $survey,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
                app(PsychosocialDocumentService::class)->currentVersionSummary(
                    $survey,
                    PsychosocialDocument::TYPE_PGR_SUBSIDY
                ),
            ),
            // Mantido para compatibilidade; a UI do parecer legado não exibe o dossiê.
            'psychosocialDocuments' => app(PsychosocialDocumentService::class)->documentsSummary($survey),
            'plan' => $plan ? [
                'id' => $plan->id,
                'admin_published_at' => $plan->admin_published_at?->format('d/m/Y H:i'),
                'technical_opinion' => TechnicalOpinionDisclaimerStripper::strip($plan->technical_opinion) ?? '',
                'technical_opinion_file_name' => $plan->technical_opinion_file_name,
                'technical_opinion_file_url' => $plan->technical_opinion_file_path
                    ? route('admin.companies.surveys.technical-opinion-file.download', [$company, $survey])
                    : null,
            ] : null,
            'technical_opinion' => TechnicalOpinionDisclaimerStripper::strip($plan?->technical_opinion) ?? '',
            'aiEnabled' => $aiEnabled,
            'aiAnalysis' => $latestAi ? [
                'content' => $latestAi->content,
            ] : null,
            'aiAnalysisPending' => $aiAnalysisPending,
            'technicalOpinionAi' => $latestTechnicalOpinionAi ? [
                'content' => TechnicalOpinionDisclaimerStripper::strip($latestTechnicalOpinionAi->content) ?? '',
            ] : null,
            'technicalOpinionAiPending' => $technicalOpinionAiPending,
            'aiGeneratePostUrl' => url('/admin/companies/'.$company->getKey().'/surveys/'.$survey->getKey().'/ai-analysis'),
            'technicalOpinionGeneratePostUrl' => url('/admin/companies/'.$company->getKey().'/surveys/'.$survey->getKey().'/technical-opinion'),
            'actionMeasures' => $this->parecerMeasures($company, $survey),
        ]);
    }

    /**
     * Mesmas ações do cadastro de medidas, para editar no próprio parecer.
     *
     * @return array<string, mixed>
     */
    private function parecerMeasures(Company $company, Survey $survey): array
    {
        $rows = PsychosocialMeasure::query()
            ->where('survey_id', $survey->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'measures' => $rows->map(fn (PsychosocialMeasure $measure): array => [
                'id' => $measure->id,
                'factor_label' => $measure->factor_label,
                'measure_description' => $measure->measure_description,
                'status' => $measure->status,
                'status_label' => $measure->statusLabel(),
                'origin' => $measure->origin,
                'plan' => PsychosocialMeasurePresenter::present($measure),
            ])->all(),
            'statuses' => PsychosocialMeasure::STATUS_LABELS,
            'canEditSuggestion' => ! ($survey->requires_publication && $survey->published_at !== null),
            'dueOrigins' => PsychosocialMeasureService::DUE_ORIGINS,
            'measuresAi' => [
                'generated_at' => $survey->action_measures_generated_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                'pending' => Cache::has(ActionMeasuresGenerator::pendingCacheKey($survey->id)),
                'refresh_url' => route('admin.companies.surveys.measures.refresh-ai', [$company->id, $survey->id]),
            ],
        ];
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

        return redirect(Nr1Shell::parecerUrl($survey))
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

        if (! Nr1GroupPrivacy::hasMinimumSample($survey)) {
            return back()->with('error', 'Amostra abaixo do grupo mínimo: a Mia não analisa esta campanha.');
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

        return back(fallback: Nr1Shell::parecerUrl($survey))->with('success', $successMessage);
    }

    /**
     * Recálculo pela Talents. Com a campanha publicada (V11), abre a revisão e gera novas versões em edição: o que a
     * empresa vê continua sendo a versão publicada até a próxima publicação.
     */
    public function recalculate(Request $request, Company $company, Survey $survey, SurveyResultCalculator $calculator, ReviewRoundService $rounds): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $calculator->recalculate($survey);

        if (! $survey->usesReviewFlow() || $survey->published_at === null) {
            return back(fallback: Nr1Shell::campaignUrl('resultados', $survey))->with('success', 'Resultados recalculados.');
        }

        try {
            $current = $rounds->currentRound($survey);
            $round = $current === null || $current->isPublished() ? $rounds->start($survey, $request->user()) : $current;
            $rounds->regenerate($round, $request->user());
        } catch (PsychosocialDocumentException $exception) {
            return back(fallback: Nr1Shell::campaignUrl('documentos', $survey))->with('error', 'Resultados recalculados, mas a nova versão não foi gerada: '.$exception->getMessage());
        }

        return back(fallback: Nr1Shell::campaignUrl('documentos', $survey))->with('success', 'Resultados recalculados. Nova versão dos documentos em edição; a publicada não mudou.');
    }

    public function update(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $data = $request->validate([
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

        $technicalOpinion = TechnicalOpinionDisclaimerStripper::strip(
            HtmlSanitizer::sanitizeRichText($data['technical_opinion'] ?? null)
        );

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

        $hasPublishableContent = ($technicalOpinion !== null && trim(strip_tags($technicalOpinion)) !== '')
            || $newFilePath !== null;

        if ($hasPublishableContent && ! $survey->usesReviewFlow()) {
            $this->assertLegacyTechnicalReportValidatedForPublish($survey);
        }

        DB::transaction(function () use ($plan, $technicalOpinion, $hasPublishableContent, $newFilePath, $newFileName) {
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

        return redirect(Nr1Shell::parecerUrl($survey))
            ->with('success', $hasPublishableContent
                ? ($survey->usesReviewFlow() ? 'Parecer técnico salvo. Ele fica visível só para a Talents e a PASSEG.' : 'Parecer técnico salvo e disponibilizado para a empresa.')
                : 'Conteúdo removido — a empresa não verá o parecer até você publicar novamente.');
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

    /**
     * Props de UI do resumo / AEP-PGR no parecer legado (sem dossiê).
     *
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>
     */
    private function legacyDocumentUiPayload(
        Company $company,
        Survey $survey,
        string $type,
        ?array $summary,
    ): array {
        $base = $summary ?? [
            'type' => $type,
            'label' => PsychosocialDocumentService::label($type),
            'version_number' => null,
            'kind' => null,
            'seal' => null,
            'pdf_status' => null,
            'released_at' => null,
            'ready' => false,
            'validated' => false,
            'validatable' => false,
        ];

        $companyId = $company->id;
        $surveyId = $survey->id;

        [$releaseRoute, $releaseParams, $validateRoute] = match ($type) {
            PsychosocialDocument::TYPE_EXECUTIVE_BRIEF => [
                'admin.companies.surveys.executive-brief.release',
                [$companyId, $surveyId],
                'admin.companies.surveys.executive-brief.validate',
            ],
            PsychosocialDocument::TYPE_TECHNICAL_REPORT => [
                'admin.companies.surveys.technical-report.release',
                [$companyId, $surveyId],
                'admin.companies.surveys.technical-report.validate',
            ],
            default => [
                'admin.companies.surveys.pgr-subsidy.release',
                [$companyId, $surveyId],
                null,
            ],
        };

        $downloadRoute = $type === PsychosocialDocument::TYPE_EXECUTIVE_BRIEF
            ? 'admin.companies.surveys.executive-brief.download'
            : 'admin.companies.surveys.psychosocial-documents.download';

        $previewRoute = $type === PsychosocialDocument::TYPE_EXECUTIVE_BRIEF
            ? 'admin.companies.surveys.executive-brief.preview'
            : 'admin.companies.surveys.psychosocial-documents.preview';

        $downloadParams = $type === PsychosocialDocument::TYPE_EXECUTIVE_BRIEF
            ? [$companyId, $surveyId]
            : [$companyId, $surveyId, $type];

        $previewParams = $type === PsychosocialDocument::TYPE_EXECUTIVE_BRIEF
            ? [$companyId, $surveyId]
            : [$companyId, $surveyId, $type];

        return array_merge($base, [
            'ready' => (bool) ($base['ready'] ?? false),
            'validated' => (bool) ($base['validated'] ?? false),
            'validatable' => (bool) ($base['validatable'] ?? false),
            'urls' => [
                'release' => route($releaseRoute, $releaseParams),
                'download' => route($downloadRoute, $downloadParams),
                'preview' => route($previewRoute, $previewParams),
                'validate' => $validateRoute !== null
                    ? route($validateRoute, [$companyId, $surveyId])
                    : null,
            ],
        ]);
    }

    /**
     * No modelo anterior, publicar o parecer exige o relatório técnico já no painel da empresa.
     * (O resumo executivo ficou inativo na UI do parecer legado.)
     */
    private function assertLegacyTechnicalReportValidatedForPublish(Survey $survey): void
    {
        $version = app(PsychosocialDocumentService::class)->currentVersion(
            $survey,
            PsychosocialDocument::TYPE_TECHNICAL_REPORT
        );

        if ($version !== null && $version->pdf_status === PsychosocialDocumentVersion::PDF_READY) {
            return;
        }

        throw ValidationException::withMessages([
            'technical_opinion' => 'Para publicar o parecer, publique o relatório técnico e plano de ação nesta campanha.',
        ]);
    }
}
