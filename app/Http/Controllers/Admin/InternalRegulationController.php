<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyInternalRegulation;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InternalRegulationController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $request->integer('company_id') ?: null;
        if ($companyId !== null && $companyId <= 0) {
            $companyId = null;
        }

        $q = trim($request->string('q')->toString());

        $regulations = CompanyInternalRegulation::query()
            ->with(['company:id,name', 'updatedBy:id,name'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($q !== '', function ($query) use ($q) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($inner) use ($q, $operator) {
                    $inner->where('title', $operator, '%'.$q.'%')
                        ->orWhereHas('company', fn ($c) => $c->where('name', $operator, '%'.$q.'%'));
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/InternalRegulations/Index', [
            'regulations' => $regulations,
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'company_id' => $companyId,
                'q' => $q !== '' ? $q : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = $request->integer('company_id') ?: null;

        return Inertia::render('Admin/InternalRegulations/Form', [
            'mode' => 'create',
            'regulation' => null,
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selected_company_id' => $companyId && $companyId > 0 ? $companyId : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['body_html'] = HtmlSanitizer::sanitizeRichText($data['body_html'] ?? null);
        $data['updated_by'] = $request->user()?->id;

        $regulation = CompanyInternalRegulation::query()->create($data);

        return redirect()
            ->route('admin.regulamento-interno.edit', $regulation)
            ->with('success', 'Regulamento interno criado.');
    }

    public function edit(CompanyInternalRegulation $regulamento_interno): Response
    {
        $regulamento_interno->load(['company:id,name']);

        return Inertia::render('Admin/InternalRegulations/Form', [
            'mode' => 'edit',
            'regulation' => [
                'id' => $regulamento_interno->id,
                'company_id' => $regulamento_interno->company_id,
                'title' => $regulamento_interno->title,
                'body_html' => $regulamento_interno->body_html,
                'is_published' => $regulamento_interno->is_published,
                'company' => $regulamento_interno->company,
            ],
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selected_company_id' => $regulamento_interno->company_id,
        ]);
    }

    public function update(Request $request, CompanyInternalRegulation $regulamento_interno): RedirectResponse
    {
        $data = $this->validated($request, $regulamento_interno->id);
        $data['body_html'] = HtmlSanitizer::sanitizeRichText($data['body_html'] ?? null);
        $data['updated_by'] = $request->user()?->id;

        $regulamento_interno->update($data);

        return redirect()
            ->route('admin.regulamento-interno.edit', $regulamento_interno)
            ->with('success', 'Regulamento interno atualizado.');
    }

    public function destroy(CompanyInternalRegulation $regulamento_interno): RedirectResponse
    {
        $companyId = $regulamento_interno->company_id;
        $regulamento_interno->delete();

        return redirect()
            ->route('admin.regulamento-interno.index', ['company_id' => $companyId])
            ->with('success', 'Regulamento interno removido.');
    }

    /**
     * @return array{company_id: int, title: string, body_html: ?string, is_published: bool}
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        /** @var array{company_id: int, title: string, body_html: ?string, is_published: bool} $data */
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string', 'max:500000'],
            'is_published' => ['boolean'],
        ]);

        $data['is_published'] = (bool) ($data['is_published'] ?? false);

        return $data;
    }
}
