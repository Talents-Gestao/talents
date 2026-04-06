<?php

use App\Http\Controllers\Admin\ActionPlanAdminController;
use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SurveyTemplateController;
use App\Http\Controllers\Admin\TrainingController as AdminTrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('companies/lookup-cnpj', [CompanyController::class, 'lookupCnpj'])->name('companies.lookup-cnpj');
    Route::get('companies/{company}/surveys/{survey}/action-plan', [ActionPlanAdminController::class, 'edit'])
        ->name('companies.surveys.action-plan.edit');
    Route::put('companies/{company}/surveys/{survey}/action-plan', [ActionPlanAdminController::class, 'update'])
        ->name('companies.surveys.action-plan.update');
    Route::resource('companies', CompanyController::class);
    Route::post('companies/{company}/templates/{template}', [CompanyController::class, 'attachTemplate'])->name('companies.templates.attach');
    Route::delete('companies/{company}/templates/{template}', [CompanyController::class, 'detachTemplate'])->name('companies.templates.detach');
    Route::resource('plans', PlanController::class)->except(['show']);
    Route::resource('survey-templates', SurveyTemplateController::class);
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings/ai', [AiSettingsController::class, 'update'])->name('settings.ai.update');
    Route::post('settings/ai/test', [AiSettingsController::class, 'test'])->name('settings.ai.test');
    Route::put('settings/mail', [MailSettingsController::class, 'update'])->name('settings.mail.update');
    Route::post('settings/mail/test', [MailSettingsController::class, 'test'])->name('settings.mail.test');
    Route::get('ai-settings', [AiSettingsController::class, 'edit'])->name('ai-settings.edit');
    Route::put('ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
    Route::post('ai-settings/test', [AiSettingsController::class, 'test'])->name('ai-settings.test');
    Route::get('capacitacao', [AdminTrainingController::class, 'index'])->name('training.index');
});
