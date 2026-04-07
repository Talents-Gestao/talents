<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\MethodologyFormTemplate;
use Illuminate\Http\RedirectResponse;

class MethodologyCompanyController extends Controller
{
    public function attachTemplate(Company $company, MethodologyFormTemplate $template): RedirectResponse
    {
        $company->methodologyFormTemplates()->syncWithoutDetaching([$template->id]);

        return back()->with('success', 'Template de Metodologia vinculado à empresa.');
    }

    public function detachTemplate(Company $company, MethodologyFormTemplate $template): RedirectResponse
    {
        $company->methodologyFormTemplates()->detach($template->id);

        return back()->with('success', 'Template removido da empresa.');
    }
}
