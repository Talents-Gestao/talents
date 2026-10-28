<?php

use App\Http\Controllers\ClientEvaluation\PublicClientEvaluationController;
use Illuminate\Support\Facades\Route;

Route::get('/avaliacao/{token}', [PublicClientEvaluationController::class, 'show'])
    ->middleware('throttle:public-token-page')
    ->whereUuid('token')
    ->name('client-evaluation.public');

Route::get('/avaliacao/{token}/obrigado', [PublicClientEvaluationController::class, 'thanks'])
    ->middleware('throttle:public-token-page')
    ->whereUuid('token')
    ->name('client-evaluation.public.thanks');

Route::post('/avaliacao/{token}', [PublicClientEvaluationController::class, 'submit'])
    ->middleware('throttle:public-client-evaluation-submit')
    ->whereUuid('token')
    ->name('client-evaluation.public.submit');
