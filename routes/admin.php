<?php

use App\Http\Controllers\Admin\ActionPlanAdminController;
use App\Http\Controllers\Admin\AiSettingsController;
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
use App\Http\Controllers\Admin\StrategicCalendarController as AdminStrategicCalendarController;
use App\Http\Controllers\Admin\SurveyTemplateController;
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
});
