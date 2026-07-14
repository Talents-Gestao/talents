<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Desligamento\DesligamentoCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DesligamentoCompanySelectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        abort_unless($company->hasDesligamentoEnabled(), 403);

        $request->session()->put(DesligamentoCompanyContext::SESSION_KEY, $company->id);

        return redirect()
            ->route('admin.survey-templates.index')
            ->with('success', 'Empresa selecionada: '.$company->name);
    }
}
