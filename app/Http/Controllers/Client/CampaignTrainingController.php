<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Exceptions\Nr1CampaignException;
use App\Http\Controllers\Controller;
use App\Models\Nr1Training;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Services\Nr1\Nr1OpportunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Solicitar proposta» de um treinamento da campanha NR-1. Só um POST explícito cria a oportunidade; ver a tela não cria nada.
 */
class CampaignTrainingController extends Controller
{
    public function __construct(private readonly Nr1OpportunityService $opportunities) {}

    public function requestProposal(Request $request, Survey $survey, string $modality, string $audience): RedirectResponse
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->company_id, 404);
        abort_unless(in_array($modality, Nr1Training::MODALITIES, true) && in_array($audience, SurveyAudienceLink::AUDIENCES, true), 404);

        try {
            $this->opportunities->requestTraining($survey, $request->user(), $modality, $audience);
        } catch (Nr1CampaignException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Proposta solicitada. A Talents entrará em contato.');
    }
}
