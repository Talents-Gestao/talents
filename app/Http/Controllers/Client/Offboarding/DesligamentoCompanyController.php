<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Offboarding\DesligamentoCompanyContext;
use Illuminate\Http\Request;

abstract class DesligamentoCompanyController extends Controller
{
    protected function company(Request $request): Company
    {
        return app(DesligamentoCompanyContext::class)->resolve($request);
    }
}
