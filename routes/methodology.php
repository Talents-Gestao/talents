<?php

use App\Http\Controllers\MethodologyPublicSurveyController;
use Illuminate\Support\Facades\Route;

Route::get('/satisfacao/{token}', [MethodologyPublicSurveyController::class, 'show'])
    ->middleware('throttle:public-token-page')
    ->name('methodology.public');
Route::get('/satisfacao/{token}/obrigado', [MethodologyPublicSurveyController::class, 'thanks'])
    ->middleware('throttle:public-token-page')
    ->name('methodology.public.thanks');
Route::post('/satisfacao/{token}', [MethodologyPublicSurveyController::class, 'submit'])
    ->middleware('throttle:public-survey-submit')
    ->name('methodology.public.submit');
