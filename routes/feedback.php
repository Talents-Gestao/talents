<?php

use App\Http\Controllers\Feedback\PublicFeedbackSignController;
use Illuminate\Support\Facades\Route;

Route::prefix('feedback')->name('feedback.')->group(function () {
    Route::get('assinar/{token}', [PublicFeedbackSignController::class, 'show'])->name('sign.show');
    Route::post('assinar/{token}', [PublicFeedbackSignController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('sign.store');
});
