<?php

use App\Http\Controllers\LandingInterestController;
use App\Http\Controllers\ProfileController;
use App\Support\AdminHomeResolver;
use App\Support\WorkspaceManager;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/nr-1', function () {
    return Inertia::render('Nr1', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('landing.nr1');

Route::get('/diagnostico-comportamental', function () {
    return Inertia::render('DiagnosticoComportamental', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('landing.diagnostico');

Route::get('/contratacao-de-talentos', function () {
    return Inertia::render('ContratacaoTalentos', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('landing.contratacao');

Route::get('/direcionamento-estrategico', function () {
    return Inertia::render('DirecionamentoEstrategico', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('landing.direcionamento');

Route::post('/interesse', [LandingInterestController::class, 'store'])
    ->middleware('throttle:public-landing-interest')
    ->name('landing.interest');

Route::get('/dashboard', function () {
    $user = auth()->user();
    if (! $user) {
        return redirect()->route('login');
    }

    $workspace = app(WorkspaceManager::class)->ensureActiveWorkspace($user, request());

    if (! $workspace) {
        return redirect()->route('workspaces.select');
    }

    if ($workspace->isTalents()) {
        return redirect(app(AdminHomeResolver::class)->urlFor($user));
    }

    return redirect()->route('client.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
