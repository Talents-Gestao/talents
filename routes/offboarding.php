<?php

use App\Http\Controllers\Offboarding\PublicExitInterviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('desligamento')->name('desligamento.public.')->group(function () {
    Route::get('responder/{token}', [PublicExitInterviewController::class, 'show'])->name('show');
    Route::get('responder/{token}/obrigado', [PublicExitInterviewController::class, 'thanks'])->name('thanks');
    Route::post('responder/{token}', [PublicExitInterviewController::class, 'submit'])
        ->middleware('throttle:20,1')
        ->name('submit');
});
