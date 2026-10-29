<?php

use App\Http\Controllers\Nr1\ShellController;
use App\Support\Nr1Shell\Nr1Shell;
use Illuminate\Support\Facades\Route;

// Shell NR-1: navegação própria, padrão em todos os ambientes (sem flag). As rotas antigas (admin e client) seguem valendo por URL.
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
