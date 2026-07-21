<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommercialSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformCompanyController extends Controller
{
    public function edit(): Response
    {
        $settings = CommercialSetting::current();

        return Inertia::render('Admin/PlatformCompany/Edit', [
            'settings' => $settings->only([
                'company_name',
                'company_cnpj',
                'company_address',
                'company_city_state',
                'company_phone',
                'company_email',
                'company_representative_line',
                'company_forum_city_state',
                'company_contract_signatory_name',
                'company_contract_signatory_cpf',
                'default_payment_terms',
                'default_prazo_dias',
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_cnpj' => ['nullable', 'string', 'max:32'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_city_state' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:64'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_representative_line' => ['nullable', 'string', 'max:5000'],
            'company_forum_city_state' => ['nullable', 'string', 'max:255'],
            'company_contract_signatory_name' => ['nullable', 'string', 'max:255'],
            'company_contract_signatory_cpf' => ['nullable', 'string', 'max:32'],
            'default_payment_terms' => ['nullable', 'string', 'max:5000'],
            'default_prazo_dias' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        $settings = CommercialSetting::current();
        $settings->fill($data);
        $settings->updated_by = $request->user()?->id;
        $settings->save();

        return redirect()
            ->route('admin.empresa-talents.edit')
            ->with('success', 'Dados da Talents (CONTRATADA) atualizados.');
    }
}
