<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Surveys\ArchiveAndDeleteSurvey;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySurveyController extends Controller
{
    public function destroy(
        Request $request,
        Company $company,
        Survey $survey,
        ArchiveAndDeleteSurvey $archiveAndDelete,
    ): RedirectResponse {
        abort_unless($survey->company_id === $company->id, 404);

        $archiveAndDelete->handle($survey, $request->user());

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', 'Pesquisa excluída. Os dados foram arquivados.');
    }
}
