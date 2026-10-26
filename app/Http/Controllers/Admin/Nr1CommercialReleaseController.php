<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Libera (ou retira) os resultados agregados de uma campanha para o Comercial limitado.
 */
class Nr1CommercialReleaseController extends Controller
{
    public function release(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);

        $survey->forceFill([
            'commercial_released_at' => now(),
            'commercial_released_by' => $request->user()->id,
        ])->save();

        return back()->with('success', 'Resultados agregados liberados ao Comercial.');
    }

    public function withdraw(Company $company, Survey $survey): RedirectResponse
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);

        $survey->forceFill(['commercial_released_at' => null, 'commercial_released_by' => null])->save();

        return back()->with('success', 'Liberação ao Comercial retirada.');
    }
}
