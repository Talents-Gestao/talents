<?php

use App\Http\Controllers\Client\ActionPlanController;
use App\Http\Controllers\Client\ComplaintController;
use App\Http\Controllers\Client\CompanyNoticeController as ClientCompanyNoticeController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DepartmentController;
use App\Http\Controllers\Client\Feedback\FeedbackDashboardController;
use App\Http\Controllers\Client\Feedback\FeedbackEmployeeController;
use App\Http\Controllers\Client\Feedback\FeedbackSessionController;
use App\Http\Controllers\Client\Ferias\EmployeeLeaveController;
use App\Http\Controllers\Client\Desligamento\ExitInterviewController;
use App\Http\Controllers\Client\ExportController;
use App\Http\Controllers\Client\ImportController;
use App\Http\Controllers\Client\MethodologyController as ClientMethodologyController;
use App\Http\Controllers\Client\MethodologySurveyController;
use App\Http\Controllers\Client\MethodologySurveyResultsController;
use App\Http\Controllers\Client\PositionController;
use App\Http\Controllers\Client\ReportController;
use App\Http\Controllers\Client\RhidApiController;
use App\Http\Controllers\Client\RhidComplianceController;
use App\Http\Controllers\Client\RhidSettingsController;
use App\Http\Controllers\Client\StrategicCalendarController as ClientStrategicCalendarController;
use App\Http\Controllers\Client\SurveyController;
use App\Http\Controllers\Client\SurveyResultsController;
use App\Http\Controllers\Client\TrainingController;
use App\Http\Controllers\Client\VozDoTimeController;
use App\Http\Controllers\Client\Tasks\BoardController as ClientTasksBoardController;
use App\Http\Controllers\Client\Tasks\BoardFavoriteController as ClientTasksBoardFavoriteController;
use App\Http\Controllers\Client\Tasks\CardAttachmentController as ClientTasksCardAttachmentController;
use App\Http\Controllers\Client\Tasks\CardChecklistItemController as ClientTasksCardChecklistItemController;
use App\Http\Controllers\Client\Tasks\CardCommentController as ClientTasksCardCommentController;
use App\Http\Controllers\Client\Tasks\CardController as ClientTasksCardController;
use App\Http\Controllers\Client\Tasks\CardMoveController as ClientTasksCardMoveController;
use App\Http\Controllers\Client\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'company'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    Route::get('avisos', [ClientCompanyNoticeController::class, 'index'])->name('notices.index');
    Route::get('avisos/recentes', [ClientCompanyNoticeController::class, 'recent'])->name('notices.recent');
    Route::post('avisos/{notice}/lido', [ClientCompanyNoticeController::class, 'markRead'])->name('notices.mark-read');
    Route::post('avisos/marcar-todos-lidos', [ClientCompanyNoticeController::class, 'markAllRead'])->name('notices.mark-all-read');

    Route::middleware(['company_admin', 'can.module:usuarios'])->group(function () {
        Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->except(['show']);
    });

    Route::middleware('can.module:departamentos_cargos')->group(function () {
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::post('import/departments', [ImportController::class, 'departments'])->name('import.departments');
        Route::resource('positions', PositionController::class)->except(['show']);
    });

    Route::get('voz-do-time', [VozDoTimeController::class, 'index'])->name('voz-do-time.index');

    Route::middleware('can.module:pesquisas')->group(function () {
        Route::resource('surveys', SurveyController::class);
        Route::get('surveys/{survey}/results', [SurveyResultsController::class, 'show'])->name('surveys.results');
    });

    Route::middleware('can.module:pesquisas,edit')->group(function () {
        Route::post('surveys/{survey}/ai-analysis', [SurveyResultsController::class, 'generateAiAnalysis'])->name('surveys.ai-analysis');
        Route::post('surveys/{survey}/recalculate', [SurveyResultsController::class, 'recalculate'])->name('surveys.recalculate');
    });

    Route::middleware('can.module:planos_acao')->group(function () {
        Route::get('surveys/{survey}/action-plan', [ActionPlanController::class, 'show'])->name('surveys.action-plan');
        Route::get('surveys/{survey}/action-plan/technical-opinion-file', [ActionPlanController::class, 'downloadTechnicalOpinionFile'])->name('surveys.action-plan.technical-opinion-file');
        Route::patch('action-plan-items/{item}', [ActionPlanController::class, 'updateItem'])->name('action-plan-items.update');
    });

    Route::middleware('can.module:relatorios')->group(function () {
        Route::get('surveys/{survey}/reports/executive', [ReportController::class, 'executive'])->name('surveys.reports.executive');
        Route::get('surveys/{survey}/reports/technical', [ReportController::class, 'technical'])->name('surveys.reports.technical');
        Route::get('surveys/{survey}/reports/referral', [ReportController::class, 'referral'])->name('surveys.reports.referral');
        Route::get('surveys/{survey}/reports/action-plan', [ReportController::class, 'actionPlan'])->name('surveys.reports.action-plan');
        Route::get('surveys/{survey}/export/json', [ExportController::class, 'json'])->name('surveys.export.json');
        Route::get('surveys/{survey}/export/csv', [ExportController::class, 'csv'])->name('surveys.export.csv');
    });

    Route::middleware('can.module:capacitacao')->group(function () {
        Route::get('capacitacao', [TrainingController::class, 'index'])->name('training.index');
    });

    Route::middleware(['strategic_calendar', 'can.module:calendario_estrategico'])->group(function () {
        Route::get('calendario-estrategico', [ClientStrategicCalendarController::class, 'index'])->name('strategic-calendar.index');
        Route::patch('calendario-estrategico/{item}/conclusao', [ClientStrategicCalendarController::class, 'toggleCompletion'])
            ->name('strategic-calendar.toggle-completion');
        Route::patch('calendario-estrategico/tarefas/{card}/conclusao', [ClientStrategicCalendarController::class, 'toggleTaskCompletion'])
            ->name('strategic-calendar.toggle-task-completion');
        Route::get('calendario-estrategico/anexos/{attachment}/download', [ClientStrategicCalendarController::class, 'attachmentDownload'])
            ->name('strategic-calendar.attachment-download');
    });

    Route::middleware('can.module:metodologia')->prefix('metodologia')->name('metodologia.')->group(function () {
        Route::get('/', [ClientMethodologyController::class, 'index'])->name('index');
        Route::get('pesquisa-satisfacao/{survey}/results', [MethodologySurveyResultsController::class, 'show'])->name('pesquisa-satisfacao.results');
        Route::get('pesquisa-satisfacao/{survey}/export/csv', [MethodologySurveyResultsController::class, 'exportCsv'])->name('pesquisa-satisfacao.export.csv');
        Route::resource('pesquisa-satisfacao', MethodologySurveyController::class)
            ->parameters(['pesquisa-satisfacao' => 'survey']);
    });

    Route::middleware('can.module:denuncias')->group(function () {
        Route::get('complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
        Route::patch('complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.status');
        Route::post('complaints/{complaint}/messages', [ComplaintController::class, 'storeMessage'])->name('complaints.messages.store');
    });

    Route::middleware('can.module:rhid')->prefix('rhid')->name('rhid.')->group(function () {
        Route::get('compliance', [RhidComplianceController::class, 'index'])->name('compliance.index');
        Route::get('collaborators/{person}', [RhidComplianceController::class, 'collaboratorShow'])
            ->name('collaborators.show')
            ->whereNumber('person');
        Route::get('settings', [RhidSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [RhidSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/test', [RhidSettingsController::class, 'test'])->name('settings.test');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('justification-types', [RhidApiController::class, 'justificationTypes'])->name('justification-types');
            Route::get('alert-types', [RhidApiController::class, 'alertTypes'])->name('alert-types');
            Route::post('justifications/list', [RhidApiController::class, 'listJustifications'])->name('justifications.list');
            Route::post('justifications', [RhidApiController::class, 'storeJustification'])->name('justifications.store');
            Route::post('justifications/mass', [RhidApiController::class, 'massJustification'])->name('justifications.mass');
            Route::delete('justifications/{id}', [RhidApiController::class, 'destroyJustification'])->name('justifications.destroy');
            Route::get('person-bank-hours', [RhidApiController::class, 'personBankHours'])->name('person-bank-hours');
            Route::get('person-bank-hours/all', [RhidApiController::class, 'allPersonBankHours'])->name('person-bank-hours.all');
            Route::get('people', [RhidApiController::class, 'listPeople'])->name('people.index');
            Route::get('people/{id}', [RhidApiController::class, 'showPerson'])->name('people.show')->whereNumber('id');
            Route::put('people/{id}/schedule-preference', [RhidApiController::class, 'updatePersonSchedulePreference'])
                ->name('people.schedule-preference.update')
                ->whereNumber('id');
            Route::post('people/schedule-preferences/batch', [RhidApiController::class, 'batchPersonSchedulePreferences'])
                ->name('people.schedule-preferences.batch');
            Route::get('departments', [RhidApiController::class, 'listDepartments'])->name('departments.index');
            Route::get('person-roles', [RhidApiController::class, 'listPersonRoles'])->name('person-roles.index');
            Route::post('person-shift/mass', [RhidApiController::class, 'massPersonShift'])->name('person-shift.mass');
            Route::post('reports/start', [RhidApiController::class, 'startReport'])->name('reports.start');
            Route::get('reports/status', [RhidApiController::class, 'reportStatus'])->name('reports.status');
            Route::get('reports/download', [RhidApiController::class, 'downloadReport'])->name('reports.download');
            Route::post('espelhos/store', [RhidApiController::class, 'storeEspelhoPdf'])->name('espelhos.store');
            Route::post('espelhos/batch', [RhidApiController::class, 'startEspelhoBatch'])->name('espelhos.batch.start');
            Route::get('espelhos/batch/{batch}', [RhidApiController::class, 'showEspelhoBatch'])->name('espelhos.batch.show')->whereNumber('batch');
            Route::get('espelhos/imports', [RhidApiController::class, 'listEspelhoImports'])->name('espelhos.imports.index');
            Route::get('espelhos/imports/{import}', [RhidApiController::class, 'showEspelhoImport'])->name('espelhos.imports.show')->whereNumber('import');
            Route::post('espelhos/imports/{import}/reparse', [RhidApiController::class, 'reparseEspelhoImport'])->name('espelhos.imports.reparse')->whereNumber('import');
            Route::post('espelhos/imports/{import}/parse-sync', [RhidApiController::class, 'syncParseEspelhoImport'])->name('espelhos.imports.parse-sync')->whereNumber('import');
            Route::get('espelhos/imports/{import}/file', [RhidApiController::class, 'downloadEspelhoImportFile'])->name('espelhos.imports.file')->whereNumber('import');
            Route::get('espelhos/schedule-adherence', [RhidApiController::class, 'espelhoScheduleAdherence'])->name('espelhos.schedule-adherence');
            Route::get('espelhos/schedule-adherence/marks', [RhidApiController::class, 'espelhoScheduleAdherenceMarks'])->name('espelhos.schedule-adherence.marks');
            Route::post('afd/export', [RhidApiController::class, 'exportAfd'])->name('afd.export');
            Route::get('last-punches', [RhidApiController::class, 'lastPunches'])->name('last-punches');
            Route::get('punch-schedule-settings', [RhidApiController::class, 'punchScheduleSettings'])->name('punch-schedule-settings.show');
            Route::put('punch-schedule-settings', [RhidApiController::class, 'updatePunchScheduleSettings'])->name('punch-schedule-settings.update');
            Route::get('devices', [RhidApiController::class, 'devices'])->name('devices.index');
            Route::post('devices', [RhidApiController::class, 'storeDevice'])->name('devices.store');
            Route::put('devices', [RhidApiController::class, 'updateDevice'])->name('devices.update');
            Route::get('devices/{id}', [RhidApiController::class, 'showDevice'])->name('devices.show');
            Route::delete('devices/{id}', [RhidApiController::class, 'destroyDevice'])->name('devices.destroy');
            Route::post('devices/{id}/id-cloud', [RhidApiController::class, 'enableIdCloud'])->name('devices.id-cloud');
            Route::post('sync/force-all', [RhidApiController::class, 'forceResyncAll'])->name('sync.force-all');
        });
    });

    Route::middleware('can.module:feedbacks')->prefix('feedbacks')->name('feedbacks.')->group(function () {
        Route::get('/', [FeedbackDashboardController::class, 'index'])->name('index');
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

    Route::middleware(['company_admin', 'can.module:ferias'])->prefix('ferias')->name('ferias.')->group(function () {
        Route::get('/', [EmployeeLeaveController::class, 'index'])->name('index');
        Route::get('create', [EmployeeLeaveController::class, 'create'])->name('create');
        Route::post('/', [EmployeeLeaveController::class, 'store'])->name('store');
        Route::get('{leave}/edit', [EmployeeLeaveController::class, 'edit'])->name('edit');
        Route::put('{leave}', [EmployeeLeaveController::class, 'update'])->name('update');
        Route::delete('{leave}', [EmployeeLeaveController::class, 'destroy'])->name('destroy');
    });

    Route::middleware(['company_admin', 'can.module:desligamento'])->prefix('desligamento')->name('desligamento.')->group(function () {
        Route::get('/', [ExitInterviewController::class, 'index'])->name('index');
        Route::get('create', [ExitInterviewController::class, 'create'])->name('create');
        Route::post('/', [ExitInterviewController::class, 'store'])->name('store');
        Route::get('{interview}', [ExitInterviewController::class, 'show'])->name('show');
        Route::get('{interview}/edit', [ExitInterviewController::class, 'edit'])->name('edit');
        Route::put('{interview}', [ExitInterviewController::class, 'update'])->name('update');
        Route::delete('{interview}', [ExitInterviewController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can.module:tarefas')->prefix('tarefas')->name('tarefas.')->group(function () {
        Route::get('/', [ClientTasksBoardController::class, 'index'])->name('index');
        Route::patch('cards/{card}', [ClientTasksCardController::class, 'update'])->name('cards.update');
        Route::post('cards/{card}/mover', [ClientTasksCardMoveController::class, 'store'])->name('cards.move');
        Route::post('cards/{card}/comentarios', [ClientTasksCardCommentController::class, 'store'])->name('cards.comentarios.store');
        Route::patch('checklist-itens/{item}', [ClientTasksCardChecklistItemController::class, 'update'])->name('checklist-itens.update');
        Route::post('cards/{card}/anexos', [ClientTasksCardAttachmentController::class, 'store'])->name('cards.anexos.store');
        Route::delete('anexos/{attachment}', [ClientTasksCardAttachmentController::class, 'destroy'])->name('anexos.destroy');
        Route::post('{board}/favoritar', [ClientTasksBoardFavoriteController::class, 'store'])->name('favoritar')->whereNumber('board');
        Route::delete('{board}/favoritar', [ClientTasksBoardFavoriteController::class, 'destroy'])->name('desfavoritar')->whereNumber('board');
        Route::get('{board}', [ClientTasksBoardController::class, 'show'])->name('show')->whereNumber('board');
    });
});
