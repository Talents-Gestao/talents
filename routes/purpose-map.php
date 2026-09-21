<?php

declare(strict_types=1);

use App\Http\Controllers\PurposeMap\PublicPurposeMapController;
use Illuminate\Support\Facades\Route;

Route::get('/mapa-proposito/{token}', [PublicPurposeMapController::class, 'show'])
    ->middleware('throttle:public-token-page')
    ->name('purpose-map.public');

Route::get('/mapa-proposito/{token}/obrigado', [PublicPurposeMapController::class, 'thanks'])
    ->middleware('throttle:public-token-page')
    ->name('purpose-map.public.thanks');

Route::post('/mapa-proposito/{token}', [PublicPurposeMapController::class, 'submit'])
    ->middleware('throttle:public-survey-submit')
    ->name('purpose-map.public.submit');
