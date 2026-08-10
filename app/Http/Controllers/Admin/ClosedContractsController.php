<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommercialProposal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Histórico de fechamentos (Clientes → Contratos fechados).
 *
 * Definição v1: proposta com is_closed = true (sem exigir ZapSign assinado nem venda).
 * Permissão: Companies (igual ao Coming Soon anterior). Sem company_id — match por texto/CNPJ.
 */
class ClosedContractsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $sellerId = $request->integer('seller_id') ?: null;
        $hasContract = $request->boolean('has_contract');
        $hasSale = $request->boolean('has_sale');
        $closedFrom = $this->parseDate($request->input('closed_from'));
        $closedTo = $this->parseDate($request->input('closed_to'));

        $query = CommercialProposal::query()
            ->closed()
            ->with([
                'seller:id,name',
                'sale:id,proposal_id,code',
                'contracts' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->orderByDesc('closed_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($search, $operator) {
                $inner->where('client_name', $operator, '%'.$search.'%')
                    ->orWhere('code', $operator, '%'.$search.'%')
                    ->orWhere('client_cnpj', $operator, '%'.$search.'%');
            });
        }

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        if ($closedFrom) {
            $query->whereDate('closed_at', '>=', $closedFrom);
        }

        if ($closedTo) {
            $query->whereDate('closed_at', '<=', $closedTo);
        }

        if ($request->has('has_contract') && $hasContract) {
            $query->whereHas('contracts');
        }

        if ($request->has('has_sale') && $hasSale) {
            $query->whereHas('sale');
        }

        $proposals = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CommercialProposal $proposal) => $this->listPayload($proposal));

        return Inertia::render('Admin/ClosedContracts/Index', [
            'proposals' => $proposals,
            'filters' => [
                'search' => $search !== '' ? $search : '',
                'seller_id' => $sellerId ? (string) $sellerId : '',
                'closed_from' => $closedFrom?->format('Y-m-d') ?? '',
                'closed_to' => $closedTo?->format('Y-m-d') ?? '',
                'has_contract' => $request->has('has_contract') ? $hasContract : false,
                'has_sale' => $request->has('has_sale') ? $hasSale : false,
            ],
            'sellers' => User::query()
                ->where('is_commercial', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(CommercialProposal $proposal): array
    {
        $contract = $proposal->contracts->first();
        $sale = $proposal->sale;

        $hasZapSign = $contract !== null && (
            filled($contract->zapsign_status) || $contract->zapsign_sent_at !== null
        );

        return [
            'id' => $proposal->id,
            'code' => $proposal->code,
            'client_name' => $proposal->client_name,
            'client_cnpj' => $proposal->client_cnpj,
            'closed_at' => optional($proposal->closed_at)?->toIso8601String(),
            'total_final_cents' => (int) $proposal->total_final_cents,
            'seller' => $proposal->seller
                ? ['id' => $proposal->seller->id, 'name' => $proposal->seller->name]
                : null,
            'badges' => [
                'closed' => true,
                'has_contract' => $contract !== null,
                'has_zapsign' => $hasZapSign,
                'has_sale' => $sale !== null,
            ],
            'latest_contract_id' => $contract?->id,
            'sale_id' => $sale?->id,
            'sale_code' => $sale?->code,
        ];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
