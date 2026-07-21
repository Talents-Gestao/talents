<?php

namespace App\Http\Controllers\Client;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Survey;
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
        $complaintsCount = 0;
        $openComplaintsCount = 0;

        $canSurveys = $user->canAccess(PermissionModule::Pesquisas, PermissionAction::View);
        $canComplaints = $user->canAccess(PermissionModule::Denuncias, PermissionAction::View);

        if (! $canSurveys && ! $canComplaints) {
            abort(403, 'Sem permissão para esta área.');
        }

        if ($canSurveys) {
            $surveysCount = Survey::query()
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

        return Inertia::render('Client/TeamVoice/Index', [
            'surveysCount' => $surveysCount,
            'complaintsCount' => $complaintsCount,
            'openComplaintsCount' => $openComplaintsCount,
            'canSurveys' => $canSurveys,
            'canComplaints' => $canComplaints,
            'complaintsPublicUrl' => $company->complaints_public_token
                ? route('denuncia.create', $company->complaints_public_token)
                : null,
        ]);
    }
}
