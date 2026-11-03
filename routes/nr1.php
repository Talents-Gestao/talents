<?php

use App\Http\Controllers\Nr1\LegacyUrlRedirectController;
use App\Http\Controllers\Nr1\ShellController;
use App\Support\Nr1Shell\Nr1Shell;
use Illuminate\Support\Facades\Route;

// Shell NR-1: navegação própria, única interface do NR-1.
Route::middleware(['web'])->prefix('nr1')->name('nr1.')->group(function () {
    // Colaborador: link da campanha, sem login.
    Route::get('jornada/{token}/{section?}', [ShellController::class, 'jornada'])
        ->whereIn('section', ['campanha', 'minha-jornada'])
        ->middleware('throttle:public-token-page')
        ->name('jornada');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [ShellController::class, 'index'])->name('index');

        foreach (array_keys(Nr1Shell::SECTIONS) as $section) {
            if ($section === 'minha-jornada') {
                continue; // só pelo link do colaborador
            }

            Route::get($section, [ShellController::class, 'show'])->defaults('section', $section)->name($section);
        }
    });
});

// Compatibilidade: favoritos e e-mails já enviados das telas removidas. Único vestígio delas; só redireciona.
Route::middleware(['web', 'auth', 'verified'])->controller(LegacyUrlRedirectController::class)->group(function () {
    Route::get('admin/companies/{company}/surveys/{survey}/action-plan', 'adminLaudo');
    Route::get('client/surveys/{survey}/results', 'clientResults');
    Route::get('client/surveys/{survey}/painel-psicossocial', 'clientResults');
    Route::get('client/surveys/{survey}/action-plan', 'clientPlan');
    Route::get('client/surveys/{survey}/plano-de-acao', 'clientPlan');
});
