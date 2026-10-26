<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Publica os resultados de uma campanha para a empresa (T02). Só publica: não há "despublicar" aqui,
 * o retorno de versão e o histórico entram com o fluxo de revisão da Etapa 7.
 */
class SurveyPublicationAdminController extends Controller
{
    public function publish(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);

        if ($survey->published_at !== null) {
            return back()->with('success', 'Esta campanha já estava publicada.');
        }

        $survey->forceFill([
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ])->save();

        return back()->with('success', 'Resultados publicados para a empresa.');
    }
}
