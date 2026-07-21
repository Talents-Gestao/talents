<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Leaves;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Leaves\FeriasCompanyContext;
use Illuminate\Http\Request;

abstract class FeriasCompanyController extends Controller
{
    protected function company(Request $request): Company
    {
        return app(FeriasCompanyContext::class)->resolve($request);
    }
}
