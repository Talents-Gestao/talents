<?php

declare(strict_types=1);

namespace App\Http\Controllers\Nr1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Survey;
use App\Models\User;
use App\Support\Nr1Shell\Nr1Shell;
use App\Support\SurveyPublicationGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * URLs antigas do Laudo e das telas do cliente (favoritos, e-mails já enviados): só redirecionam para o painel NR-1 e
 * nunca entregam dado. Quem não pode abrir a seção de destino recebe 403; campanha de outra empresa ou não publicada, 404.
 */
class LegacyUrlRedirectController extends Controller
{
    public function adminLaudo(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
        $user = $request->user();
        abort_unless(Nr1Shell::profileFor($user) === Nr1Shell::PROFILE_TALENTS, 403);

        return redirect(Nr1Shell::parecerUrl($survey));
    }

    public function clientResults(Request $request, Survey $survey): RedirectResponse
    {
        return $this->toClientSection($request->user(), $survey, 'resultados');
    }

    public function clientPlan(Request $request, Survey $survey): RedirectResponse
    {
        $user = $request->user();
        $section = ! $survey->usesReviewFlow() && Nr1Shell::allows($user, 'parecer') ? 'parecer' : 'plano-de-acao';

        return $this->toClientSection($user, $survey, $section);
    }

    private function toClientSection(User $user, Survey $survey, string $section): RedirectResponse
    {
        abort_unless(Nr1Shell::profileFor($user) === Nr1Shell::PROFILE_EMPRESA, 403);
        abort_unless((int) $survey->company_id === (int) $user->contextCompanyId() && SurveyPublicationGate::allows($survey), 404);
        abort_unless(Nr1Shell::allows($user, $section), 403);

        return redirect()->route('nr1.'.$section, ['campanha' => $survey->id]);
    }
}
