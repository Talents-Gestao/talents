<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesSuggestionRoutes;
use App\Http\Controllers\Controller;
use App\Models\TeamSuggestion;
use App\Support\Suggestions\SuggestionCatalog;
use App\Support\Suggestions\SuggestionCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuggestionController extends Controller
{
    use ResolvesSuggestionRoutes;

    public function index(Request $request): Response
    {
        $context = app(SuggestionCompanyContext::class);

        if ($context->needsCompanySelection($request)) {
            return Inertia::render('Client/Suggestions/Index', [
                'suggestions' => ['data' => [], 'links' => []],
                'companyPicker' => $context->availableCompanies(),
                'activeCompany' => null,
                'isAdminContext' => true,
                'publicUrl' => null,
            ]);
        }

        $company = $context->resolve($request);

        $suggestions = TeamSuggestion::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (TeamSuggestion $suggestion) => [
                'id' => $suggestion->id,
                'topic_label' => SuggestionCatalog::label(SuggestionCatalog::topics(), $suggestion->topic),
                'area_label' => SuggestionCatalog::label(SuggestionCatalog::areas(), $suggestion->area),
                'response_label' => SuggestionCatalog::label(
                    SuggestionCatalog::responsePreferences(),
                    $suggestion->response_preference,
                ),
                'status' => $suggestion->status,
                'status_label' => SuggestionCatalog::label(SuggestionCatalog::statuses(), $suggestion->status),
                'has_contact' => $suggestion->hasStoredEncrypted('contact')
                    || $suggestion->hasStoredEncrypted('reporter_name'),
                'created_at' => $suggestion->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Client/Suggestions/Index', [
            'suggestions' => $suggestions,
            'companyPicker' => $context->isAdminContext($request) ? $context->availableCompanies() : null,
            'activeCompany' => $company->only(['id', 'name']),
            'isAdminContext' => $context->isAdminContext($request),
            'publicUrl' => $company->complaints_public_token
                ? route('sugestao.create', $company->complaints_public_token)
                : null,
        ]);
    }

    public function show(Request $request, TeamSuggestion $suggestion): Response
    {
        abort_unless($suggestion->company_id === app(SuggestionCompanyContext::class)->resolve($request)->id, 404);

        return Inertia::render('Client/Suggestions/Show', [
            'suggestion' => [
                'id' => $suggestion->id,
                'topic_label' => SuggestionCatalog::label(SuggestionCatalog::topics(), $suggestion->topic),
                'area_label' => SuggestionCatalog::label(SuggestionCatalog::areas(), $suggestion->area),
                'response_preference' => $suggestion->response_preference,
                'response_label' => SuggestionCatalog::label(
                    SuggestionCatalog::responsePreferences(),
                    $suggestion->response_preference,
                ),
                'status' => $suggestion->status,
                'status_label' => SuggestionCatalog::label(SuggestionCatalog::statuses(), $suggestion->status),
                'message' => $suggestion->safeDecrypt('message', TeamSuggestion::UNREADABLE_ENCRYPTED_PLACEHOLDER),
                'reporter_name' => $suggestion->response_preference === 'sim'
                    ? $suggestion->safeDecrypt('reporter_name', TeamSuggestion::UNREADABLE_ENCRYPTED_PLACEHOLDER)
                    : null,
                'contact' => $suggestion->response_preference === 'sim'
                    ? $suggestion->safeDecrypt('contact', TeamSuggestion::UNREADABLE_ENCRYPTED_PLACEHOLDER)
                    : null,
                'created_at' => $suggestion->created_at?->toIso8601String(),
            ],
            'statuses' => SuggestionCatalog::statuses(),
            'isAdminContext' => app(SuggestionCompanyContext::class)->isAdminContext($request),
        ]);
    }

    public function updateStatus(Request $request, TeamSuggestion $suggestion): RedirectResponse
    {
        abort_unless($suggestion->company_id === app(SuggestionCompanyContext::class)->resolve($request)->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(SuggestionCatalog::statuses()))],
        ]);

        $suggestion->update(['status' => $data['status']]);

        return back()->with('success', 'Status atualizado.');
    }
}
