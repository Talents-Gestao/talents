<?php

namespace App\Http\Middleware;

use App\Models\ActionPlanItem;
use App\Models\Survey;
use App\Support\SurveyPublicationGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas da empresa que expõem resultado, plano ou documento de uma campanha: antes de "Publicado" a campanha
 * que exige publicação responde 404, como se não existisse (T02). Resolve a campanha pelo parâmetro
 * {survey} ou, nas rotas de item do plano, pelo plano do item.
 */
class EnsureSurveyPublished
{
    public function handle(Request $request, Closure $next): Response
    {
        $survey = $this->resolveSurvey($request);

        if ($survey !== null && ! SurveyPublicationGate::allows($survey)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }

    private function resolveSurvey(Request $request): ?Survey
    {
        $param = $request->route('survey');

        if ($param instanceof Survey) {
            return $param;
        }

        if ($param !== null) {
            return Survey::query()->find($param);
        }

        $item = $request->route('item');
        if ($item !== null && ! $item instanceof ActionPlanItem) {
            $item = ActionPlanItem::query()->find($item);
        }

        return $item?->actionPlan?->survey;
    }
}
