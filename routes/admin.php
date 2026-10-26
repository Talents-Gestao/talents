<?php

use App\Http\Controllers\Admin\ActionPlanAdminController;
use App\Http\Controllers\Admin\ExecutiveBriefAdminController;
use App\Http\Controllers\Admin\PsychosocialDocumentAdminController;
use App\Http\Controllers\Admin\SurveyCampaignRegistrationAdminController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\Commercial\ContractController as CommercialContractController;
use App\Http\Controllers\Admin\Commercial\ProductController as CommercialProductController;
use App\Http\Controllers\Admin\Commercial\ContractTemplateController as CommercialContractTemplateController;
use App\Http\Controllers\Admin\Commercial\PreviewController as CommercialPreviewController;
use App\Http\Controllers\Admin\Commercial\ProposalController as CommercialProposalController;
use App\Http\Controllers\Admin\Commercial\SettingsController as CommercialSettingsController;
use App\Http\Controllers\Admin\FeedbackCompanySelectController;
use App\Http\Controllers\Admin\FeriasCompanySelectController;
use App\Http\Controllers\Admin\DesligamentoCompanySelectController;
use App\Http\Controllers\Admin\ComplaintCompanySelectController;
use App\Http\Controllers\Admin\SuggestionCompanySelectController;
use App\Http\Controllers\Client\Feedback\FeedbackDashboardController;
use App\Http\Controllers\Client\Feedback\FeedbackEmployeeController;
use App\Http\Controllers\Client\Feedback\FeedbackSessionController;
use App\Http\Controllers\Client\Leaves\EmployeeLeaveController;
use App\Http\Controllers\Client\Offboarding\ExitInterviewController;
use App\Http\Controllers\Client\ComplaintController;
use App\Http\Controllers\Client\SuggestionController;
use App\Http\Controllers\Admin\Finance\BankAccountController as FinanceBankAccountController;
use App\Http\Controllers\Admin\Finance\CommissionController as FinanceCommissionController;
use App\Http\Controllers\Admin\Finance\FinanceDashboardController;
use App\Http\Controllers\Admin\Finance\InstallmentController as FinanceInstallmentController;
use App\Http\Controllers\Admin\Finance\PayableController as FinancePayableController;
use App\Http\Controllers\Admin\Finance\PaymentMethodController as FinancePaymentMethodController;
use App\Http\Controllers\Admin\Finance\ReceivableController as FinanceReceivableController;
use App\Http\Controllers\Admin\Finance\SaleController as FinanceSaleController;
use App\Http\Controllers\Admin\ClientEvaluationController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanySurveyController;
use App\Http\Controllers\Admin\PurposeMapCampaignController;
use App\Http\Controllers\Admin\Commercial\CommercialProcessController;
use App\Http\Controllers\Admin\InternalRegulationController;
use App\Http\Controllers\Admin\MonthlyHighlightController;
use App\Http\Controllers\Admin\CompanyEmployeeController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\ClosedContractsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BusinessDiagnosticController;
use App\Http\Controllers\Admin\LandingInterestSubmissionController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\MethodologyCompanyController;
use App\Http\Controllers\Admin\MethodologyController as AdminMethodologyController;
use App\Http\Controllers\Admin\MethodologyFormTemplateController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlatformCompanyController;
use App\Http\Controllers\Admin\PontoEspelhoController;
use App\Http\Controllers\Admin\PontoPanelController;
use App\Http\Controllers\Admin\RhidPortfolioController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SolidesCurriculumController;
use App\Http\Controllers\Admin\SolidesSettingsController;
use App\Http\Controllers\Admin\HiringProcessController;
use App\Http\Controllers\Admin\CompanyNoticeController as AdminCompanyNoticeController;
use App\Http\Controllers\Admin\StrategicCalendarController as AdminStrategicCalendarController;
use App\Http\Controllers\Admin\SurveyTemplateController;
use App\Http\Controllers\Admin\Tasks\BoardActivationController as TasksBoardActivationController;
use App\Http\Controllers\Admin\Tasks\ProcessTemplateController as TasksProcessTemplateController;
use App\Http\Controllers\Admin\Tasks\TaskBoardAttachmentController;
use App\Http\Controllers\Admin\Tasks\TaskBoardCardController;
use App\Http\Controllers\Admin\Tasks\TaskBoardChecklistController;
use App\Http\Controllers\Admin\Tasks\TaskBoardChecklistItemController;
use App\Http\Controllers\Admin\Tasks\TaskBoardCommentController;
use App\Http\Controllers\Admin\Tasks\TaskBoardController as TasksTaskBoardController;
use App\Http\Controllers\Admin\Tasks\TaskBoardFavoriteController;
use App\Http\Controllers\Admin\Tasks\TaskBoardLabelController;
use App\Http\Controllers\Admin\Tasks\TaskBoardListController;
use App\Http\Controllers\Admin\Tasks\TaskBoardMemberController;
use App\Http\Controllers\Admin\Tasks\TemplateCardController as TasksTemplateCardController;
use App\Http\Controllers\Admin\Tasks\TemplateListController as TasksTemplateListController;
use App\Http\Controllers\Admin\Interviews\InterviewController;
use App\Http\Controllers\Admin\Meetings\MeetingController;
use App\Http\Controllers\Admin\Interviews\InterviewQuestionnaireController;
use App\Http\Controllers\Admin\Interviews\InterviewReportController;
use App\Http\Controllers\NewsFeedController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('em-breve/{module}', [ComingSoonController::class, 'show'])
        ->where('module', '[a-z0-9\-]+')
        ->name('coming-soon.show');

    Route::middleware('admin.can:dashboard')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::patch('/dashboard/meta-mensal', [AdminDashboardController::class, 'updateMonthlyGoal'])
            ->name('dashboard.monthly-goal.update');
    });

    Route::middleware('admin.can:empresa_talents')->group(function () {
        Route::get('empresa-talents', [PlatformCompanyController::class, 'edit'])->name('empresa-talents.edit');
        Route::put('empresa-talents', [PlatformCompanyController::class, 'update'])->name('empresa-talents.update');
    });

    Route::middleware('admin.can:landing_interest')->group(function () {
        Route::get('/interessados-landing', [LandingInterestSubmissionController::class, 'index'])
            ->name('landing-interest.index');
        Route::post('/interessados-landing', [LandingInterestSubmissionController::class, 'store'])
            ->name('landing-interest.store');
        Route::patch('/interessados-landing/{submission}', [LandingInterestSubmissionController::class, 'update'])
            ->name('landing-interest.update');
        Route::delete('/interessados-landing/{submission}', [LandingInterestSubmissionController::class, 'destroy'])
            ->name('landing-interest.destroy');
    });

    Route::middleware('admin.can:strategic_calendar')->group(function () {
        Route::post('calendario-estrategico/{item}/anexos', [AdminStrategicCalendarController::class, 'attachmentsStore'])
            ->name('strategic-calendar.attachments.store');
        Route::delete('calendario-estrategico/anexos/{attachment}', [AdminStrategicCalendarController::class, 'attachmentDestroy'])
            ->name('strategic-calendar.attachment.destroy');
        Route::get('calendario-estrategico/anexos/{attachment}/download', [AdminStrategicCalendarController::class, 'attachmentDownload'])
            ->name('strategic-calendar.attachment-download');
        Route::patch('calendario-estrategico/{item}/data', [AdminStrategicCalendarController::class, 'updateDate'])
            ->name('strategic-calendar.update-date');
        Route::resource('calendario-estrategico', AdminStrategicCalendarController::class)
            ->except(['show'])
            ->names([
                'index' => 'strategic-calendar.index',
                'create' => 'strategic-calendar.create',
                'store' => 'strategic-calendar.store',
                'edit' => 'strategic-calendar.edit',
                'update' => 'strategic-calendar.update',
                'destroy' => 'strategic-calendar.destroy',
            ])
            ->parameters(['calendario-estrategico' => 'item']);

        Route::get('avisos', [AdminCompanyNoticeController::class, 'index'])->name('notices.index');
        Route::get('avisos/criar', [AdminCompanyNoticeController::class, 'create'])->name('notices.create');
        Route::post('avisos', [AdminCompanyNoticeController::class, 'store'])->name('notices.store');
    });

    // Sino de avisos internos da Talents — acessível a qualquer administrador.
    Route::get('avisos/recentes', [AdminCompanyNoticeController::class, 'recent'])->name('notices.recent');
    Route::get('avisos/{notice}/abrir', [AdminCompanyNoticeController::class, 'open'])->name('notices.open');
    Route::post('avisos/{notice}/lido', [AdminCompanyNoticeController::class, 'markRead'])->name('notices.mark-read');
    Route::post('avisos/marcar-todos-lidos', [AdminCompanyNoticeController::class, 'markAllRead'])->name('notices.mark-all-read');
    Route::delete('avisos/{notice}', [AdminCompanyNoticeController::class, 'destroy'])->name('notices.destroy');
    Route::post('avisos/excluir-todos', [AdminCompanyNoticeController::class, 'destroyAll'])->name('notices.destroy-all');

    Route::get('noticias', NewsFeedController::class)->name('news.feed');

    Route::middleware('admin.can:companies')->group(function () {
        Route::get('avaliacoes-clientes', [ClientEvaluationController::class, 'index'])
            ->name('client-evaluations.index');
        Route::get('avaliacoes-clientes/criar', [ClientEvaluationController::class, 'create'])
            ->name('client-evaluations.create');
        Route::post('avaliacoes-clientes', [ClientEvaluationController::class, 'store'])
            ->name('client-evaluations.store');
        Route::get('avaliacoes-clientes/{evaluation}', [ClientEvaluationController::class, 'show'])
            ->name('client-evaluations.show');
        Route::delete('avaliacoes-clientes/{evaluation}', [ClientEvaluationController::class, 'destroy'])
            ->name('client-evaluations.destroy');
        Route::post('avaliacoes-clientes/{evaluation}/email', [ClientEvaluationController::class, 'sendEmail'])
            ->name('client-evaluations.email');

        Route::get('colaboradores/lookup-cep', [CompanyEmployeeController::class, 'lookupCep'])
            ->name('colaboradores.lookup-cep');
        Route::resource('colaboradores', CompanyEmployeeController::class)
            ->parameters(['colaboradores' => 'employee']);

        Route::resource('regulamento-interno', InternalRegulationController::class)
            ->parameters(['regulamento-interno' => 'regulamento_interno']);
        Route::get('regulamento-interno/{regulamento_interno}/download', [InternalRegulationController::class, 'download'])
            ->name('regulamento-interno.download');

        Route::resource('destaques-mes', MonthlyHighlightController::class)
            ->parameters(['destaques-mes' => 'destaque_mes']);

        Route::get('companies/{company}/rhid-metrics', [RhidPortfolioController::class, 'companyMetrics'])
            ->name('companies.rhid-metrics');
        Route::get('companies/lookup-cnpj', [CompanyController::class, 'lookupCnpj'])->name('companies.lookup-cnpj');
        Route::get('companies/{company}/surveys/{survey}/action-plan', [ActionPlanAdminController::class, 'edit'])
            ->name('companies.surveys.action-plan.edit');
        Route::get('companies/{company}/surveys/{survey}/cadastro-tecnico', [SurveyCampaignRegistrationAdminController::class, 'edit'])
            ->name('companies.surveys.registration.edit');
        Route::put('companies/{company}/surveys/{survey}/cadastro-tecnico', [SurveyCampaignRegistrationAdminController::class, 'update'])
            ->name('companies.surveys.registration.update');
        Route::post('companies/{company}/surveys/{survey}/resumo-executivo/liberar', [ExecutiveBriefAdminController::class, 'release'])
            ->name('companies.surveys.executive-brief.release');
        Route::get('companies/{company}/surveys/{survey}/resumo-executivo/download', [ExecutiveBriefAdminController::class, 'download'])
            ->name('companies.surveys.executive-brief.download');
        Route::post('companies/{company}/surveys/{survey}/subsidio-pgr/liberar', [PsychosocialDocumentAdminController::class, 'releaseSubsidy'])
            ->name('companies.surveys.pgr-subsidy.release');
        Route::post('companies/{company}/surveys/{survey}/dossie/liberar', [PsychosocialDocumentAdminController::class, 'releaseDossier'])
            ->name('companies.surveys.evidence-dossier.release');
        Route::get('companies/{company}/surveys/{survey}/dossie/anexos/{attachment}', [PsychosocialDocumentAdminController::class, 'downloadAttachment'])
            ->name('companies.surveys.evidence-dossier.attachments.download');
        Route::get('companies/{company}/surveys/{survey}/medidas', [\App\Http\Controllers\Admin\PsychosocialMeasureAdminController::class, 'edit'])
            ->name('companies.surveys.measures.edit');
        Route::post('companies/{company}/surveys/{survey}/medidas', [\App\Http\Controllers\Admin\PsychosocialMeasureAdminController::class, 'store'])
            ->name('companies.surveys.measures.store');
        Route::patch('companies/{company}/surveys/{survey}/medidas/{measure}/sugestao', [\App\Http\Controllers\Admin\PsychosocialMeasureAdminController::class, 'updateSuggestion'])
            ->name('companies.surveys.measures.suggestion');
        Route::patch('companies/{company}/surveys/{survey}/medidas/{measure}', [\App\Http\Controllers\Admin\PsychosocialMeasureAdminController::class, 'updateStatus'])
            ->name('companies.surveys.measures.status');
        Route::get('companies/{company}/surveys/{survey}/documentos/{type}/download', [PsychosocialDocumentAdminController::class, 'download'])
            ->whereIn('type', ['executive_brief', 'pgr_subsidy', 'evidence_dossier'])
            ->name('companies.surveys.psychosocial-documents.download');
        Route::post('companies/{company}/surveys/{survey}/documentos/{type}/validacao-informada', [PsychosocialDocumentAdminController::class, 'validateInformed'])
            ->whereIn('type', ['executive_brief', 'pgr_subsidy', 'evidence_dossier'])
            ->name('companies.surveys.psychosocial-documents.validate-informed');
        Route::post('companies/{company}/surveys/{survey}/documentos/{type}/compartilhar', [\App\Http\Controllers\Admin\DocumentShareAdminController::class, 'store'])
            ->whereIn('type', ['executive_brief', 'pgr_subsidy', 'evidence_dossier'])
            ->name('companies.surveys.psychosocial-documents.share');
        // Campanha NR-1 V11: treinamentos contratados por público e total de elegíveis (só a Talents).
        Route::get('companies/{company}/surveys/{survey}/campanha-nr1', [\App\Http\Controllers\Admin\CampaignAdminController::class, 'show'])
            ->name('companies.surveys.campaign.show');
        Route::post('companies/{company}/surveys/{survey}/campanha-nr1/contratar', [\App\Http\Controllers\Admin\CampaignAdminController::class, 'contract'])
            ->name('companies.surveys.campaign.contract');
        Route::post('companies/{company}/surveys/{survey}/campanha-nr1/remover-contratacao', [\App\Http\Controllers\Admin\CampaignAdminController::class, 'uncontract'])
            ->name('companies.surveys.campaign.uncontract');
        Route::post('companies/{company}/surveys/{survey}/campanha-nr1/elegiveis', [\App\Http\Controllers\Admin\CampaignAdminController::class, 'updateEligible'])
            ->name('companies.surveys.campaign.eligible');
        // Painel de resultados V11 para a consultoria e Revisão interna (Caderno §9): nada disso chega à empresa.
        Route::get('companies/{company}/surveys/{survey}/resultados-psicossociais', [\App\Http\Controllers\Admin\PsychosocialResultsAdminController::class, 'show'])
            ->name('companies.surveys.results.show');
        Route::post('companies/{company}/surveys/{survey}/revisao-interna', [\App\Http\Controllers\Admin\PsychosocialResultsAdminController::class, 'storeInternalReview'])
            ->name('companies.surveys.internal-review.store');
        // Fluxo de revisão dos dois documentos finais (campanha V11): Em edição → … → Publicado.
        Route::get('companies/{company}/surveys/{survey}/documentos', [\App\Http\Controllers\Admin\ReviewRoundAdminController::class, 'index'])
            ->name('companies.surveys.documents.index');
        Route::post('companies/{company}/surveys/{survey}/documentos/revisao', [\App\Http\Controllers\Admin\ReviewRoundAdminController::class, 'start'])
            ->name('companies.surveys.documents.start');
        Route::post('companies/{company}/surveys/{survey}/documentos/exigir-revisao', [\App\Http\Controllers\Admin\ReviewRoundAdminController::class, 'updateReviewRequirement'])
            ->name('companies.surveys.documents.review-requirement');
        Route::post('companies/{company}/surveys/{survey}/documentos/revisao/{round}/gerar', [\App\Http\Controllers\Admin\ReviewRoundAdminController::class, 'regenerate'])
            ->whereNumber('round')->name('companies.surveys.documents.regenerate');
        Route::post('companies/{company}/surveys/{survey}/documentos/revisao/{round}/enviar', [\App\Http\Controllers\Admin\ReviewRoundAdminController::class, 'send'])
            ->whereNumber('round')->name('companies.surveys.documents.send');
        Route::post('companies/{company}/surveys/{survey}/documentos/revisao/{round}/publicar', [\App\Http\Controllers\Admin\ReviewRoundAdminController::class, 'publish'])
            ->whereNumber('round')->name('companies.surveys.documents.publish');
        Route::get('companies/{company}/surveys/{survey}/documentos/versoes/{version}/download', [\App\Http\Controllers\Admin\ReviewRoundAdminController::class, 'download'])
            ->whereNumber('version')->name('companies.surveys.documents.download');
        Route::post('companies/{company}/surveys/{survey}/publicar', [\App\Http\Controllers\Admin\SurveyPublicationAdminController::class, 'publish'])
            ->name('companies.surveys.publication.store');
        Route::delete('companies/{company}/document-shares/{share}', [\App\Http\Controllers\Admin\DocumentShareAdminController::class, 'revoke'])
            ->name('companies.document-shares.revoke');
        Route::get('companies/{company}/surveys/{survey}/action-plan/pdf', [ActionPlanAdminController::class, 'pdf'])
            ->name('companies.surveys.action-plan.pdf');
        Route::match(['get', 'post'], 'companies/{company}/surveys/{survey}/action-plan/document-pdf', [ActionPlanAdminController::class, 'documentPdf'])
            ->name('companies.surveys.action-plan.document-pdf');
        Route::delete('companies/{company}/surveys/{survey}', [CompanySurveyController::class, 'destroy'])
            ->name('companies.surveys.destroy');
        Route::put('companies/{company}/surveys/{survey}/action-plan', [ActionPlanAdminController::class, 'update'])
            ->name('companies.surveys.action-plan.update');
        Route::post('companies/{company}/surveys/{survey}/action-plan/generate-suggested', [ActionPlanAdminController::class, 'generateSuggestedPlan'])
            ->name('companies.surveys.action-plan.generate-suggested');
        Route::post('companies/{company}/surveys/{survey}/ai-analysis', [ActionPlanAdminController::class, 'generateAiAnalysis'])
            ->name('companies.surveys.ai-analysis');
        Route::post('companies/{company}/surveys/{survey}/technical-opinion', [ActionPlanAdminController::class, 'generateTechnicalOpinion'])
            ->name('companies.surveys.technical-opinion');
        Route::get('companies/{company}/surveys/{survey}/technical-opinion-file', [ActionPlanAdminController::class, 'downloadTechnicalOpinionFile'])
            ->name('companies.surveys.technical-opinion-file.download');
        Route::get('companies/{company}/surveys/{survey}/nr1-reports/{type}', [ActionPlanAdminController::class, 'downloadNr1Report'])
            ->name('companies.surveys.nr1-reports.download');
        Route::get('companies/{company}/surveys/{survey}/dados-brutos/csv', [\App\Http\Controllers\Admin\SurveyRawExportController::class, 'csv'])
            ->middleware('admin.can:nr1_dados_brutos,view')
            ->name('companies.surveys.raw-export.csv');
        Route::get('mapa-proposito', [PurposeMapCampaignController::class, 'index'])
            ->name('mapa-proposito.index');
        Route::get('mapa-proposito/resultados', [PurposeMapCampaignController::class, 'show'])
            ->name('mapa-proposito.dashboard');
        Route::post('mapa-proposito/analisar', [PurposeMapCampaignController::class, 'analyze'])
            ->name('mapa-proposito.analyze');
        Route::get('companies/{company}/users', [CompanyUserController::class, 'index'])->name('companies.users.index');
        Route::get('companies/{company}/users/create', [CompanyUserController::class, 'create'])->name('companies.users.create');
        Route::post('companies/{company}/users', [CompanyUserController::class, 'store'])->name('companies.users.store');
        Route::post('companies/{company}/users/{user}/resend-invitation', [CompanyUserController::class, 'resendInvitation'])->name('companies.users.resend-invitation');
        Route::get('companies/{company}/users/{user}/edit', [CompanyUserController::class, 'edit'])->name('companies.users.edit');
        Route::match(['put', 'patch'], 'companies/{company}/users/{user}', [CompanyUserController::class, 'update'])->name('companies.users.update');
        Route::delete('companies/{company}/users/{user}', [CompanyUserController::class, 'destroy'])->name('companies.users.destroy');
        Route::post('companies/{company}/resend-invitation', [CompanyController::class, 'resendInvitation'])->name('companies.resend-invitation');
        Route::resource('companies', CompanyController::class);
        Route::post('companies/{company}/templates/{template}', [CompanyController::class, 'attachTemplate'])->name('companies.templates.attach');
        Route::delete('companies/{company}/templates/{template}', [CompanyController::class, 'detachTemplate'])->name('companies.templates.detach');
        Route::post('companies/{company}/methodology-templates/{template}', [MethodologyCompanyController::class, 'attachTemplate'])->name('companies.methodology-templates.attach');
        Route::delete('companies/{company}/methodology-templates/{template}', [MethodologyCompanyController::class, 'detachTemplate'])->name('companies.methodology-templates.detach');
    });

    Route::middleware('admin.can:companies_diagnostico')->group(function () {
        Route::resource('diagnostico-empresarial', BusinessDiagnosticController::class)
            ->parameters(['diagnostico-empresarial' => 'diagnostico_empresarial']);
    });

    Route::middleware('admin.can:companies_contratos_fechados')->group(function () {
        Route::get('contratos-fechados', [ClosedContractsController::class, 'index'])
            ->name('contratos-fechados.index');
    });

    Route::middleware('admin.can:nr1_oportunidades,view')->prefix('oportunidades-nr1')->name('nr1-opportunities.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Nr1OpportunityController::class, 'index'])->name('index');
        Route::get('fila/{opportunity}', [\App\Http\Controllers\Admin\Nr1OpportunityController::class, 'showOpportunity'])
            ->whereNumber('opportunity')->name('opportunity');
        Route::get('{survey}', [\App\Http\Controllers\Admin\Nr1OpportunityController::class, 'show'])
            ->whereNumber('survey')->name('show');
    });

    // Trabalhar a fila (assumir, estado, próxima ação) exige edição no módulo; só ver não basta.
    Route::middleware('admin.can:nr1_oportunidades,edit')->prefix('oportunidades-nr1/fila')->name('nr1-opportunities.')->group(function () {
        Route::post('{opportunity}/assumir', [\App\Http\Controllers\Admin\Nr1OpportunityController::class, 'assume'])->whereNumber('opportunity')->name('assume');
        Route::post('{opportunity}/estado', [\App\Http\Controllers\Admin\Nr1OpportunityController::class, 'changeStatus'])->whereNumber('opportunity')->name('status');
        Route::post('{opportunity}/proxima-acao', [\App\Http\Controllers\Admin\Nr1OpportunityController::class, 'nextAction'])->whereNumber('opportunity')->name('next-action');
    });

    Route::middleware('admin.can:rhid')->get('rhid/{path?}', function () {
        return redirect()->route('admin.ponto.index');
    })->where('path', '.*');

    Route::middleware('admin.can:rhid')->prefix('ponto')->name('ponto.')->group(function () {
        Route::get('/', [PontoPanelController::class, 'index'])->name('index');
        Route::get('companies/{company}/metrics', [PontoPanelController::class, 'companyMetrics'])->name('companies.metrics');
        Route::get('companies/{company}/last-punches', [PontoPanelController::class, 'lastPunches'])->name('companies.last-punches');
        Route::get('companies/{company}/people', [PontoPanelController::class, 'people'])->name('companies.people');
        Route::get('companies/{company}/schedule-adherence', [PontoPanelController::class, 'scheduleAdherence'])
            ->name('companies.schedule-adherence');
        Route::get('companies/{company}/justification-types', [PontoPanelController::class, 'justificationTypes'])
            ->name('companies.justification-types');
        Route::post('companies/{company}/justifications/list', [PontoPanelController::class, 'listJustifications'])
            ->name('companies.justifications.list');
        Route::post('companies/{company}/justifications', [PontoPanelController::class, 'storeJustification'])
            ->name('companies.justifications.store');
        Route::put('companies/{company}/justifications/{id}', [PontoPanelController::class, 'updateJustification'])
            ->name('companies.justifications.update')
            ->whereNumber('id');
        Route::delete('companies/{company}/justifications/{id}', [PontoPanelController::class, 'destroyJustification'])
            ->name('companies.justifications.destroy')
            ->whereNumber('id');
        Route::get('companies/{company}/configuracao', [PontoPanelController::class, 'settings'])
            ->name('companies.settings.edit');
        Route::put('companies/{company}/settings', [PontoPanelController::class, 'updateSettings'])
            ->name('companies.settings.update');
        Route::post('companies/{company}/settings/test', [PontoPanelController::class, 'testSettings'])
            ->name('companies.settings.test');

        Route::get('companies/{company}/punch-schedule', [PontoEspelhoController::class, 'punchScheduleShow'])
            ->name('companies.punch-schedule.show');
        Route::put('companies/{company}/punch-schedule', [PontoEspelhoController::class, 'punchScheduleUpdate'])
            ->name('companies.punch-schedule.update');
        Route::post('companies/{company}/schedule-preferences/batch', [PontoEspelhoController::class, 'batchSchedulePreferences'])
            ->name('companies.schedule-preferences.batch');

        Route::post('companies/{company}/reports/start', [PontoEspelhoController::class, 'startReport'])
            ->name('companies.reports.start');
        Route::get('companies/{company}/reports/status', [PontoEspelhoController::class, 'reportStatus'])
            ->name('companies.reports.status');
        Route::get('companies/{company}/reports/download', [PontoEspelhoController::class, 'downloadReport'])
            ->name('companies.reports.download');

        Route::post('companies/{company}/espelhos/store', [PontoEspelhoController::class, 'storeEspelhoPdf'])
            ->name('companies.espelhos.store');
        Route::post('companies/{company}/espelhos/batch', [PontoEspelhoController::class, 'startEspelhoBatch'])
            ->name('companies.espelhos.batch.start');
        Route::get('companies/{company}/espelhos/batch/{batch}', [PontoEspelhoController::class, 'showEspelhoBatch'])
            ->name('companies.espelhos.batch.show')
            ->whereNumber('batch');
        Route::get('companies/{company}/espelhos/auto-import', [PontoEspelhoController::class, 'autoImportStatus'])
            ->name('companies.espelhos.auto-import');
        Route::get('companies/{company}/espelhos/imports', [PontoEspelhoController::class, 'listEspelhoImports'])
            ->name('companies.espelhos.imports.index');
        Route::get('companies/{company}/espelhos/imports/{import}', [PontoEspelhoController::class, 'showEspelhoImport'])
            ->name('companies.espelhos.imports.show')
            ->whereNumber('import');
        Route::post('companies/{company}/espelhos/imports/{import}/reparse', [PontoEspelhoController::class, 'reparseEspelhoImport'])
            ->name('companies.espelhos.imports.reparse')
            ->whereNumber('import');
        Route::post('companies/{company}/espelhos/imports/{import}/parse-sync', [PontoEspelhoController::class, 'syncParseEspelhoImport'])
            ->name('companies.espelhos.imports.parse-sync')
            ->whereNumber('import');
        Route::get('companies/{company}/espelhos/imports/{import}/file', [PontoEspelhoController::class, 'downloadEspelhoImportFile'])
            ->name('companies.espelhos.imports.file')
            ->whereNumber('import');
    });

    Route::middleware('admin.can:feedbacks')->prefix('feedbacks')->name('feedbacks.')->group(function () {
        Route::get('/', [FeedbackDashboardController::class, 'index'])->name('index');
        Route::post('company', [FeedbackCompanySelectController::class, 'store'])->name('company.store');

        Route::middleware('feedback.company')->group(function () {
            Route::resource('employees', FeedbackEmployeeController::class)->except(['destroy']);
            Route::delete('employees/{employee}', [FeedbackEmployeeController::class, 'destroy'])->name('employees.destroy');
            Route::get('sessions', [FeedbackSessionController::class, 'index'])->name('sessions.index');
            Route::get('sessions/create', [FeedbackSessionController::class, 'create'])->name('sessions.create');
            Route::post('sessions', [FeedbackSessionController::class, 'store'])->name('sessions.store');
            Route::get('sessions/{session}', [FeedbackSessionController::class, 'show'])->name('sessions.show');
            Route::get('sessions/{session}/edit', [FeedbackSessionController::class, 'edit'])->name('sessions.edit');
            Route::patch('sessions/{session}', [FeedbackSessionController::class, 'update'])->name('sessions.update');
            Route::post('sessions/{session}/assinaturas', [FeedbackSessionController::class, 'sendSignatures'])->name('sessions.signatures');
            Route::get('sessions/{session}/pdf', [FeedbackSessionController::class, 'pdf'])->name('sessions.pdf');
        });
    });

    Route::middleware('admin.can:ferias')->prefix('ferias')->name('ferias.')->group(function () {
        Route::get('/', [EmployeeLeaveController::class, 'index'])->name('index');
        Route::post('company', [FeriasCompanySelectController::class, 'store'])->name('company.store');

        Route::middleware('ferias.company')->group(function () {
            Route::get('create', [EmployeeLeaveController::class, 'create'])->name('create');
            Route::post('/', [EmployeeLeaveController::class, 'store'])->name('store');
            Route::get('{leave}/edit', [EmployeeLeaveController::class, 'edit'])->name('edit');
            Route::put('{leave}', [EmployeeLeaveController::class, 'update'])->name('update');
            Route::delete('{leave}', [EmployeeLeaveController::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware('admin.can:desligamento')->prefix('desligamento')->name('desligamento.')->group(function () {
        Route::get('/', [ExitInterviewController::class, 'index'])->name('index');
        Route::post('company', [DesligamentoCompanySelectController::class, 'store'])->name('company.store');

        Route::middleware('desligamento.company')->group(function () {
            Route::get('create', [ExitInterviewController::class, 'create'])->name('create');
            Route::post('/', [ExitInterviewController::class, 'store'])->name('store');
            Route::get('{interview}', [ExitInterviewController::class, 'show'])->name('show');
            Route::get('{interview}/pdf', [ExitInterviewController::class, 'pdf'])->name('pdf');
            Route::post('{interview}/link', [ExitInterviewController::class, 'shareLink'])->name('link.store');
            Route::delete('{interview}/link', [ExitInterviewController::class, 'revokeLink'])->name('link.destroy');
            Route::get('{interview}/edit', [ExitInterviewController::class, 'edit'])->name('edit');
            Route::put('{interview}', [ExitInterviewController::class, 'update'])->name('update');
            Route::delete('{interview}', [ExitInterviewController::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware('admin.can:denuncias')->prefix('complaints')->name('complaints.')->group(function () {
        Route::get('/', [ComplaintController::class, 'index'])->name('index');
        Route::post('company', [ComplaintCompanySelectController::class, 'store'])->name('company.store');

        Route::middleware('complaints.company')->group(function () {
            Route::get('{complaint}', [ComplaintController::class, 'show'])->name('show');
            Route::patch('{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('status');
            Route::post('{complaint}/messages', [ComplaintController::class, 'storeMessage'])->name('messages.store');
        });
    });

    Route::middleware('admin.can:sugestoes')->prefix('sugestoes')->name('suggestions.')->group(function () {
        Route::get('/', [SuggestionController::class, 'index'])->name('index');
        Route::post('company', [SuggestionCompanySelectController::class, 'store'])->name('company.store');

        Route::middleware('suggestions.company')->group(function () {
            Route::get('{suggestion}', [SuggestionController::class, 'show'])->name('show')->whereNumber('suggestion');
            Route::patch('{suggestion}/status', [SuggestionController::class, 'updateStatus'])->name('status')->whereNumber('suggestion');
        });
    });

    Route::middleware('admin.can:plans')->group(function () {
        Route::resource('plans', PlanController::class)->except(['show']);
    });

    Route::middleware('admin.can:survey_templates')->group(function () {
        Route::resource('survey-templates', SurveyTemplateController::class);
    });

    Route::middleware('admin.can:settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings/ai', [AiSettingsController::class, 'update'])->name('settings.ai.update');
        Route::post('settings/ai/test', [AiSettingsController::class, 'test'])->name('settings.ai.test');
        Route::post('settings/ai/test-transcription', [AiSettingsController::class, 'testTranscription'])->name('settings.ai.test-transcription');
        Route::put('settings/solides', [SolidesSettingsController::class, 'update'])->name('settings.solides.update');
        Route::post('settings/solides/test', [SolidesSettingsController::class, 'test'])->name('settings.solides.test');
        Route::put('settings/mail', [MailSettingsController::class, 'update'])->name('settings.mail.update');
        Route::post('settings/mail/test', [MailSettingsController::class, 'test'])->name('settings.mail.test');
        Route::get('ai-settings', [AiSettingsController::class, 'edit'])->name('ai-settings.edit');
        Route::put('ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
        Route::post('ai-settings/test', [AiSettingsController::class, 'test'])->name('ai-settings.test');
    });

    Route::middleware('admin.can:solides_banco_talentos')->group(function () {
        Route::get('solides/curriculos', [SolidesCurriculumController::class, 'index'])->name('solides.curriculos.index');
    });

    Route::middleware('admin.can:solides_acompanhamento')->group(function () {
        Route::get('acompanhamento', [HiringProcessController::class, 'index'])->name('acompanhamento.index');
        Route::post('acompanhamento', [HiringProcessController::class, 'store'])->name('acompanhamento.store');
        Route::post('acompanhamento/reordenar', [HiringProcessController::class, 'reorder'])->name('acompanhamento.reorder');
        Route::patch('acompanhamento/{hiringProcess}', [HiringProcessController::class, 'update'])->name('acompanhamento.update');
        Route::post('acompanhamento/{hiringProcess}/avancar', [HiringProcessController::class, 'advance'])->name('acompanhamento.advance');
        Route::post('acompanhamento/{hiringProcess}/recuar', [HiringProcessController::class, 'retreat'])->name('acompanhamento.retreat');
        Route::post('acompanhamento/{hiringProcess}/observacoes', [HiringProcessController::class, 'storeComment'])->name('acompanhamento.comments.store');
        Route::delete('acompanhamento/{hiringProcess}', [HiringProcessController::class, 'destroy'])->name('acompanhamento.destroy');
    });

    Route::middleware('admin.can:methodology')->group(function () {
        // Regras de recomendação das Soluções Talents (dimensão em atenção → serviço), revisadas pela Talents.
        Route::get('solucoes-nr1', [\App\Http\Controllers\Admin\ServiceMatchAdminController::class, 'index'])->name('service-matches.index');
        Route::patch('solucoes-nr1/{match}', [\App\Http\Controllers\Admin\ServiceMatchAdminController::class, 'update'])->whereNumber('match')->name('service-matches.update');
        Route::post('solucoes-nr1/{match}/revisar', [\App\Http\Controllers\Admin\ServiceMatchAdminController::class, 'approve'])->whereNumber('match')->name('service-matches.approve');
        Route::get('metodologia', [AdminMethodologyController::class, 'index'])->name('metodologia.index');
        Route::resource('methodology-templates', MethodologyFormTemplateController::class)
            ->parameters(['methodology-templates' => 'template']);
    });

    Route::prefix('comercial')->name('comercial.')->group(function () {
        Route::middleware('admin.can:comercial_propostas')->group(function () {
            Route::get('/', function () {
                return redirect()->route('admin.comercial.propostas.index');
            })->name('dashboard');
            Route::post('propostas/preview', [CommercialPreviewController::class, 'calculate'])->name('propostas.preview');
            Route::get('propostas/{proposal}/pdf', [CommercialProposalController::class, 'pdf'])->name('propostas.pdf');
            Route::post('propostas/{proposal}/contratos', [CommercialContractController::class, 'store'])
                ->name('propostas.contratos.store');
            Route::post('propostas/{proposal}/converter', [FinanceSaleController::class, 'store'])
                ->name('propostas.converter');
            Route::patch('propostas/{proposal}/status', [CommercialProposalController::class, 'updateStatus'])
                ->name('propostas.status');
            Route::patch('propostas/{proposal}/contacted', [CommercialProposalController::class, 'markContacted'])
                ->name('propostas.contacted');
            Route::patch('propostas/{proposal}/notes', [CommercialProposalController::class, 'updateNotes'])
                ->name('propostas.notes');
            Route::post('propostas/{proposal}/reabrir', [CommercialProposalController::class, 'reopen'])
                ->name('propostas.reopen');
            Route::post('propostas/{proposal}/comissoes-extra', [CommercialProposalController::class, 'storeExtraCommission'])
                ->name('propostas.comissoes-extra');
            Route::get('contratos/{contract}/pdf', [CommercialContractController::class, 'pdf'])
                ->name('contratos.pdf');
            Route::post('contratos/{contract}/zapsign', [CommercialContractController::class, 'sendZapSign'])
                ->name('contratos.zapsign');
            Route::resource('propostas', CommercialProposalController::class)
                ->except(['show'])
                ->parameters(['propostas' => 'proposal']);
        });

        Route::middleware('admin.can:comercial_processos')->group(function () {
            Route::get('processos/{processo}/download', [CommercialProcessController::class, 'download'])
                ->name('processos.download');
            Route::resource('processos', CommercialProcessController::class)
                ->parameters(['processos' => 'processo']);
        });

        Route::middleware('admin.can:comercial_valores_contratos')->group(function () {
            Route::get('configuracoes', [CommercialSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('configuracoes', [CommercialSettingsController::class, 'update'])->name('settings.update');
            Route::patch('vendedores/{user}', [CommercialSettingsController::class, 'toggleSeller'])->name('settings.sellers.toggle');
            Route::get('contract-templates/{template}/docx', [CommercialContractTemplateController::class, 'downloadDocx'])
                ->name('contract-templates.docx');
            Route::get('contract-templates/{template}/editor', [CommercialContractTemplateController::class, 'editor'])
                ->name('contract-templates.editor');
            Route::resource('contract-templates', CommercialContractTemplateController::class)
                ->only(['store', 'update', 'destroy'])
                ->parameters(['contract-templates' => 'template'])
                ->names('contract-templates');
            Route::resource('products', CommercialProductController::class)
                ->only(['store', 'update', 'destroy'])
                ->parameters(['products' => 'product'])
                ->names('products');
        });
    });

    Route::prefix('financeiro')->name('financeiro.')->group(function () {
        Route::middleware('admin.can:financeiro_resumo')->group(function () {
            Route::get('/', [FinanceDashboardController::class, 'index'])->name('dashboard');
        });

        Route::middleware('admin.can:financeiro_vendas')->group(function () {
            Route::get('vendas', [FinanceSaleController::class, 'index'])->name('vendas.index');
            Route::get('vendas/nova', [FinanceSaleController::class, 'create'])->name('vendas.create');
            Route::post('vendas', [FinanceSaleController::class, 'storeManual'])->name('vendas.store');
            Route::get('vendas/{sale}/editar', [FinanceSaleController::class, 'edit'])->name('vendas.edit');
            Route::put('vendas/{sale}', [FinanceSaleController::class, 'update'])->name('vendas.update');
            Route::delete('vendas/{sale}', [FinanceSaleController::class, 'destroy'])->name('vendas.destroy');
            Route::get('vendas/{sale}', [FinanceSaleController::class, 'show'])->name('vendas.show');
        });

        // "|" = OR entre módulos (vírgula é reservada pelo Laravel para parâmetros).
        Route::middleware('admin.can:financeiro_vendas|financeiro_contas_a_receber')->group(function () {
            Route::patch('parcelas/{installment}/pagamento', [FinanceInstallmentController::class, 'registerPayment'])
                ->name('parcelas.pagamento');
            Route::patch('parcelas/{installment}', [FinanceInstallmentController::class, 'update'])
                ->name('parcelas.update');
            Route::get('parcelas/{installment}/comprovante', [FinanceInstallmentController::class, 'receipt'])
                ->name('parcelas.comprovante');
        });

        Route::middleware('admin.can:financeiro_comissoes')->group(function () {
            Route::get('comissoes', [FinanceCommissionController::class, 'index'])->name('comissoes.index');
            Route::patch('comissoes/{commission}', [FinanceCommissionController::class, 'update'])
                ->name('comissoes.update');
        });

        Route::middleware('admin.can:financeiro_contas_bancarias')->group(function () {
            Route::get('contas-bancarias', [FinanceBankAccountController::class, 'index'])->name('contas-bancarias.index');
            Route::get('contas-bancarias/nova', [FinanceBankAccountController::class, 'create'])->name('contas-bancarias.create');
            Route::post('contas-bancarias', [FinanceBankAccountController::class, 'store'])->name('contas-bancarias.store');
            Route::post('contas-bancarias/transferir', [FinanceBankAccountController::class, 'transfer'])
                ->name('contas-bancarias.transfer');
            Route::get('contas-bancarias/{bank_account}/editar', [FinanceBankAccountController::class, 'edit'])
                ->name('contas-bancarias.edit');
            Route::put('contas-bancarias/{bank_account}', [FinanceBankAccountController::class, 'update'])
                ->name('contas-bancarias.update');
            Route::delete('contas-bancarias/{bank_account}', [FinanceBankAccountController::class, 'destroy'])
                ->name('contas-bancarias.destroy');
        });

        Route::middleware('admin.can:financeiro_formas_pagamento')->group(function () {
            Route::resource('formas-pagamento', FinancePaymentMethodController::class)
                ->except(['show'])
                ->parameters(['formas-pagamento' => 'payment_method']);
        });

        Route::middleware('admin.can:financeiro_contas_a_pagar')->group(function () {
            Route::get('contas-a-pagar', [FinancePayableController::class, 'index'])->name('contas-a-pagar.index');
            Route::get('contas-a-pagar/nova', [FinancePayableController::class, 'create'])->name('contas-a-pagar.create');
            Route::post('contas-a-pagar', [FinancePayableController::class, 'store'])->name('contas-a-pagar.store');
            Route::get('contas-a-pagar/{payable}/editar', [FinancePayableController::class, 'edit'])->name('contas-a-pagar.edit');
            Route::put('contas-a-pagar/{payable}', [FinancePayableController::class, 'update'])->name('contas-a-pagar.update');
            Route::delete('contas-a-pagar/{payable}', [FinancePayableController::class, 'destroy'])->name('contas-a-pagar.destroy');
            Route::patch('contas-a-pagar/{payable}/pagar', [FinancePayableController::class, 'markPaid'])
                ->name('contas-a-pagar.mark-paid');
        });

        Route::middleware('admin.can:financeiro_contas_a_receber')->group(function () {
            Route::get('contas-a-receber', [FinanceReceivableController::class, 'index'])->name('contas-a-receber.index');
            Route::get('contas-a-receber/nova', [FinanceReceivableController::class, 'create'])->name('contas-a-receber.create');
            Route::post('contas-a-receber', [FinanceReceivableController::class, 'store'])->name('contas-a-receber.store');
            Route::get('contas-a-receber/{receivable}/editar', [FinanceReceivableController::class, 'edit'])
                ->name('contas-a-receber.edit');
            Route::put('contas-a-receber/{receivable}', [FinanceReceivableController::class, 'update'])
                ->name('contas-a-receber.update');
            Route::delete('contas-a-receber/{receivable}', [FinanceReceivableController::class, 'destroy'])
                ->name('contas-a-receber.destroy');
            Route::patch('contas-a-receber/{receivable}/receber', [FinanceReceivableController::class, 'markPaid'])
                ->name('contas-a-receber.mark-paid');
        });
    });

    Route::middleware('admin.can:tarefas')->prefix('tarefas')->name('tarefas.')->group(function () {
        Route::resource('processos', TasksProcessTemplateController::class)
            ->parameters(['processos' => 'template']);

        Route::post('processos/{template}/listas', [TasksTemplateListController::class, 'store'])->name('processos.listas.store');
        Route::patch('processo-listas/{template_list}', [TasksTemplateListController::class, 'update'])->name('processo-listas.update');
        Route::delete('processo-listas/{template_list}', [TasksTemplateListController::class, 'destroy'])->name('processo-listas.destroy');

        Route::post('processo-listas/{template_list}/cards', [TasksTemplateCardController::class, 'store'])->name('processo-listas.cards.store');
        Route::patch('processo-cards/{template_card}', [TasksTemplateCardController::class, 'update'])->name('processo-cards.update');
        Route::delete('processo-cards/{template_card}', [TasksTemplateCardController::class, 'destroy'])->name('processo-cards.destroy');

        Route::get('quadros/ativar', [TasksBoardActivationController::class, 'create'])->name('quadros.ativar');
        Route::post('quadros/reordenar', [TasksTaskBoardController::class, 'reorder'])
            ->middleware('admin.can:tarefas,edit')
            ->name('quadros.reordenar');
        Route::post('processos/{template}/ativar', [TasksBoardActivationController::class, 'store'])->name('processos.ativar');

        Route::resource('quadros', TasksTaskBoardController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['quadros' => 'board']);
        Route::patch('quadros/{board}', [TasksTaskBoardController::class, 'update'])->name('quadros.update');
        Route::delete('quadros/{board}', [TasksTaskBoardController::class, 'destroy'])->name('quadros.destroy');

        Route::post('quadros/{board}/listas', [TaskBoardListController::class, 'store'])->name('quadros.listas.store');
        Route::patch('listas/{list}', [TaskBoardListController::class, 'update'])->name('listas.update');
        Route::post('listas/{list}/arquivar', [TaskBoardListController::class, 'archive'])->name('listas.arquivar');
        Route::post('listas/{list}/restaurar', [TaskBoardListController::class, 'restore'])->name('listas.restaurar');
        Route::delete('listas/{list}', [TaskBoardListController::class, 'destroy'])->name('listas.destroy');

        Route::post('listas/{list}/cards', [TaskBoardCardController::class, 'store'])->name('listas.cards.store');
        Route::patch('cards/{card}', [TaskBoardCardController::class, 'update'])->name('cards.update');
        Route::post('cards/{card}/mover', [TaskBoardCardController::class, 'move'])->name('cards.move');
        Route::post('cards/{card}/arquivar', [TaskBoardCardController::class, 'archive'])->name('cards.arquivar');
        Route::post('cards/{card}/restaurar', [TaskBoardCardController::class, 'restore'])->name('cards.restaurar');
        Route::delete('cards/{card}', [TaskBoardCardController::class, 'destroy'])->name('cards.destroy');

        Route::post('quadros/{board}/labels', [TaskBoardLabelController::class, 'store'])->name('quadros.labels.store');
        Route::patch('labels/{label}', [TaskBoardLabelController::class, 'update'])->name('labels.update');
        Route::delete('labels/{label}', [TaskBoardLabelController::class, 'destroy'])->name('labels.destroy');

        Route::post('cards/{card}/checklists', [TaskBoardChecklistController::class, 'store'])->name('cards.checklists.store');
        Route::post('cards/{card}/checklists/reordenar', [TaskBoardChecklistController::class, 'reorder'])->name('cards.checklists.reorder');
        Route::patch('checklists/{checklist}', [TaskBoardChecklistController::class, 'update'])->name('checklists.update');
        Route::delete('checklists/{checklist}', [TaskBoardChecklistController::class, 'destroy'])->name('checklists.destroy');

        Route::post('checklists/{checklist}/itens', [TaskBoardChecklistItemController::class, 'store'])->name('checklists.itens.store');
        Route::post('checklists/{checklist}/itens/reordenar', [TaskBoardChecklistItemController::class, 'reorder'])->name('checklists.itens.reorder');
        Route::patch('checklist-itens/{item}', [TaskBoardChecklistItemController::class, 'update'])->name('checklist-itens.update');
        Route::delete('checklist-itens/{item}', [TaskBoardChecklistItemController::class, 'destroy'])->name('checklist-itens.destroy');

        Route::post('cards/{card}/anexos', [TaskBoardAttachmentController::class, 'store'])->name('cards.anexos.store');
        Route::delete('anexos/{attachment}', [TaskBoardAttachmentController::class, 'destroy'])->name('anexos.destroy');

        Route::post('cards/{card}/comentarios', [TaskBoardCommentController::class, 'store'])->name('cards.comentarios.store');
        Route::delete('comentarios/{comment}', [TaskBoardCommentController::class, 'destroy'])->name('comentarios.destroy');

        Route::post('quadros/{board}/favoritar', [TaskBoardFavoriteController::class, 'store'])->name('quadros.favoritar');
        Route::delete('quadros/{board}/favoritar', [TaskBoardFavoriteController::class, 'destroy'])->name('quadros.desfavoritar');

        Route::post('quadros/{board}/membros', [TaskBoardMemberController::class, 'store'])->name('quadros.membros.store');
        Route::delete('quadros/{board}/membros/{user}', [TaskBoardMemberController::class, 'destroy'])->name('quadros.membros.destroy');
        Route::post('quadros/{board}/partilhar-cartoes', [TasksTaskBoardController::class, 'shareCards'])
            ->middleware('admin.can:tarefas,edit')
            ->name('quadros.partilhar-cartoes');
    });

    Route::middleware('admin.can:entrevistas_roteiros')->prefix('entrevistas')->name('entrevistas.')->group(function () {
        Route::resource('roteiros', InterviewQuestionnaireController::class)
            ->except(['show'])
            ->parameters(['roteiros' => 'questionnaire']);
    });

    Route::middleware('admin.can:entrevistas_ia')->prefix('entrevistas')->name('entrevistas.')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->name('index');
        Route::get('nova', [InterviewController::class, 'create'])->name('create');
        Route::post('/', [InterviewController::class, 'store'])->name('store');
        Route::get('{interview}/relatorio.pdf', [InterviewReportController::class, 'pdf'])->name('report.pdf');
        Route::get('{interview}/relatorio.docx', [InterviewReportController::class, 'docx'])->name('report.docx');
        Route::post('{interview}/reprocessar', [InterviewController::class, 'reprocess'])->name('reprocess');
        Route::get('{interview}', [InterviewController::class, 'show'])->name('show');
        Route::delete('{interview}', [InterviewController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('admin.can:entrevistas_reunioes')->prefix('reunioes')->name('reunioes.')->group(function () {
        Route::get('/', [MeetingController::class, 'index'])->name('index');
        Route::get('nova', [MeetingController::class, 'create'])->name('create');
        Route::post('/', [MeetingController::class, 'store'])->name('store');
        Route::post('{meeting}/audio', [MeetingController::class, 'storeAudio'])->name('audio.store');
        Route::patch('{meeting}/ata', [MeetingController::class, 'updateMinutes'])->name('minutes.update');
        Route::post('{meeting}/reprocessar', [MeetingController::class, 'reprocess'])->name('reprocess');
        Route::get('{meeting}', [MeetingController::class, 'show'])->name('show');
        Route::delete('{meeting}', [MeetingController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('admin.can:equipe')->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::post('users/{user}/resend-invitation', [AdminUserController::class, 'resendInvitation'])->name('users.resend-invitation');
        Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::match(['put', 'patch'], 'users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    });
});
