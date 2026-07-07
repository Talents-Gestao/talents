<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Feedback\FeedbackCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedbackCompanySelectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        abort_unless($company->hasFeedbacksEnabled(), 403);

        $request->session()->put(FeedbackCompanyContext::SESSION_KEY, $company->id);

        return redirect()
            ->route('admin.feedbacks.index')
            ->with('success', 'Empresa selecionada: '.$company->name);
    }
}
