<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Http\Controllers\Concerns\ResolvesFeedbackRoutes;
use App\Models\CompanyEmployee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedbackEmployeeController extends FeedbackCompanyController
{
    use ResolvesFeedbackRoutes;

    public function index(Request $request): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function create(Request $request): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function show(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function edit(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function update(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    public function destroy(Request $request, CompanyEmployee $employee): RedirectResponse
    {
        return $this->deprecatedRedirect($request);
    }

    private function deprecatedRedirect(Request $request): RedirectResponse
    {
        $this->company($request);

        return $this->feedbackRedirect(
            'index',
            message: 'Os colaboradores passam a vir do RHID (Control iD). Use o módulo RHID ou os selects nos fluxos de Feedback.',
        );
    }
}
