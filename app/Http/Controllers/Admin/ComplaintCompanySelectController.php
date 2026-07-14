<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Complaints\ComplaintCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ComplaintCompanySelectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        abort_unless($company->hasComplaintsEnabled(), 403);

        $request->session()->put(ComplaintCompanyContext::SESSION_KEY, $company->id);

        return redirect()
            ->route('admin.complaints.index')
            ->with('success', 'Empresa selecionada: '.$company->name);
    }
}
