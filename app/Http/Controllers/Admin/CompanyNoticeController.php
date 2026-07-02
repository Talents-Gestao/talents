<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Notices\PublishCompanyNotice;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyNoticeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CompanyNotice::query()
            ->with(['company:id,name', 'creator:id,name'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->input('company_id'));
        }

        return Inertia::render('Admin/Notices/Index', [
            'notices' => $query->paginate(20)->withQueryString(),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['company_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Notices/Create', [
            'companies' => Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->filter(fn (Company $company) => $company->hasStrategicCalendarEnabled())
                ->values(),
        ]);
    }

    public function store(Request $request, PublishCompanyNotice $publishCompanyNotice): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        abort_unless($company->hasStrategicCalendarEnabled(), 422);

        $publishCompanyNotice->handle(
            companyId: (int) $data['company_id'],
            title: $data['title'],
            body: $data['body'],
            actor: $request->user(),
        );

        return redirect()
            ->route('admin.notices.index')
            ->with('success', 'Aviso publicado para a empresa.');
    }
}
