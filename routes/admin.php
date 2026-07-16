<?php

use App\Http\Controllers\Admin\ActionPlanAdminController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\Commercial\ContractController as CommercialContractController;
use App\Http\Controllers\Admin\Commercial\ProductController as CommercialProductController;
use App\Http\Controllers\Admin\Commercial\ContractTemplateController as CommercialContractTemplateController;
use App\Http\Controllers\Admin\Commercial\DashboardController as CommercialDashboardController;
use App\Http\Controllers\Admin\Commercial\PreviewController as CommercialPreviewController;
use App\Http\Controllers\Admin\Commercial\ProposalController as CommercialProposalController;
use App\Http\Controllers\Admin\Commercial\SettingsController as CommercialSettingsController;
use App\Http\Controllers\Admin\FeedbackCompanySelectController;
use App\Http\Controllers\Admin\FeriasCompanySelectController;
use App\Http\Controllers\Admin\DesligamentoCompanySelectController;
use App\Http\Controllers\Admin\ComplaintCompanySelectController;
use App\Http\Controllers\Client\Feedback\FeedbackDashboardController;
use App\Http\Controllers\Client\Feedback\FeedbackEmployeeController;
use App\Http\Controllers\Client\Feedback\FeedbackSessionController;
use App\Http\Controllers\Client\Ferias\EmployeeLeaveController;
use App\Http\Controllers\Client\Desligamento\ExitInterviewController;
use App\Http\Controllers\Client\ComplaintController;
use App\Http\Controllers\Admin\Finance\CommissionController as FinanceCommissionController;
use App\Http\Controllers\Admin\Finance\FinanceDashboardController;
use App\Http\Controllers\Admin\Finance\InstallmentController as FinanceInstallmentController;
use App\Http\Controllers\Admin\Finance\SaleController as FinanceSaleController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyEmployeeController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\ComingSoonController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LandingInterestSubmissionController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\MethodologyCompanyController;
use App\Http\Controllers\Admin\MethodologyController as AdminMethodologyController;
use App\Http\Controllers\Admin\MethodologyFormTemplateController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PlatformCompanyController;
use App\Http\Controllers\Admin\RhidPortfolioController;
use App\Http\Controllers\Admin\RhidPanelController;
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
use App\Http\Controllers\Admin\Entrevistas\InterviewController;
use App\Http\Controllers\Admin\Entrevistas\InterviewQuestionnaireController;
use App\Http\Controllers\Admin\Entrevistas\InterviewReportController;
use App\Http\Controllers\Admin\TrainingController as AdminTrainingController;
use App\Http\Controllers\NewsFeedController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('em-breve/{module}', [ComingSoonController::class, 'show'])
        ->where('module', '[a-z0-9\-]+')
        ->name('coming-soon.show');

    Route::middleware('admin.can:dashboard')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('rhid/portfolio-metrics', [RhidPortfolioController::class, 'portfolioMetrics'])
            ->name('rhid.portfolio-metrics');
    });

    Route::middleware('admin.can:empresa_talents')->group(function () {
        Route::get('empresa-talents', [PlatformCompanyController::class, 'edit'])->name('empresa-talents.edit');
        Route::put('empresa-talents', [PlatformCompanyController::class, 'update'])->name('empresa-talents.update');
    });

    Route::middleware('admin.can:landing_interest')->group(function () {
        Route::get('/interessados-landing', [LandingInterestSubmissionController::class, 'index'])
            ->name('landing-interest.index');
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
    Route::post('avisos/{notice}/lido', [AdminCompanyNoticeController::class, 'markRead'])->name('notices.mark-read');
    Route::post('avisos/marcar-todos-lidos', [AdminCompanyNoticeController::class, 'markAllRead'])->name('notices.mark-all-read');

    Route::get('noticias', NewsFeedController::class)->name('news.feed');

    Route::middleware('admin.can:companies')->group(function () {
        Route::get('colaboradores/lookup-cep', [CompanyEmployeeController::class, 'lookupCep'])
            ->name('colaboradores.lookup-cep');
        Route::resource('colaboradores', CompanyEmployeeController::class)
            ->parameters(['colaboradores' => 'employee']);

        Route::get('companies/{company}/rhid-metrics', [RhidPortfolioController::class, 'companyMetrics'])
            ->name('companies.rhid-metrics');
        Route::get('companies/lookup-cnpj', [CompanyController::class, 'lookupCnpj'])->name('companies.lookup-cnpj');
        Route::get('companies/{company}/surveys/{survey}/action-plan', [ActionPlanAdminController::class, 'edit'])
            ->name('companies.surveys.action-plan.edit');
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

    Route::middleware('admin.can:rhid')->prefix('rhid')->name('rhid.')->group(function () {
        Route::get('/', [RhidPanelController::class, 'index'])->name('index');
        Route::get('summary', [RhidPanelController::class, 'summary'])->name('summary');
        Route::get('companies/{company}/metrics', [RhidPanelController::class, 'companyMetrics'])->name('companies.metrics');
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

    Route::middleware('admin.can:solides')->group(function () {
        Route::get('solides/curriculos', [SolidesCurriculumController::class, 'index'])->name('solides.curriculos.index');

        Route::get('acompanhamento', [HiringProcessController::class, 'index'])->name('acompanhamento.index');
        Route::post('acompanhamento', [HiringProcessController::class, 'store'])->name('acompanhamento.store');
        Route::post('acompanhamento/reordenar', [HiringProcessController::class, 'reorder'])->name('acompanhamento.reorder');
        Route::patch('acompanhamento/{hiringProcess}', [HiringProcessController::class, 'update'])->name('acompanhamento.update');
        Route::post('acompanhamento/{hiringProcess}/avancar', [HiringProcessController::class, 'advance'])->name('acompanhamento.advance');
        Route::post('acompanhamento/{hiringProcess}/recuar', [HiringProcessController::class, 'retreat'])->name('acompanhamento.retreat');
        Route::delete('acompanhamento/{hiringProcess}', [HiringProcessController::class, 'destroy'])->name('acompanhamento.destroy');
    });

    Route::middleware('admin.can:training')->group(function () {
        Route::get('capacitacao', [AdminTrainingController::class, 'index'])->name('training.index');
    });

    Route::middleware('admin.can:methodology')->group(function () {
        Route::get('metodologia', [AdminMethodologyController::class, 'index'])->name('metodologia.index');
        Route::resource('methodology-templates', MethodologyFormTemplateController::class)
            ->parameters(['methodology-templates' => 'template']);
    });

    Route::middleware('admin.can:comercial')->prefix('comercial')->name('comercial.')->group(function () {
        Route::get('/', [CommercialDashboardController::class, 'index'])->name('dashboard');
        Route::get('configuracoes', [CommercialSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('configuracoes', [CommercialSettingsController::class, 'update'])->name('settings.update');
        Route::patch('vendedores/{user}', [CommercialSettingsController::class, 'toggleSeller'])->name('settings.sellers.toggle');
        Route::post('propostas/preview', [CommercialPreviewController::class, 'calculate'])->name('propostas.preview');
        Route::get('propostas/{proposal}/pdf', [CommercialProposalController::class, 'pdf'])->name('propostas.pdf');
        Route::post('propostas/{proposal}/contratos', [CommercialContractController::class, 'store'])
            ->name('propostas.contratos.store');
        Route::post('propostas/{proposal}/converter', [FinanceSaleController::class, 'store'])
            ->name('propostas.converter');
        Route::get('contratos/{contract}/pdf', [CommercialContractController::class, 'pdf'])
            ->name('contratos.pdf');
        Route::post('contratos/{contract}/zapsign', [CommercialContractController::class, 'sendZapSign'])
            ->name('contratos.zapsign');
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
        Route::resource('propostas', CommercialProposalController::class)
            ->except(['show'])
            ->parameters(['propostas' => 'proposal']);
    });

    Route::middleware('admin.can:financeiro')->prefix('financeiro')->name('financeiro.')->group(function () {
        Route::get('/', [FinanceDashboardController::class, 'index'])->name('dashboard');
        Route::get('vendas', [FinanceSaleController::class, 'index'])->name('vendas.index');
        Route::get('vendas/{sale}', [FinanceSaleController::class, 'show'])->name('vendas.show');
        Route::patch('parcelas/{installment}/pagamento', [FinanceInstallmentController::class, 'registerPayment'])
            ->name('parcelas.pagamento');
        Route::get('parcelas/{installment}/comprovante', [FinanceInstallmentController::class, 'receipt'])
            ->name('parcelas.comprovante');
        Route::get('comissoes', [FinanceCommissionController::class, 'index'])->name('comissoes.index');
        Route::patch('comissoes/{commission}', [FinanceCommissionController::class, 'update'])
            ->name('comissoes.update');
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
    });

    Route::middleware('admin.can:entrevistas')->prefix('entrevistas')->name('entrevistas.')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->name('index');
        Route::get('nova', [InterviewController::class, 'create'])->name('create');
        Route::post('/', [InterviewController::class, 'store'])->name('store');
        Route::resource('roteiros', InterviewQuestionnaireController::class)
            ->except(['show'])
            ->parameters(['roteiros' => 'questionnaire']);
        Route::get('{interview}/relatorio.pdf', [InterviewReportController::class, 'pdf'])->name('report.pdf');
        Route::get('{interview}/relatorio.docx', [InterviewReportController::class, 'docx'])->name('report.docx');
        Route::post('{interview}/reprocessar', [InterviewController::class, 'reprocess'])->name('reprocess');
        Route::get('{interview}', [InterviewController::class, 'show'])->name('show');
        Route::delete('{interview}', [InterviewController::class, 'destroy'])->name('destroy');
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
