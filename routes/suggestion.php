<?php

use App\Http\Controllers\Suggestion\PublicSuggestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('sugestoes')->name('sugestao.')->group(function () {
    Route::get('{token}/obrigado', [PublicSuggestionController::class, 'thanks'])->name('thanks');
    Route::get('{token}', [PublicSuggestionController::class, 'create'])->name('create');
    Route::post('{token}', [PublicSuggestionController::class, 'store'])
        ->middleware('throttle:public-suggestion-store')
        ->name('store');
});
