<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Models\User;
use App\Services\CommercialPricingService;
use App\Services\CommercialProposalPdfService;
use App\Support\CommercialProposalPdfDefaults;
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
            ->with(['seller:id,name', 'sale:id,proposal_id,code,status'])
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
            'catalogProducts' => $this->catalogProductsPayload(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $totals, $catalogLines] = $this->validatedWithTotals($request);

        $proposal = CommercialProposal::create(array_merge(
            $this->legacyDefaults(),
            $data,
            $totals,
            [
                'code' => CommercialProposal::nextCode(),
                'created_by' => $request->user()?->id,
                'closed_at' => ($data['is_closed'] ?? false) ? now() : null,
            ],
        ));

        $this->syncCatalogLines($proposal, $catalogLines);

        return redirect()
            ->route('admin.comercial.propostas.edit', $proposal)
            ->with('success', "Proposta {$proposal->code} criada.");
    }

    public function edit(CommercialProposal $proposal): Response
    {
        return Inertia::render('Admin/Comercial/Propostas/Form', [
            'mode' => 'edit',
            'proposal' => $this->proposalFormPayload($proposal),
            'sellers' => $this->sellersOptions(),
            'settings' => $this->publicSettings(),
            'catalogProducts' => $this->catalogProductsPayload(),
        ]);
    }

    public function update(Request $request, CommercialProposal $proposal): RedirectResponse
    {
        [$data, $totals, $catalogLines] = $this->validatedWithTotals($request, $proposal);

        $wasClosed = $proposal->is_closed;
        $isClosed = (bool) ($data['is_closed'] ?? false);

        $proposal->update(array_merge($data, $totals, [
            'closed_at' => match (true) {
                $isClosed && ! $wasClosed => now(),
                ! $isClosed => null,
                default => $proposal->closed_at,
            },
        ]));

        $this->syncCatalogLines($proposal, $catalogLines);

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

        $settings = $s->only([
            'default_commission_percent',
            'pdf_validade_dias',
        ]);

        $settings['pdf_descricoes_servicos'] = CommercialProposalPdfDefaults::serviceDescriptionsForSettings(
            $s->pdf_descricoes_servicos
        );

        return $settings;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<int, array<string, mixed>>}
     */
    private function validatedWithTotals(Request $request, ?CommercialProposal $existing = null): array
    {
        $data = $this->validateProposal($request);
        $catalogProducts = $data['catalog_products'] ?? [];
        unset($data['catalog_products']);

        $calcInputs = array_merge($data, [
            'catalog_products' => $catalogProducts,
        ]);

        $totals = $existing && $existing->hasLegacyServices()
            ? $this->pricing->calculatePreservingLegacy($existing, $calcInputs)
            : $this->pricing->calculate($calcInputs);

        $catalogLines = $totals['catalog_lines'] ?? [];
        unset($totals['catalog_lines']);

        return [$data, $totals, $catalogLines];
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalogLines
     */
    private function syncCatalogLines(CommercialProposal $proposal, array $catalogLines): void
    {
        $proposal->catalogLines()->delete();

        foreach ($catalogLines as $line) {
            $proposal->catalogLines()->create([
                'commercial_product_id' => $line['product_id'],
                'options' => $line['options'] ?? [],
                'label_snapshot' => $line['label'],
                'detail_snapshot' => $line['detail'] ?? '',
                'total_cents' => (int) $line['value_cents'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalFormPayload(CommercialProposal $proposal): array
    {
        $proposal->load([
            'seller:id,name',
            'contracts' => fn ($q) => $q->orderByDesc('generated_at')
                ->select(['id', 'proposal_id', 'code', 'template_name_snapshot', 'generated_at']),
            'catalogLines.product:id,slug,name,pricing_type,pricing_config',
        ]);

        $payload = $proposal->toArray();
        $payload['catalog_products'] = $proposal->catalogLines->map(fn ($line) => [
            'product_id' => $line->commercial_product_id,
            'enabled' => true,
            'modality' => $line->options['modality'] ?? '',
            'salary_cents' => (int) ($line->options['salary_cents'] ?? 0),
            'rate_mode' => $line->options['rate_mode'] ?? '',
            'units' => $line->options['units'] ?? '',
            'adjustment' => $line->options['adjustment'] ?? 'none',
            'discount_type' => $line->options['discount_type'] ?? 'percent',
            'discount_percent' => $line->options['discount_percent'] ?? '',
            'discount_value_cents' => (int) ($line->options['discount_value_cents'] ?? 0),
        ])->values()->all();
        $payload['has_legacy_services'] = $proposal->hasLegacyServices();
        $payload['legacy_summary'] = $this->legacySummaryLines($proposal);

        return $payload;
    }

    /**
     * @return array<int, array{key: string, label: string, cents: int}>
     */
    private function legacySummaryLines(CommercialProposal $proposal): array
    {
        $lines = [];

        $map = [
            ['flag' => $proposal->svc_pesquisas, 'key' => 'pesquisas', 'label' => 'Pesquisas e Organograma', 'col' => 'total_pesquisas_cents'],
            ['flag' => $proposal->svc_profiler, 'key' => 'profiler', 'label' => 'Profiler — Diagnóstico Comportamental', 'col' => 'total_profiler_cents'],
            ['flag' => filled($proposal->svc_devolutiva), 'key' => 'devolutiva', 'label' => 'Devolutiva e Diagnóstico', 'col' => 'total_devolutiva_cents'],
            ['flag' => $proposal->svc_nr1, 'key' => 'nr1', 'label' => 'NR-1 — Mapeamento (12 parcelas)', 'col' => 'total_nr1_cents'],
            ['flag' => filled($proposal->svc_nr1_implantacao_modo), 'key' => 'nr1_implantacao', 'label' => 'NR-1 — Implantação', 'col' => 'total_nr1_implantacao_cents'],
            ['flag' => $proposal->svc_contratacao, 'key' => 'contratacao', 'label' => 'Contratação / Recrutamento', 'col' => 'total_contratacao_cents'],
            ['flag' => $proposal->svc_direcionamento, 'key' => 'direcionamento', 'label' => 'Direcionamento Estratégico', 'col' => 'total_direcionamento_cents'],
            ['flag' => $proposal->svc_palestras, 'key' => 'palestras', 'label' => 'Palestras e Treinamentos', 'col' => 'total_palestras_cents'],
        ];

        foreach ($map as $item) {
            if ($item['flag']) {
                $lines[] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'cents' => (int) $proposal->{$item['col']},
                ];
            }
        }

        return $lines;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogProductsPayload(): array
    {
        return CommercialProduct::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (CommercialProduct $p) => $p->toCatalogArray())
            ->all();
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

            'palestra_topic' => ['nullable', 'string', 'max:500'],
            'palestra_event_date' => ['nullable', 'date'],
            'palestra_start_time' => ['nullable', 'string', 'max:32'],
            'palestra_duration_hours' => ['nullable', 'string', 'max:32'],
            'palestra_venue_address' => ['nullable', 'string', 'max:500'],
            'palestra_audience_estimate' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'palestra_format' => ['nullable', Rule::in(['presencial', 'online', 'hibrido'])],

            'is_closed' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'pdf_subtitle' => ['nullable', 'string', 'max:500'],
            'pdf_objetivo' => ['nullable', 'string', 'max:5000'],
            'service_descriptions' => ['nullable', 'array'],
            'service_descriptions.*' => ['nullable', 'string', 'max:10000'],

            'catalog_products' => ['nullable', 'array'],
            'catalog_products.*.product_id' => ['required', 'integer', Rule::exists('commercial_products', 'id')],
            'catalog_products.*.enabled' => ['boolean'],
            'catalog_products.*.modality' => ['nullable', 'string', 'max:64'],
            'catalog_products.*.salary_cents' => ['nullable', 'integer', 'min:0'],
            'catalog_products.*.rate_mode' => ['nullable', Rule::in(['hour', 'quantity', 'unit'])],
            'catalog_products.*.units' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'catalog_products.*.adjustment' => ['nullable', Rule::in(['none', 'bonus', 'discount'])],
            'catalog_products.*.discount_type' => ['nullable', Rule::in(['percent', 'value'])],
            'catalog_products.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catalog_products.*.discount_value_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['commission_percent'] = (float) (CommercialSetting::current()->default_commission_percent ?? 0);

        $data['service_descriptions'] = $this->normalizeServiceDescriptions(
            $data['service_descriptions'] ?? null
        );

        return $data;
    }

    /**
     * Remove entradas vazias ou iguais ao padrão — null significa "usar texto padrão".
     *
     * @param  array<string, string|null>|null  $descriptions
     * @return array<string, string>|null
     */
    private function normalizeServiceDescriptions(?array $descriptions): ?array
    {
        if ($descriptions === null) {
            return null;
        }

        $settings = CommercialSetting::current();
        $defaults = CommercialProposalPdfDefaults::serviceDescriptionsForSettings(
            $settings->pdf_descricoes_servicos
        );

        $catalogDescriptions = CommercialProduct::query()
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->pluck('description', 'slug')
            ->all();

        $normalized = [];
        foreach ($descriptions as $key => $text) {
            if (! is_string($key) || ! filled($text)) {
                continue;
            }

            $default = $defaults[$key] ?? ($catalogDescriptions[$key] ?? '');
            if (trim($text) === trim((string) $default)) {
                continue;
            }

            $normalized[$key] = trim($text);
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyDefaults(): array
    {
        return [
            'svc_pesquisas' => false,
            'svc_profiler' => false,
            'svc_devolutiva' => null,
            'svc_nr1' => false,
            'svc_nr1_implantacao_modo' => null,
            'svc_contratacao' => false,
            'svc_contratacao_salario_cents' => null,
            'svc_direcionamento' => false,
            'direcionamento_horas' => null,
            'svc_palestras' => false,
        ];
    }
}
