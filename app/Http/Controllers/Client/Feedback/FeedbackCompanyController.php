<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Http\Request;

abstract class FeedbackCompanyController extends Controller
{
    protected function company(Request $request): Company
    {
        return app(FeedbackCompanyContext::class)->resolve($request);
    }
}
