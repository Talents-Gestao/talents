<?php

use App\Http\Controllers\Client\ActionPlanController;
use App\Http\Controllers\Client\ComplaintController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DepartmentController;
use App\Http\Controllers\Client\ExportController;
use App\Http\Controllers\Client\ImportController;
use App\Http\Controllers\Client\PositionController;
use App\Http\Controllers\Client\ReportController;
use App\Http\Controllers\Client\RhidApiController;
use App\Http\Controllers\Client\RhidComplianceController;
use App\Http\Controllers\Client\RhidSettingsController;
use App\Http\Controllers\Client\SurveyController;
use App\Http\Controllers\Client\SurveyResultsController;
use App\Http\Controllers\Client\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'company'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::resource('positions', PositionController::class)->except(['show']);
    Route::post('import/departments', [ImportController::class, 'departments'])->name('import.departments');
    Route::resource('surveys', SurveyController::class);
    Route::get('surveys/{survey}/results', [SurveyResultsController::class, 'show'])->name('surveys.results');
    Route::post('surveys/{survey}/ai-analysis', [SurveyResultsController::class, 'generateAiAnalysis'])->name('surveys.ai-analysis');
    Route::post('surveys/{survey}/recalculate', [SurveyResultsController::class, 'recalculate'])->name('surveys.recalculate');
    Route::get('surveys/{survey}/action-plan', [ActionPlanController::class, 'show'])->name('surveys.action-plan');
    Route::post('surveys/{survey}/action-plan/generate', [ActionPlanController::class, 'generate'])->name('surveys.action-plan.generate');
    Route::patch('action-plan-items/{item}', [ActionPlanController::class, 'updateItem'])->name('action-plan-items.update');
    Route::get('surveys/{survey}/reports/executive', [ReportController::class, 'executive'])->name('surveys.reports.executive');
    Route::get('surveys/{survey}/reports/technical', [ReportController::class, 'technical'])->name('surveys.reports.technical');
    Route::get('surveys/{survey}/export/json', [ExportController::class, 'json'])->name('surveys.export.json');
    Route::get('surveys/{survey}/export/csv', [ExportController::class, 'csv'])->name('surveys.export.csv');

    Route::get('capacitacao', [TrainingController::class, 'index'])->name('training.index');
    Route::get('complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::patch('complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.status');
    Route::post('complaints/{complaint}/messages', [ComplaintController::class, 'storeMessage'])->name('complaints.messages.store');

    Route::middleware('company_admin')->prefix('rhid')->name('rhid.')->group(function () {
        Route::get('compliance', [RhidComplianceController::class, 'index'])->name('compliance.index');
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
            Route::post('person-shift/mass', [RhidApiController::class, 'massPersonShift'])->name('person-shift.mass');
            Route::post('reports/start', [RhidApiController::class, 'startReport'])->name('reports.start');
            Route::get('reports/status', [RhidApiController::class, 'reportStatus'])->name('reports.status');
            Route::get('reports/download', [RhidApiController::class, 'downloadReport'])->name('reports.download');
            Route::post('afd/export', [RhidApiController::class, 'exportAfd'])->name('afd.export');
            Route::get('last-punches', [RhidApiController::class, 'lastPunches'])->name('last-punches');
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
