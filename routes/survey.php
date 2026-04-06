<?php

use App\Http\Controllers\Survey\PublicSurveyController;
use Illuminate\Support\Facades\Route;

Route::get('/pesquisa/{token}', [PublicSurveyController::class, 'show'])->name('survey.public');
Route::get('/pesquisa/{token}/obrigado', [PublicSurveyController::class, 'thanks'])->name('survey.public.thanks');
Route::post('/pesquisa/{token}', [PublicSurveyController::class, 'submit'])
    ->middleware('throttle:public-survey-submit')
    ->name('survey.public.submit');
