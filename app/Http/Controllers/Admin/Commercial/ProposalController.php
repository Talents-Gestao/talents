<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Actions\Hiring\CreateHiringProcessFromClosedProposal;
use App\Actions\Notices\PublishCommercialNotice;
use App\Enums\ProposalLostReason;
use App\Http\Controllers\Controller;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialProduct;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Models\FinancePaymentMethod;
use App\Models\User;
use App\Services\CommercialPricingService;
use App\Services\CommercialProposalPdfService;
use App\Models\CommercialSaleInstallment;
use App\Support\Commercial\CommercialCodeSearch;
use App\Support\Commercial\OptionalCommission;
use App\Support\Commercial\ProposalListStatus;
use App\Support\CommercialProposalPdfDefaults;
use App\Support\CommercialProposalPdfOptionalSections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProposalController extends Controller
{
    public function __construct(
        private readonly CommercialPricingService $pricing,
        private readonly PublishCommercialNotice $notices,
        private readonly CreateHiringProcessFromClosedProposal $createHiringFromClosedProposal,
    ) {}

    public function index(Request $request): Response
    {
        $request->merge([
            'search' => $request->filled('search') ? $request->input('search') : null,
            'seller_id' => $request->filled('seller_id') ? $request->input('seller_id') : null,
            'status' => $request->filled('status') ? $request->input('status') : null,
            'sale_situation' => $request->filled('sale_situation') ? $request->input('sale_situation') : null,
            'created_from' => $request->filled('created_from') ? $request->input('created_from') : null,
            'created_to' => $request->filled('created_to') ? $request->input('created_to') : null,
            'hide_ended' => $request->has('hide_ended') ? $request->input('hide_ended') : null,
        ]);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'seller_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in([
                'abertas',
                'fechadas',
                'perdidas',
                // Filtros legados (compat. de links).
                'em_negociacao',
                'em_andamento',
                'aprovadas',
                'encerradas',
            ])],
            'sale_situation' => ['nullable', 'string', Rule::in(['without_sale', 'with_sale'])],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'hide_ended' => ['nullable', 'boolean'],
        ], [
            'created_to.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ]);

        $statusFilter = $validated['status'] ?? '';
        // Padrão: não exibir perdidas. Chip «Perdidas» tem prioridade.
        $hideEndedRequested = array_key_exists('hide_ended', $validated) && $validated['hide_ended'] !== null
            ? (bool) $validated['hide_ended']
            : true;
        $hideEnded = $hideEndedRequested && ! in_array($statusFilter, ['encerradas', 'perdidas'], true);

        $filters = [
            'search' => $validated['search'] ?? '',
            'seller_id' => isset($validated['seller_id']) ? (string) $validated['seller_id'] : '',
            'status' => $statusFilter,
            'sale_situation' => $validated['sale_situation'] ?? '',
            'created_from' => $validated['created_from'] ?? '',
            'created_to' => $validated['created_to'] ?? '',
            'hide_ended' => $hideEnded,
        ];

        $baseQuery = CommercialProposal::query();
        $this->applyProposalIndexFilters($baseQuery, $filters, includeStatus: false);

        $statusCounts = [
            'all' => (clone $baseQuery)->count(),
            'abertas' => ProposalListStatus::applyFilter((clone $baseQuery), 'abertas')->count(),
            'fechadas' => ProposalListStatus::applyFilter((clone $baseQuery), 'fechadas')->count(),
            'perdidas' => ProposalListStatus::applyFilter((clone $baseQuery), 'perdidas')->count(),
            // Compat. com UI/links antigos.
            'em_negociacao' => 0,
            'aprovadas' => ProposalListStatus::applyFilter((clone $baseQuery), 'fechadas')->count(),
            'encerradas' => ProposalListStatus::applyFilter((clone $baseQuery), 'perdidas')->count(),
        ];

        $q = CommercialProposal::query()
            ->with([
                'seller:id,name',
                'sale' => function ($saleQuery): void {
                    $saleQuery->select('id', 'proposal_id', 'code', 'status', 'installments_count')
                        ->withCount([
                            'installments as paid_installments_count' => fn ($iq) => $iq
                                ->where('status', CommercialSaleInstallment::STATUS_PAGO),
                            'installments as total_installments_count',
                        ]);
                },
            ])
            ->orderByDesc('created_at');

        $this->applyProposalIndexFilters($q, $filters, includeStatus: true);

        $proposals = $q->paginate(15)->withQueryString();

        $proposals->getCollection()->transform(function (CommercialProposal $proposal) {
            $listStatus = ProposalListStatus::for($proposal);
            $arr = $proposal->toArray();
            $arr['list_status'] = $listStatus;
            $arr['list_status_label'] = ProposalListStatus::label($listStatus);
            $arr['lost_reason'] = $proposal->lost_reason;
            $arr['lost_reason_label'] = ProposalLostReason::tryFrom((string) ($proposal->lost_reason ?? ''))?->label();
            $arr['lost_reason_notes'] = $proposal->lost_reason_notes;
            $arr['paid_installments'] = $proposal->sale?->paid_installments_count;
            $arr['total_installments'] = $proposal->sale?->total_installments_count
                ?? $proposal->sale?->installments_count;
            $arr['can_reopen'] = $proposal->canReopen();

            return $arr;
        });

        $commercialSettings = CommercialSetting::current();

        return Inertia::render('Admin/Commercial/Proposals/Index', [
            'proposals' => $proposals,
            'sellers' => $this->sellersOptions(),
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'lostReasonOptions' => ProposalLostReason::options(),
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
            'default_commission_percent' => (float) ($commercialSettings->default_commission_percent ?? 0),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Commercial/Proposals/Form', [
            'mode' => 'create',
            'proposal' => null,
            'sellers' => $this->sellersOptions(),
            'settings' => $this->publicSettings(),
            'catalogProducts' => $this->catalogProductsPayload(),
            'pdfOptionalSectionOptions' => CommercialProposalPdfOptionalSections::options(),
            'paymentMethodOptions' => $this->paymentMethodOptions(null),
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
                'list_status' => ($data['is_closed'] ?? false)
                    ? ProposalListStatus::CLOSED
                    : ProposalListStatus::OPEN,
            ],
        ));

        $this->syncCatalogLines($proposal, $catalogLines);

        $hiringFlash = null;
        if ($proposal->is_closed) {
            $this->notices->proposalWon($proposal, $request->user());
            $hiringFlash = $this->createHiringFromClosedProposal->handle($proposal, $request->user())['flash'] ?? null;
        } else {
            $this->notices->proposalCreated($proposal, $request->user());
        }

        $redirect = redirect()
            ->route('admin.comercial.propostas.index')
            ->with('success', "Proposta {$proposal->code} criada.");

        return $hiringFlash !== null
            ? $redirect->with('info', $hiringFlash)
            : $redirect;
    }

    public function edit(CommercialProposal $proposal): Response
    {
        $commercialSettings = CommercialSetting::current();

        return Inertia::render('Admin/Commercial/Proposals/Form', [
            'mode' => 'edit',
            'proposal' => $this->proposalFormPayload($proposal),
            'sellers' => $this->sellersOptions(),
            'settings' => $this->publicSettings(),
            'catalogProducts' => $this->catalogProductsPayload(),
            'pdfOptionalSectionOptions' => CommercialProposalPdfOptionalSections::options(),
            'paymentMethodOptions' => $this->paymentMethodOptions($proposal),
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
            'list_status' => match (true) {
                $isClosed => ProposalListStatus::CLOSED,
                $wasClosed => ProposalListStatus::OPEN,
                default => in_array(
                    ProposalListStatus::normalize((string) ($proposal->list_status ?? '')),
                    ProposalListStatus::values(),
                    true,
                )
                    ? ProposalListStatus::normalize((string) $proposal->list_status)
                    : ProposalListStatus::OPEN,
            },
        ]));

        $this->syncCatalogLines($proposal, $catalogLines);

        $hiringFlash = null;
        if ($isClosed && ! $wasClosed) {
            $this->notices->proposalWon($proposal->refresh(), $request->user());
            $hiringFlash = $this->createHiringFromClosedProposal->handle($proposal, $request->user())['flash'] ?? null;
        }

        $proposal->refresh()->load('contracts');

        $redirect = redirect()->route('admin.comercial.propostas.edit', $proposal);

        if ($proposal->hasSignedContract()) {
            $redirect = $redirect
                ->with('success', 'Proposta atualizada. Há contrato assinado: gere um novo atualizado para enviar ao cliente com a alteração.')
                ->with('suggest_updated_contract', true);
        } elseif ($proposal->hasZapSignSentContract()) {
            $redirect = $redirect
                ->with('success', 'Proposta atualizada. Há contrato enviado ao ZapSign: se a alteração for relevante, gere um novo PDF e envie novamente (o anterior permanece no histórico).')
                ->with('suggest_updated_contract', true);
        } else {
            $redirect = $redirect->with('success', 'Proposta atualizada.');
        }

        return $hiringFlash !== null
            ? $redirect->with('info', $hiringFlash)
            : $redirect;
    }

    /**
     * Reabre proposta fechada/perdida para alteração de última hora (sem venda).
     */
    public function reopen(Request $request, CommercialProposal $proposal): RedirectResponse
    {
        if ($proposal->hasSale()) {
            throw ValidationException::withMessages([
                'proposal' => 'Não é possível reabrir uma proposta que já possui venda vinculada.',
            ]);
        }

        if (! $proposal->canReopen()) {
            return redirect()
                ->back()
                ->with('success', 'A proposta já está em aberto para edição.');
        }

        $proposal->update([
            'is_closed' => false,
            'closed_at' => null,
            'list_status' => ProposalListStatus::OPEN,
            'lost_reason' => null,
            'lost_reason_notes' => null,
        ]);

        $message = 'Proposta reaberta. Pode editar os dados.';
        if ($proposal->fresh()->hasSignedContract()) {
            $message .= ' Como há contrato assinado, após salvar gere um contrato novo e envie ao cliente.';
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    public function updateStatus(Request $request, CommercialProposal $proposal): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                ...ProposalListStatus::values(),
                // Slugs legados (normalizados no update).
                'in_progress',
                'negotiation',
                'approved',
            ])],
            'lost_reason' => ['nullable', 'string', Rule::enum(ProposalLostReason::class)],
            'lost_reason_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'status.required' => 'Selecione o status.',
            'status.in' => 'Status inválido.',
            'lost_reason_notes.max' => 'A justificativa não pode ter mais de 5000 caracteres.',
        ]);

        $listStatus = ProposalListStatus::normalize($data['status']);
        $wasClosed = (bool) $proposal->is_closed;
        $isClosed = ProposalListStatus::impliesClosed($listStatus);

        if ($proposal->hasSale() && ! $isClosed) {
            throw ValidationException::withMessages([
                'status' => 'Não é possível reabrir uma proposta que já possui venda vinculada.',
            ]);
        }

        $lostReason = null;
        $lostReasonNotes = null;

        if ($listStatus === ProposalListStatus::ENDED) {
            $reasonRaw = $data['lost_reason'] ?? null;
            if (! is_string($reasonRaw) || $reasonRaw === '') {
                throw ValidationException::withMessages([
                    'lost_reason' => 'Selecione o motivo da perda.',
                ]);
            }

            $lostReason = ProposalLostReason::from($reasonRaw);
            $notes = isset($data['lost_reason_notes']) ? trim((string) $data['lost_reason_notes']) : '';

            if ($lostReason === ProposalLostReason::Outros && $notes === '') {
                throw ValidationException::withMessages([
                    'lost_reason_notes' => 'Descreva a justificativa quando o motivo for «Outros».',
                ]);
            }

            $lostReasonNotes = $notes !== '' ? $notes : null;
        }

        $proposal->update([
            'list_status' => $listStatus,
            'is_closed' => $isClosed,
            'closed_at' => match (true) {
                $isClosed && $proposal->closed_at === null => now(),
                ! $isClosed => null,
                default => $proposal->closed_at,
            },
            'lost_reason' => $lostReason?->value,
            'lost_reason_notes' => $lostReasonNotes,
        ]);

        if ($listStatus === ProposalListStatus::CLOSED && ! $wasClosed) {
            $this->notices->proposalWon($proposal->refresh(), $request->user());
            $hiringFlash = $this->createHiringFromClosedProposal->handle($proposal, $request->user())['flash'] ?? null;
        } else {
            $hiringFlash = null;
        }

        $label = ProposalListStatus::label($listStatus);

        $redirect = redirect()
            ->route('admin.comercial.propostas.index')
            ->with('success', "Status da proposta {$proposal->code} atualizado para «{$label}».");

        return $hiringFlash !== null
            ? $redirect->with('info', $hiringFlash)
            : $redirect;
    }

    public function destroy(CommercialProposal $proposal): RedirectResponse
    {
        $code = (string) $proposal->code;

        DB::transaction(function () use ($proposal): void {
            // Venda/parcelas/comissão: remover antes (FK nullOnDelete deixaria órfãos financeiros).
            $sale = $proposal->sale()->first();
            if ($sale) {
                $sale->delete();
            }

            $proposal->delete();
        });

        return redirect()
            ->route('admin.comercial.propostas.index')
            ->with('success', "Proposta {$code} removida.");
    }

    public function pdf(CommercialProposal $proposal, CommercialProposalPdfService $pdfService): SymfonyResponse
    {
        $proposal->load('seller:id,name,email');

        return $pdfService
            ->generate($proposal)
            ->stream("proposta-{$proposal->code}.pdf");
    }

    /**
     * Filtros partilhados da lista (contagens de status excluem o filtro de status).
     *
     * @param  Builder<CommercialProposal>  $query
     * @param  array{
     *     search: string,
     *     seller_id: string,
     *     status: string,
     *     sale_situation: string,
     *     created_from: string,
     *     created_to: string,
     *     hide_ended: bool
     * }  $filters
     */
    private function applyProposalIndexFilters(Builder $query, array $filters, bool $includeStatus): void
    {
        if (filled($filters['search'])) {
            $s = CommercialCodeSearch::normalizeTerm((string) $filters['search']);
            $query->where(function ($inner) use ($s): void {
                $inner->where('client_name', 'like', '%'.$s.'%')
                    ->orWhere('code', 'like', '%'.$s.'%')
                    ->orWhere('client_cnpj', 'like', '%'.$s.'%');
            });
        }

        if (filled($filters['seller_id'])) {
            $query->where('seller_id', (int) $filters['seller_id']);
        }

        if (($filters['sale_situation'] ?? '') === 'without_sale') {
            $query->whereDoesntHave('sale');
        } elseif (($filters['sale_situation'] ?? '') === 'with_sale') {
            $query->whereHas('sale');
        }

        if (filled($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (filled($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if ($includeStatus && filled($filters['status'])) {
            ProposalListStatus::applyFilter($query, (string) $filters['status']);
        }

        // hide_ended só na listagem (includeStatus), não nas contagens dos chips.
        if ($includeStatus && ($filters['hide_ended'] ?? false)) {
            ProposalListStatus::excludeEnded($query);
        }
    }

    /**
     * @return array<int, array{id:int,name:string,is_owner:bool,commission_percent:float}>
     */
    private function sellersOptions(): array
    {
        return User::query()
            ->where('is_commercial', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_owner', 'commission_percent'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'is_owner' => (bool) $u->is_owner,
                'commission_percent' => (float) ($u->commission_percent ?? 0),
            ])
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
        $data = $this->validateProposal($request, $existing);
        $data = $this->applyPaymentMethodSnapshot($data);
        $data = $this->normalizeRecurringFields($data);
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

        $this->assertProposalHasPricedServices($data, $catalogLines, $existing);

        if (! empty($data['is_recurring'])) {
            $catalogLines = [];
            $periodTotal = (int) $data['recurring_months'] * (int) $data['recurring_monthly_cents'];
            $commissionPercent = (float) ($data['commission_percent'] ?? $totals['commission_percent'] ?? 0);
            $totals['total_final_cents'] = $periodTotal;
            $totals['commission_percent'] = $commissionPercent;
            $totals['commission_cents'] = (int) round($periodTotal * $commissionPercent / 100);
        }

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
     * Proposta pontual precisa de pelo menos um serviço com valor — senão o contrato sai com lista vazia.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $catalogLines
     */
    private function assertProposalHasPricedServices(
        array $data,
        array $catalogLines,
        ?CommercialProposal $existing,
    ): void {
        if (! empty($data['is_recurring'])) {
            return;
        }

        if ($existing?->hasLegacyServices()) {
            return;
        }

        foreach ($catalogLines as $line) {
            if ((int) ($line['value_cents'] ?? 0) > 0) {
                return;
            }

            $options = is_array($line['options'] ?? null) ? $line['options'] : [];
            if (($options['adjustment'] ?? '') === 'bonus' && (int) ($options['subtotal_cents'] ?? 0) > 0) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'catalog_products' => 'Selecione pelo menos um produto com valor. Sem produtos, o contrato fica sem a lista de serviços.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalFormPayload(CommercialProposal $proposal): array
    {
        $proposal->load([
            'seller:id,name',
            'sale' => fn ($q) => $q->withCount('installments')->with('commission'),
            'contracts' => fn ($q) => $q->orderByDesc('generated_at')
                ->select([
                    'id',
                    'proposal_id',
                    'code',
                    'template_name_snapshot',
                    'generated_at',
                    'zapsign_status',
                    'zapsign_sent_at',
                    'zapsign_document_token',
                ]),
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
            'custom_cents' => (int) ($line->options['custom_cents'] ?? 0),
            'adjustment' => $line->options['adjustment'] ?? 'none',
            'discount_type' => $line->options['discount_type'] ?? 'percent',
            'discount_percent' => $line->options['discount_percent'] ?? '',
            'discount_value_cents' => (int) ($line->options['discount_value_cents'] ?? 0),
            'observation' => (string) ($line->options['observation'] ?? ''),
        ])->values()->all();
        $payload['contracts'] = $proposal->contracts->map(fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'template_name_snapshot' => $c->template_name_snapshot,
            'generated_at' => $c->generated_at?->toIso8601String(),
            'zapsign_status' => $c->zapsign_status,
            'zapsign_sent_at' => $c->zapsign_sent_at?->toIso8601String(),
            'zapsign_sent' => $c->wasSentToZapSign(),
            'zapsign_signed' => $c->isZapSignSigned(),
            'zapsign_status_label' => $c->zapSignStatusLabel(),
        ])->values()->all();
        $payload['has_sale'] = $proposal->hasSale();
        $payload['can_reopen'] = $proposal->canReopen();
        $payload['has_signed_contract'] = $proposal->hasSignedContract();
        $payload['has_zapsign_sent_contract'] = $proposal->hasZapSignSentContract();
        $payload['has_legacy_services'] = $proposal->hasLegacyServices();
        $payload['legacy_summary'] = $this->legacySummaryLines($proposal);
        $payload['finance_impact'] = $this->financeImpactPayload($proposal);
        $payload['pdf_optional_sections'] = CommercialProposalPdfOptionalSections::normalizeSelection(
            $proposal->pdf_optional_sections
        );
        $payload['recurring_monthly_reais'] = $proposal->recurring_monthly_cents !== null
            ? number_format(((int) $proposal->recurring_monthly_cents) / 100, 2, '.', '')
            : '';

        return $payload;
    }

    /**
     * Áreas financeiras/comerciais impactadas ao editar ou excluir proposta fechada/convertida.
     *
     * @return array{
     *     requires_warning: bool,
     *     is_closed: bool,
     *     has_sale: bool,
     *     items: list<array{key: string, label: string, detail: string, href: string|null}>
     * }
     */
    private function financeImpactPayload(CommercialProposal $proposal): array
    {
        $sale = $proposal->relationLoaded('sale')
            ? $proposal->sale
            : $proposal->sale()->withCount('installments')->with('commission')->first();

        if ($sale && ! $sale->relationLoaded('commission')) {
            $sale->load('commission');
        }
        if ($sale && ! isset($sale->installments_count) && ! $sale->relationLoaded('installments')) {
            $sale->loadCount('installments');
        }

        $isClosed = (bool) $proposal->is_closed
            || ProposalListStatus::for($proposal) === ProposalListStatus::CLOSED;
        $hasSale = $sale !== null;
        $items = [];

        if ($isClosed) {
            $items[] = [
                'key' => 'status',
                'label' => 'Comercial · Propostas / Contratos fechados',
                'detail' => 'A proposta continua marcada como fechada (status verde) no funil comercial.',
                'href' => route('admin.comercial.propostas.index', ['status' => 'fechadas']),
            ];
        }

        if ($hasSale) {
            $installments = (int) ($sale->installments_count
                ?? $sale->total_installments_count
                ?? $sale->installments()->count());

            $items[] = [
                'key' => 'venda',
                'label' => "Financeiro · Venda {$sale->code}",
                'detail' => 'A venda vinculada permanece no Financeiro. Valores da proposta não recalculam a venda automaticamente.',
                'href' => route('admin.financeiro.vendas.show', $sale),
            ];

            $items[] = [
                'key' => 'receber',
                'label' => 'Financeiro · Contas a receber',
                'detail' => $installments === 1
                    ? '1 parcela desta venda entra no saldo a receber / fluxo de caixa.'
                    : "{$installments} parcelas desta venda entram no saldo a receber / fluxo de caixa.",
                'href' => route('admin.financeiro.contas-a-receber.index'),
            ];

            if ($sale->commission && (int) $sale->commission->amount_cents > 0) {
                $items[] = [
                    'key' => 'comissao',
                    'label' => 'Financeiro · Comissões',
                    'detail' => 'Há comissão a pagar vinculada a esta venda.',
                    'href' => route('admin.financeiro.comissoes.index'),
                ];
            }
        }

        return [
            'requires_warning' => $isClosed || $hasSale,
            'is_closed' => $isClosed,
            'has_sale' => $hasSale,
            'items' => $items,
        ];
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
            ['flag' => $proposal->svc_nr1, 'key' => 'nr1', 'label' => 'NR-1 — Mapeamento', 'col' => 'total_nr1_cents'],
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
     * @return array<int, array{value: int, label: string, slug: string}>
     */
    private function paymentMethodOptions(?CommercialProposal $proposal): array
    {
        $currentId = $proposal?->payment_method_id;

        return FinancePaymentMethod::query()
            ->where(function ($q) use ($currentId): void {
                $q->where('is_active', true);
                if ($currentId) {
                    $q->orWhere('id', $currentId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (FinancePaymentMethod $m) => [
                'value' => $m->id,
                'label' => $m->name,
                'slug' => $m->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyPaymentMethodSnapshot(array $data): array
    {
        $methodId = isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null;
        unset($data['payment_method']);

        if (! $methodId) {
            $data['payment_method_id'] = null;
            $data['payment_method'] = null;
            $data['payment_method_label'] = null;

            return $data;
        }

        $method = FinancePaymentMethod::query()->findOrFail($methodId);
        $data['payment_method_id'] = $method->id;
        $data['payment_method'] = $method->slug;
        $data['payment_method_label'] = $method->name;

        return $data;
    }

    private function validateProposal(Request $request, ?CommercialProposal $existing = null): array
    {
        $currentPaymentMethodId = $existing?->payment_method_id;

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
            'include_publico_atendido' => ['boolean'],

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
            'is_recurring' => ['boolean'],
            'recurring_months' => [
                Rule::requiredIf(fn () => $request->boolean('is_recurring')),
                'nullable',
                'integer',
                'min:1',
                'max:60',
            ],
            'recurring_monthly_reais' => [
                Rule::requiredIf(fn () => $request->boolean('is_recurring')),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'recurring_notes' => ['nullable', 'string', 'max:2000'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('finance_payment_methods', 'id')->where(function ($query) use ($currentPaymentMethodId): void {
                    $query->where(function ($inner) use ($currentPaymentMethodId): void {
                        $inner->where('is_active', true);
                        if ($currentPaymentMethodId) {
                            $inner->orWhere('id', $currentPaymentMethodId);
                        }
                    });
                }),
            ],
            'include_minimum_stay' => ['boolean'],

            'pay_commission' => ['nullable', 'boolean'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'pdf_subtitle' => ['nullable', 'string', 'max:500'],
            'pdf_objetivo' => ['nullable', 'string', 'max:5000'],
            'service_descriptions' => ['nullable', 'array'],
            'service_descriptions.*' => ['nullable', 'string', 'max:10000'],

            'pdf_optional_sections' => ['nullable', 'array'],
            'pdf_optional_sections.*' => ['boolean'],

            'catalog_products' => ['nullable', 'array'],
            'catalog_products.*.product_id' => ['required', 'integer', Rule::exists('commercial_products', 'id')],
            'catalog_products.*.enabled' => ['boolean'],
            'catalog_products.*.modality' => ['nullable', 'string', 'max:64'],
            'catalog_products.*.salary_cents' => ['nullable', 'integer', 'min:0'],
            'catalog_products.*.rate_mode' => ['nullable', Rule::in(['hour', 'quantity', 'unit', 'custom'])],
            'catalog_products.*.units' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'catalog_products.*.custom_cents' => ['nullable', 'integer', 'min:0'],
            'catalog_products.*.adjustment' => ['nullable', Rule::in(['none', 'bonus', 'discount'])],
            'catalog_products.*.discount_type' => ['nullable', Rule::in(['percent', 'value'])],
            'catalog_products.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catalog_products.*.discount_value_cents' => ['nullable', 'integer', 'min:0'],
            'catalog_products.*.observation' => ['nullable', 'string', 'max:2000'],
        ], [
            'client_name.required' => 'Informe o nome / razão social.',
            'employee_count.required' => 'Informe o número de funcionários.',
            'employee_count.integer' => 'O número de funcionários deve ser um inteiro.',
            'employee_count.min' => 'O número de funcionários não pode ser negativo.',
            'payment_method_id.required' => 'Selecione a forma de pagamento.',
            'payment_method_id.exists' => 'Forma de pagamento inválida ou inativa.',
            'client_email.email' => 'Informe um e-mail válido.',
            'recurring_months.required' => 'Informe a duração em meses do serviço recorrente.',
            'recurring_months.min' => 'A duração deve ser de pelo menos 1 mês.',
            'recurring_months.max' => 'A duração não pode ser maior que 60 meses.',
            'recurring_monthly_reais.required' => 'Informe o valor mensal.',
            'recurring_monthly_reais.min' => 'O valor mensal deve ser maior que zero.',
            'commission_percent.numeric' => 'O percentual de comissão deve ser numérico.',
            'commission_percent.min' => 'O percentual de comissão não pode ser negativo.',
            'commission_percent.max' => 'O percentual de comissão não pode ser maior que 100.',
        ]);

        $seller = filled($data['seller_id'] ?? null)
            ? User::query()->find((int) $data['seller_id'])
            : null;
        $data['commission_percent'] = OptionalCommission::resolveForProposal($seller);
        unset($data['pay_commission']);

        $data['include_publico_atendido'] = (bool) ($data['include_publico_atendido'] ?? true);
        $data['include_minimum_stay'] = (bool) ($data['include_minimum_stay'] ?? true);
        $data['is_recurring'] = (bool) ($data['is_recurring'] ?? false);

        $data['service_descriptions'] = $this->normalizeServiceDescriptions(
            $data['service_descriptions'] ?? null
        );

        $data['pdf_optional_sections'] = $this->normalizePdfOptionalSections(
            $data['pdf_optional_sections'] ?? null
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRecurringFields(array $data): array
    {
        if (empty($data['is_recurring'])) {
            $data['is_recurring'] = false;
            $data['recurring_months'] = null;
            $data['recurring_monthly_cents'] = null;
            $data['recurring_notes'] = null;
            unset($data['recurring_monthly_reais']);

            return $data;
        }

        $data['is_recurring'] = true;
        $data['recurring_months'] = (int) $data['recurring_months'];
        $data['recurring_monthly_cents'] = (int) round(((float) $data['recurring_monthly_reais']) * 100);
        $data['recurring_notes'] = filled($data['recurring_notes'] ?? null)
            ? trim((string) $data['recurring_notes'])
            : null;
        unset($data['recurring_monthly_reais']);

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
     * @param  array<string, bool>|null  $sections
     * @return array<string, bool>|null
     */
    private function normalizePdfOptionalSections(?array $sections): ?array
    {
        if ($sections === null) {
            return null;
        }

        $normalized = CommercialProposalPdfOptionalSections::normalizeSelection($sections);

        return array_filter($normalized) === [] ? null : $normalized;
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
