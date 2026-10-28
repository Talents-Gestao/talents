<?php

use App\Http\Controllers\Nr1\ShellController;
use App\Support\Nr1Shell\Nr1Shell;
use Illuminate\Support\Facades\Route;

// Shell NR-1: navegação própria. Nada aqui substitui as rotas antigas (admin e client), que seguem valendo.
// Flag: config nr1.shell_enabled (NR1_SHELL_ENABLED). Desligada, tudo abaixo responde 404.
Route::middleware(['web', 'nr1.shell'])->prefix('nr1')->name('nr1.')->group(function () {
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
