<?php

use App\Http\Controllers\Admin\ActionPlanAdminController;
use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\Commercial\DashboardController as CommercialDashboardController;
use App\Http\Controllers\Admin\Commercial\PreviewController as CommercialPreviewController;
use App\Http\Controllers\Admin\Commercial\ProposalController as CommercialProposalController;
use App\Http\Controllers\Admin\Commercial\SettingsController as CommercialSettingsController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LandingInterestSubmissionController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\MethodologyCompanyController;
use App\Http\Controllers\Admin\MethodologyController as AdminMethodologyController;
use App\Http\Controllers\Admin\MethodologyFormTemplateController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SolidesCurriculumController;
use App\Http\Controllers\Admin\SolidesSettingsController;
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
use App\Http\Controllers\Admin\TrainingController as AdminTrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/interessados-landing', [LandingInterestSubmissionController::class, 'index'])
        ->name('landing-interest.index');
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
    Route::get('companies/lookup-cnpj', [CompanyController::class, 'lookupCnpj'])->name('companies.lookup-cnpj');
    Route::get('companies/{company}/surveys/{survey}/action-plan', [ActionPlanAdminController::class, 'edit'])
        ->name('companies.surveys.action-plan.edit');
    Route::put('companies/{company}/surveys/{survey}/action-plan', [ActionPlanAdminController::class, 'update'])
        ->name('companies.surveys.action-plan.update');
    Route::post('companies/{company}/surveys/{survey}/ai-analysis', [ActionPlanAdminController::class, 'generateAiAnalysis'])
        ->name('companies.surveys.ai-analysis');
    Route::get('companies/{company}/users', [CompanyUserController::class, 'index'])->name('companies.users.index');
    Route::get('companies/{company}/users/create', [CompanyUserController::class, 'create'])->name('companies.users.create');
    Route::post('companies/{company}/users', [CompanyUserController::class, 'store'])->name('companies.users.store');
    Route::get('companies/{company}/users/{user}/edit', [CompanyUserController::class, 'edit'])->name('companies.users.edit');
    Route::match(['put', 'patch'], 'companies/{company}/users/{user}', [CompanyUserController::class, 'update'])->name('companies.users.update');
    Route::delete('companies/{company}/users/{user}', [CompanyUserController::class, 'destroy'])->name('companies.users.destroy');
    Route::resource('companies', CompanyController::class);
    Route::post('companies/{company}/templates/{template}', [CompanyController::class, 'attachTemplate'])->name('companies.templates.attach');
    Route::delete('companies/{company}/templates/{template}', [CompanyController::class, 'detachTemplate'])->name('companies.templates.detach');
    Route::resource('plans', PlanController::class)->except(['show']);
    Route::resource('survey-templates', SurveyTemplateController::class);
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings/ai', [AiSettingsController::class, 'update'])->name('settings.ai.update');
    Route::post('settings/ai/test', [AiSettingsController::class, 'test'])->name('settings.ai.test');
    Route::put('settings/solides', [SolidesSettingsController::class, 'update'])->name('settings.solides.update');
    Route::post('settings/solides/test', [SolidesSettingsController::class, 'test'])->name('settings.solides.test');
    Route::get('solides/curriculos', [SolidesCurriculumController::class, 'index'])->name('solides.curriculos.index');
    Route::put('settings/mail', [MailSettingsController::class, 'update'])->name('settings.mail.update');
    Route::post('settings/mail/test', [MailSettingsController::class, 'test'])->name('settings.mail.test');
    Route::get('ai-settings', [AiSettingsController::class, 'edit'])->name('ai-settings.edit');
    Route::put('ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
    Route::post('ai-settings/test', [AiSettingsController::class, 'test'])->name('ai-settings.test');
    Route::get('capacitacao', [AdminTrainingController::class, 'index'])->name('training.index');

    Route::get('metodologia', [AdminMethodologyController::class, 'index'])->name('metodologia.index');
    Route::post('companies/{company}/methodology-templates/{template}', [MethodologyCompanyController::class, 'attachTemplate'])->name('companies.methodology-templates.attach');
    Route::delete('companies/{company}/methodology-templates/{template}', [MethodologyCompanyController::class, 'detachTemplate'])->name('companies.methodology-templates.detach');
    Route::resource('methodology-templates', MethodologyFormTemplateController::class)
        ->parameters(['methodology-templates' => 'template']);

    Route::prefix('comercial')->name('comercial.')->group(function () {
        Route::get('/', [CommercialDashboardController::class, 'index'])->name('dashboard');
        Route::get('configuracoes', [CommercialSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('configuracoes', [CommercialSettingsController::class, 'update'])->name('settings.update');
        Route::patch('vendedores/{user}', [CommercialSettingsController::class, 'toggleSeller'])->name('settings.sellers.toggle');
        Route::post('propostas/preview', [CommercialPreviewController::class, 'calculate'])->name('propostas.preview');
        Route::get('propostas/{proposal}/pdf', [CommercialProposalController::class, 'pdf'])->name('propostas.pdf');
        Route::resource('propostas', CommercialProposalController::class)
            ->except(['show'])
            ->parameters(['propostas' => 'proposal']);
    });

    Route::prefix('tarefas')->name('tarefas.')->group(function () {
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
            ->only(['index', 'show'])
            ->parameters(['quadros' => 'board']);
        Route::patch('quadros/{board}', [TasksTaskBoardController::class, 'update'])->name('quadros.update');

        Route::post('quadros/{board}/listas', [TaskBoardListController::class, 'store'])->name('quadros.listas.store');
        Route::patch('listas/{list}', [TaskBoardListController::class, 'update'])->name('listas.update');
        Route::delete('listas/{list}', [TaskBoardListController::class, 'destroy'])->name('listas.destroy');

        Route::post('listas/{list}/cards', [TaskBoardCardController::class, 'store'])->name('listas.cards.store');
        Route::patch('cards/{card}', [TaskBoardCardController::class, 'update'])->name('cards.update');
        Route::post('cards/{card}/mover', [TaskBoardCardController::class, 'move'])->name('cards.move');
        Route::delete('cards/{card}', [TaskBoardCardController::class, 'destroy'])->name('cards.destroy');

        Route::post('quadros/{board}/labels', [TaskBoardLabelController::class, 'store'])->name('quadros.labels.store');
        Route::patch('labels/{label}', [TaskBoardLabelController::class, 'update'])->name('labels.update');
        Route::delete('labels/{label}', [TaskBoardLabelController::class, 'destroy'])->name('labels.destroy');

        Route::post('cards/{card}/checklists', [TaskBoardChecklistController::class, 'store'])->name('cards.checklists.store');
        Route::patch('checklists/{checklist}', [TaskBoardChecklistController::class, 'update'])->name('checklists.update');
        Route::delete('checklists/{checklist}', [TaskBoardChecklistController::class, 'destroy'])->name('checklists.destroy');

        Route::post('checklists/{checklist}/itens', [TaskBoardChecklistItemController::class, 'store'])->name('checklists.itens.store');
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
});
