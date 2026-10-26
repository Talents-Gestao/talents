<?php

use App\Http\Controllers\Survey\PublicSurveyController;
use Illuminate\Support\Facades\Route;

Route::get('/pesquisa/{token}', [PublicSurveyController::class, 'show'])
    ->middleware('throttle:public-token-page')
    ->name('survey.public');
Route::get('/pesquisa/{token}/obrigado', [PublicSurveyController::class, 'thanks'])
    ->middleware('throttle:public-token-page')
    ->name('survey.public.thanks');
Route::get('/pesquisa/{token}/treinamento/{training}/arquivo', [PublicSurveyController::class, 'trainingMaterial'])
    ->whereNumber('training')
    ->middleware('throttle:public-token-page')
    ->name('survey.public.training-material');
Route::post('/pesquisa/{token}', [PublicSurveyController::class, 'submit'])
    ->middleware('throttle:public-survey-submit')
    ->name('survey.public.submit');
