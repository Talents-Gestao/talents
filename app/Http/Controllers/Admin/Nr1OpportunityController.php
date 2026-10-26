<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\Nr1CampaignException;
use App\Http\Controllers\Controller;
use App\Models\Nr1Opportunity;
use App\Models\Survey;
use App\Services\Nr1\Nr1OpportunityService;
use App\Services\Nr1CommercialSerializer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comercial limitado (módulo nr1_oportunidades): fila de oportunidades (origem treinamento e solução) e resultado
 * agregado de campanhas PUBLICADAS. Só metadados e agregados do Nr1CommercialSerializer: não há exportação, download
 * nem rota de resposta bruta neste controller.
 */
class Nr1OpportunityController extends Controller
{
    public function __construct(
        private readonly Nr1CommercialSerializer $serializer,
        private readonly Nr1OpportunityService $opportunities,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'origin' => ['nullable', Rule::in(array_keys(Nr1Opportunity::ORIGIN_LABELS))],
            'status' => ['nullable', Rule::in(array_keys(Nr1Opportunity::STATUS_LABELS))],
        ]);

        $all = Nr1Opportunity::query();

        $queue = Nr1Opportunity::query()
            ->when($filters['origin'] ?? null, fn ($q, $origin) => $q->where('origin', $origin))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->with(['company:id,name', 'survey:id,title', 'requester:id,name', 'assignee:id,name'])
            ->orderByRaw("CASE WHEN status IN ('won', 'lost') THEN 1 ELSE 0 END")
            ->orderByDesc('last_activity_at')
            ->limit(200)
            ->get()
            ->map(fn (Nr1Opportunity $row) => $this->serializer->queueRow($row));

        $surveys = Survey::query()
            ->whereNotNull('published_at')
            ->with('company:id,name')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Survey $survey) => $this->serializer->summary($survey));

        return Inertia::render('Admin/Nr1Opportunities/Index', [
            'surveys' => $surveys,
            'queue' => $queue,
            'counters' => [
                'identified' => (clone $all)->count(),
                'in_contact' => (clone $all)->where('status', Nr1Opportunity::STATUS_IN_CONTACT)->count(),
                'to_qualify' => (clone $all)->whereIn('status', [Nr1Opportunity::STATUS_NEW, Nr1Opportunity::STATUS_TO_QUALIFY])->count(),
            ],
            'filters' => ['origin' => $filters['origin'] ?? '', 'status' => $filters['status'] ?? ''],
            'originLabels' => Nr1Opportunity::ORIGIN_LABELS,
            'statusLabels' => Nr1Opportunity::STATUS_LABELS,
            'canWork' => $request->user()->canAccessAdmin(\App\Enums\AdminPermissionModule::Nr1Oportunidades, \App\Enums\PermissionAction::Edit),
        ]);
    }

    public function show(int $survey): Response
    {
        $model = Survey::query()
            ->whereNotNull('published_at')
            ->with('company:id,name')
            ->find($survey);
        abort_if($model === null, 404);

        return Inertia::render('Admin/Nr1Opportunities/Show', [
            'opportunity' => $this->serializer->detail($model),
        ]);
    }

    public function showOpportunity(Request $request, Nr1Opportunity $opportunity): Response
    {
        return Inertia::render('Admin/Nr1Opportunities/Opportunity', [
            'opportunity' => $this->serializer->opportunityDetail($opportunity),
            'transitions' => collect(Nr1OpportunityService::TRANSITIONS[$opportunity->status] ?? [])
                ->map(fn (string $status) => ['value' => $status, 'label' => Nr1Opportunity::STATUS_LABELS[$status]])->values(),
            'canWork' => $request->user()->canAccessAdmin(\App\Enums\AdminPermissionModule::Nr1Oportunidades, \App\Enums\PermissionAction::Edit),
            'me' => $request->user()->id,
        ]);
    }

    public function assume(Request $request, Nr1Opportunity $opportunity): RedirectResponse
    {
        return $this->act(fn () => $this->opportunities->assume($opportunity, $request->user()), 'Oportunidade assumida.');
    }

    public function changeStatus(Request $request, Nr1Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Nr1Opportunity::STATUS_LABELS))],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->act(fn () => $this->opportunities->changeStatus($opportunity, $request->user(), $data['status'], $data['note'] ?? null), 'Estado atualizado.');
    }

    public function nextAction(Request $request, Nr1Opportunity $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'next_action' => ['required', 'string', 'max:1000'],
            'next_action_at' => ['nullable', 'date'],
        ]);

        return $this->act(fn () => $this->opportunities->setNextAction($opportunity, $request->user(), $data['next_action'], $data['next_action_at'] ?? null), 'Próxima ação registrada.');
    }

    private function act(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (Nr1CampaignException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $success);
    }
}
