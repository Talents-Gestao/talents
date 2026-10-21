<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Services\ExecutiveBriefService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ExecutiveBriefController extends Controller
{
    public function download(Request $request, Survey $survey, ExecutiveBriefService $service): SymfonyResponse
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->company_id, 404);

        $version = $service->currentVersion($survey);
        abort_if($version === null, 404);

        try {
            return $service->download($version, $request->user(), $request->ip());
        } catch (PsychosocialDocumentException $exception) {
            return response($exception->getMessage(), SymfonyResponse::HTTP_CONFLICT, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }
}
