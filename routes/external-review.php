<?php

use App\Http\Controllers\ExternalReview\DocumentReviewController;
use Illuminate\Support\Facades\Route;

// Revisor externo (organização aprovadora). Desligado por padrão: ver config nr1.external_review.
Route::middleware(['auth', 'verified', 'external_reviewer'])
    ->prefix('revisao')
    ->name('external-review.')
    ->group(function () {
        Route::get('/', [DocumentReviewController::class, 'index'])->name('index');
        Route::get('documentos/{version}', [DocumentReviewController::class, 'show'])
            ->whereNumber('version')->name('show');
        Route::get('documentos/{version}/download', [DocumentReviewController::class, 'download'])
            ->whereNumber('version')->name('download');
        Route::post('documentos/{version}/comentarios', [DocumentReviewController::class, 'comment'])
            ->whereNumber('version')->name('comment');
        Route::post('documentos/{version}/aprovar', [DocumentReviewController::class, 'approve'])
            ->whereNumber('version')->name('approve');
    });
