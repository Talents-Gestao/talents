<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Models\User;
use App\Services\CommercialPricingService;
use App\Services\CommercialProposalPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProposalController extends Controller
{
    public function __construct(
        private readonly CommercialPricingService $pricing,
    ) {}

    public function index(Request $request): Response
    {
        $q = CommercialProposal::query()
            ->with('seller:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = (string) $request->string('search');
            $q->where(function ($query) use ($s) {
                $query->where('client_name', 'like', '%'.$s.'%')
                    ->orWhere('code', 'like', '%'.$s.'%')
                    ->orWhere('client_cnpj', 'like', '%'.$s.'%');
            });
        }

        if ($request->filled('seller_id')) {
            $q->where('seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('status')) {
            if ($request->string('status')->toString() === 'fechadas') {
                $q->where('is_closed', true);
            } elseif ($request->string('status')->toString() === 'abertas') {
                $q->where('is_closed', false);
            }
        }

        $proposals = $q->paginate(15)->withQueryString();

        $commercialSettings = CommercialSetting::current();

        return Inertia::render('Admin/Comercial/Propostas/Index', [
            'proposals' => $proposals,
            'sellers' => $this->sellersOptions(),
            'filters' => $request->only(['search', 'seller_id', 'status']),
            'templates' => CommercialContractTemplate::active()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'zapsign_configured' => filled(trim((string) ($commercialSettings->zapsign_api_token ?? ''))),
            'zapsignParties' => [
                'contratada_signatario' => trim((string) ($commercialSettings->company_contract_signatory_name ?? '')),
                'contratada_telefone' => $commercialSettings->company_phone,
                'contratada_email' => $commercialSettings->company_email,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Comercial/Propostas/Form', [
            'mode' => 'create',
            'proposal' => null,
            'sellers' => $this->sellersOptions(),
            'settings' => $this->publicSettings(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProposal($request);

        $totals = $this->pricing->calculate($data);

        $proposal = CommercialProposal::create(array_merge($data, $totals, [
            'code' => CommercialProposal::nextCode(),
            'created_by' => $request->user()?->id,
            'closed_at' => ($data['is_closed'] ?? false) ? now() : null,
        ]));

        return redirect()
            ->route('admin.comercial.propostas.edit', $proposal)
            ->with('success', "Proposta {$proposal->code} criada.");
    }

    public function edit(CommercialProposal $proposal): Response
    {
        return Inertia::render('Admin/Comercial/Propostas/Form', [
            'mode' => 'edit',
            'proposal' => $proposal->load([
                'seller:id,name',
                'contracts' => fn ($q) => $q->orderByDesc('generated_at')
                    ->select(['id', 'proposal_id', 'code', 'template_name_snapshot', 'generated_at']),
            ]),
            'sellers' => $this->sellersOptions(),
            'settings' => $this->publicSettings(),
        ]);
    }

    public function update(Request $request, CommercialProposal $proposal): RedirectResponse
    {
        $data = $this->validateProposal($request);
        $totals = $this->pricing->calculate($data);

        $wasClosed = $proposal->is_closed;
        $isClosed = (bool) ($data['is_closed'] ?? false);

        $proposal->update(array_merge($data, $totals, [
            'closed_at' => match (true) {
                $isClosed && ! $wasClosed => now(),
                ! $isClosed => null,
                default => $proposal->closed_at,
            },
        ]));

        return redirect()
            ->route('admin.comercial.propostas.edit', $proposal)
            ->with('success', 'Proposta atualizada.');
    }

    public function destroy(CommercialProposal $proposal): RedirectResponse
    {
        $proposal->delete();

        return redirect()
            ->route('admin.comercial.propostas.index')
            ->with('success', 'Proposta removida.');
    }

    public function pdf(CommercialProposal $proposal, CommercialProposalPdfService $pdfService): SymfonyResponse
    {
        $proposal->load('seller:id,name,email');

        return $pdfService
            ->generate($proposal)
            ->stream("proposta-{$proposal->code}.pdf");
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function sellersOptions(): array
    {
        return User::query()
            ->where('is_commercial', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * Settings expostos ao frontend para o cálculo ao vivo.
     */
    private function publicSettings(): array
    {
        $s = CommercialSetting::current();

        return $s->only([
            'profiler_tier1_max', 'profiler_tier1_cents',
            'profiler_tier2_max', 'profiler_tier2_cents',
            'profiler_tier3_max', 'profiler_tier3_cents',
            'profiler_tier4_cents',
            'pesquisas_tier1_max', 'pesquisas_tier1_cents',
            'pesquisas_tier2_max', 'pesquisas_tier2_cents',
            'pesquisas_tier3_max', 'pesquisas_tier3_cents',
            'pesquisas_tier4_cents',
            'direcionamento_tier1_max', 'direcionamento_tier1_cents',
            'direcionamento_tier2_max', 'direcionamento_tier2_cents',
            'direcionamento_tier3_max', 'direcionamento_tier3_cents',
            'direcionamento_tier4_cents',
            'nr1_tier1_max', 'nr1_tier1_cents',
            'nr1_tier2_max', 'nr1_tier2_cents',
            'nr1_tier3_max', 'nr1_tier3_cents',
            'nr1_tier4_cents',
            'devolutiva_individual_cents', 'devolutiva_grupo_cents',
            'nr1_implantacao_online_cents', 'nr1_implantacao_presencial_cents',
            'palestras_base_cents', 'palestras_threshold_funcionarios', 'palestras_multiplier',
            'default_commission_percent',
            'pdf_validade_dias',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProposal(Request $request): array
    {
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_cnpj' => ['nullable', 'string', 'max:18'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:32'],
            'client_address' => ['nullable', 'string', 'max:500'],
            'client_representative' => ['nullable', 'string', 'max:255'],
            'client_representative_role' => ['nullable', 'string', 'max:255'],
            'indication' => ['nullable', 'string', 'max:255'],
            'employee_count' => ['required', 'integer', 'min:0', 'max:100000'],

            'seller_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_commercial', true))],

            'svc_pesquisas' => ['boolean'],
            'svc_profiler' => ['boolean'],
            'svc_devolutiva' => ['nullable', Rule::in(['individual', 'grupo'])],
            'svc_nr1' => ['boolean'],
            'svc_nr1_implantacao_modo' => ['nullable', Rule::in(['online', 'presencial'])],
            'svc_contratacao' => ['boolean'],
            'svc_contratacao_salario_cents' => ['nullable', 'integer', 'min:0'],
            'svc_direcionamento' => ['boolean'],
            'svc_palestras' => ['boolean'],

            'palestra_topic' => ['nullable', 'string', 'max:500'],
            'palestra_event_date' => ['nullable', 'date'],
            'palestra_start_time' => ['nullable', 'string', 'max:32'],
            'palestra_duration_hours' => ['nullable', 'string', 'max:32'],
            'palestra_venue_address' => ['nullable', 'string', 'max:500'],
            'palestra_audience_estimate' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'palestra_format' => ['nullable', Rule::in(['presencial', 'online', 'hibrido'])],

            'is_closed' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['commission_percent'] = (float) (CommercialSetting::current()->default_commission_percent ?? 0);

        return $data;
    }
}
