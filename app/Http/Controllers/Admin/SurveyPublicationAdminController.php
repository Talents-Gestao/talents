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

        // Campanha V11: "Publicado" nasce da publicação dos documentos (fluxo de revisão), nunca de um botão avulso.
        if ($survey->usesReviewFlow()) {
            return back()->with('error', 'Nesta campanha a publicação é feita em Documentos, depois da revisão.');
        }

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
