<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\MethodologySurvey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MethodologyController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->company;
        abort_unless($company && $company->hasMethodologyEnabled(), 403);

        $recentSurveys = MethodologySurvey::query()
            ->where('company_id', $company->id)
            ->with(['template'])
            ->withCount('completedResponses')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return Inertia::render('Client/Methodology/Index', [
            'recentSurveys' => $recentSurveys,
        ]);
    }
}
