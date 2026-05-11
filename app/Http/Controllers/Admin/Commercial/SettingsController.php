<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = CommercialSetting::current();

        return Inertia::render('Admin/Comercial/Configuracoes', [
            'settings' => $settings->toArray(),
            'contractTemplates' => CommercialContractTemplate::query()
                ->orderBy('name')
                ->get()
                ->map(fn (CommercialContractTemplate $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'source_type' => $t->source_type,
                    'body_html' => $t->body_html,
                    'is_active' => (bool) $t->is_active,
                    'has_docx' => (bool) $t->docx_path,
                ])
                ->all(),
            'users' => User::query()
                ->whereNull('company_id')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_commercial', 'is_active'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'is_active' => (bool) $u->is_active,
                    'is_commercial' => (bool) $u->is_commercial,
                ])
                ->all(),
        ]);
    }

    public function toggleSeller(Request $request, User $user): RedirectResponse
    {
        $request->validate(['is_commercial' => ['required', 'boolean']]);

        $user->is_commercial = (bool) $request->boolean('is_commercial');
        $user->save();

        return redirect()
            ->route('admin.comercial.settings.edit')
            ->with('success', $user->is_commercial
                ? "{$user->name} agora aparece como vendedor comercial."
                : "{$user->name} foi removido(a) dos vendedores comerciais.");
    }

    public function update(Request $request): RedirectResponse
    {
        $integerCents = ['nullable', 'integer', 'min:0'];
        $integerMax = ['nullable', 'integer', 'min:1'];

        $data = $request->validate([
            // Profiler
            'profiler_tier1_max' => $integerMax,
            'profiler_tier1_cents' => $integerCents,
            'profiler_tier2_max' => $integerMax,
            'profiler_tier2_cents' => $integerCents,
            'profiler_tier3_max' => $integerMax,
            'profiler_tier3_cents' => $integerCents,
            'profiler_tier4_cents' => $integerCents,

            // Pesquisas e Organograma
            'pesquisas_tier1_max' => $integerMax,
            'pesquisas_tier1_cents' => $integerCents,
            'pesquisas_tier2_max' => $integerMax,
            'pesquisas_tier2_cents' => $integerCents,
            'pesquisas_tier3_max' => $integerMax,
            'pesquisas_tier3_cents' => $integerCents,
            'pesquisas_tier4_cents' => $integerCents,

            // Direcionamento
            'direcionamento_tier1_max' => $integerMax,
            'direcionamento_tier1_cents' => $integerCents,
            'direcionamento_tier2_max' => $integerMax,
            'direcionamento_tier2_cents' => $integerCents,
            'direcionamento_tier3_max' => $integerMax,
            'direcionamento_tier3_cents' => $integerCents,
            'direcionamento_tier4_cents' => $integerCents,

            // NR-1
            'nr1_tier1_max' => $integerMax,
            'nr1_tier1_cents' => $integerCents,
            'nr1_tier2_max' => $integerMax,
            'nr1_tier2_cents' => $integerCents,
            'nr1_tier3_max' => $integerMax,
            'nr1_tier3_cents' => $integerCents,
            'nr1_tier4_cents' => $integerCents,

            // Devolutiva
            'devolutiva_individual_cents' => $integerCents,
            'devolutiva_grupo_cents' => $integerCents,

            // NR-1 Implantação
            'nr1_implantacao_online_cents' => $integerCents,
            'nr1_implantacao_presencial_cents' => $integerCents,

            // Palestras
            'palestras_base_cents' => $integerCents,
            'palestras_threshold_funcionarios' => ['nullable', 'integer', 'min:0'],
            'palestras_multiplier' => ['nullable', 'integer', 'min:1', 'max:10'],

            // PDF
            'pdf_validade_dias' => ['nullable', 'integer', 'min:1', 'max:365'],
            'pdf_observacoes' => ['nullable', 'string', 'max:2000'],
            'pdf_aceite_texto' => ['nullable', 'string', 'max:1000'],

            // Dados da empresa (contratos / placeholders)
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_cnpj' => ['nullable', 'string', 'max:32'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_city_state' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:64'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'default_payment_terms' => ['nullable', 'string', 'max:5000'],
            'default_prazo_dias' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        $settings = CommercialSetting::current();
        $settings->fill($data);
        $settings->updated_by = $request->user()?->id;
        $settings->save();

        return redirect()
            ->route('admin.comercial.settings.edit')
            ->with('success', 'Parâmetros comerciais atualizados.');
    }
}
