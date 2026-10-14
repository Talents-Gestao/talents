<?php

namespace App\Http\Controllers\Client;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\PurposeMapCampaign;
use App\Models\Survey;
use App\Models\TeamSuggestion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VozDoTimeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $user->company;

        $surveysCount = 0;
        $purposeMapCount = 0;
        $complaintsCount = 0;
        $openComplaintsCount = 0;
        $suggestionsCount = 0;
        $newSuggestionsCount = 0;

        $canSurveys = $user->canAccess(PermissionModule::Pesquisas, PermissionAction::View);
        $canComplaints = $user->canAccess(PermissionModule::Denuncias, PermissionAction::View);
        $canSuggestions = $user->canAccess(PermissionModule::Sugestoes, PermissionAction::View);

        if (! $canSurveys && ! $canComplaints && ! $canSuggestions) {
            abort(403, 'Sem permissão para esta área.');
        }

        if ($canSurveys) {
            $surveysCount = Survey::query()
                ->where('company_id', $company->id)
                ->count();

            $purposeMapCount = PurposeMapCampaign::query()
                ->where('company_id', $company->id)
                ->count();
        }

        if ($canComplaints) {
            $complaintsCount = Complaint::query()
                ->where('company_id', $company->id)
                ->count();

            $openComplaintsCount = Complaint::query()
                ->where('company_id', $company->id)
                ->whereIn('status', ['open', 'in_review'])
                ->count();
        }

        if ($canSuggestions) {
            $suggestionsCount = TeamSuggestion::query()
                ->where('company_id', $company->id)
                ->count();

            $newSuggestionsCount = TeamSuggestion::query()
                ->where('company_id', $company->id)
                ->where('status', 'new')
                ->count();
        }

        $publicToken = $company->complaints_public_token;

        return Inertia::render('Client/TeamVoice/Index', [
            'surveysCount' => $surveysCount,
            'purposeMapCount' => $purposeMapCount,
            'complaintsCount' => $complaintsCount,
            'openComplaintsCount' => $openComplaintsCount,
            'suggestionsCount' => $suggestionsCount,
            'newSuggestionsCount' => $newSuggestionsCount,
            'canSurveys' => $canSurveys,
            'canComplaints' => $canComplaints,
            'canSuggestions' => $canSuggestions,
            'complaintsPublicUrl' => $publicToken
                ? route('denuncia.create', $publicToken)
                : null,
            'suggestionsPublicUrl' => $publicToken
                ? route('sugestao.create', $publicToken)
                : null,
        ]);
    }
}
