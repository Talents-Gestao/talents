<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Leaves\FeriasCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeriasCompanySelectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        abort_unless($company->hasFeriasEnabled(), 403);

        $request->session()->put(FeriasCompanyContext::SESSION_KEY, $company->id);

        return redirect()
            ->route('admin.ferias.index')
            ->with('success', 'Empresa selecionada: '.$company->name);
    }
}
