<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Desligamento;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Desligamento\DesligamentoCompanyContext;
use Illuminate\Http\Request;

abstract class DesligamentoCompanyController extends Controller
{
    protected function company(Request $request): Company
    {
        return app(DesligamentoCompanyContext::class)->resolve($request);
    }
}
