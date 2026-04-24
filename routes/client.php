<?php

use App\Http\Controllers\Client\ActionPlanController;
use App\Http\Controllers\Client\ComplaintController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DepartmentController;
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
use App\Http\Controllers\Client\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'company'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    Route::middleware(['company_admin', 'can.module:usuarios'])->group(function () {
        Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->except(['show']);
    });

    Route::middleware('can.module:departamentos_cargos')->group(function () {
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::post('import/departments', [ImportController::class, 'departments'])->name('import.departments');
        Route::resource('positions', PositionController::class)->except(['show']);
    });

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
        Route::patch('action-plan-items/{item}', [ActionPlanController::class, 'updateItem'])->name('action-plan-items.update');
    });

    Route::middleware('can.module:relatorios')->group(function () {
        Route::get('surveys/{survey}/reports/executive', [ReportController::class, 'executive'])->name('surveys.reports.executive');
        Route::get('surveys/{survey}/reports/technical', [ReportController::class, 'technical'])->name('surveys.reports.technical');
        Route::get('surveys/{survey}/export/json', [ExportController::class, 'json'])->name('surveys.export.json');
        Route::get('surveys/{survey}/export/csv', [ExportController::class, 'csv'])->name('surveys.export.csv');
    });

    Route::middleware('can.module:capacitacao')->group(function () {
        Route::get('capacitacao', [TrainingController::class, 'index'])->name('training.index');
    });

    Route::middleware(['strategic_calendar', 'can.module:calendario_estrategico'])->group(function () {
        Route::get('calendario-estrategico', [ClientStrategicCalendarController::class, 'index'])->name('strategic-calendar.index');
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
});
