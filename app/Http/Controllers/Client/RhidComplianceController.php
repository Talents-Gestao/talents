<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\RhidAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RhidComplianceController extends Controller
{
    public function index(Request $request): Response
    {
        $company = $request->user()->company()->firstOrFail();

        $recentAudits = RhidAuditLog::query()
            ->where('company_id', $company->id)
            ->with('user:id,name')
            ->latest()
            ->limit(25)
            ->get(['id', 'user_id', 'action', 'endpoint', 'http_status', 'created_at']);

        return Inertia::render('Client/Rhid/Compliance', [
            'configured' => $company->rhidConfigured(),
            'recentAudits' => $recentAudits,
        ]);
    }
}
