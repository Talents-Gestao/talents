<?php

use App\Http\Controllers\HiringFollowup\PublicFollowupController;
use Illuminate\Support\Facades\Route;

// Pesquisa pública do pós-contratação (D15/D30/D60/D90): link individual por token, sem login.
Route::get('/pos-contratacao/{token}', [PublicFollowupController::class, 'show'])
    ->middleware('throttle:public-token-page')
    ->whereUuid('token')
    ->name('hiring-followup.public');

Route::get('/pos-contratacao/{token}/obrigado', [PublicFollowupController::class, 'thanks'])
    ->middleware('throttle:public-token-page')
    ->whereUuid('token')
    ->name('hiring-followup.public.thanks');

Route::post('/pos-contratacao/{token}', [PublicFollowupController::class, 'submit'])
    ->middleware('throttle:public-hiring-followup-submit')
    ->whereUuid('token')
    ->name('hiring-followup.public.submit');
