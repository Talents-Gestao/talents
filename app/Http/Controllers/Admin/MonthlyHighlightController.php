<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MonthlyHighlightCategory;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyMonthlyHighlight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyHighlightController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $request->integer('company_id') ?: null;
        if ($companyId !== null && $companyId <= 0) {
            $companyId = null;
        }

        $year = $request->integer('year') ?: null;
        if ($year !== null && $year <= 0) {
            $year = null;
        }

        $month = $request->integer('month') ?: null;
        if ($month !== null && ($month < 1 || $month > 12)) {
            $month = null;
        }

        $category = trim($request->string('category')->toString());
        $q = trim($request->string('q')->toString());

        $highlights = CompanyMonthlyHighlight::query()
            ->with(['company:id,name'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($year, fn ($query) => $query->where('year', $year))
            ->when($month, fn ($query) => $query->where('month', $month))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($q !== '', function ($query) use ($q) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($inner) use ($q, $operator) {
                    $inner->where('person_name', $operator, '%'.$q.'%')
                        ->orWhere('description', $operator, '%'.$q.'%')
                        ->orWhereHas('company', fn ($c) => $c->where('name', $operator, '%'.$q.'%'));
                });
            })
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('person_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CompanyMonthlyHighlight $row) => $this->listPayload($row));

        return Inertia::render('Admin/DestaquesMes/Index', [
            'highlights' => $highlights,
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => MonthlyHighlightCategory::options(),
            'filters' => [
                'company_id' => $companyId,
                'year' => $year,
                'month' => $month,
                'category' => $category !== '' ? $category : null,
                'q' => $q !== '' ? $q : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = $request->integer('company_id') ?: null;
        if ($companyId !== null && $companyId <= 0) {
            $companyId = null;
        }

        $now = now();

        return Inertia::render('Admin/DestaquesMes/Form', [
            'mode' => 'create',
            'highlight' => null,
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => MonthlyHighlightCategory::options(),
            'selected_company_id' => $companyId,
            'default_year' => (int) $now->year,
            'default_month' => (int) $now->month,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, requirePhoto: true);
        $photo = $request->file('photo');
        unset($data['photo'], $data['remove_photo']);

        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;
        $data['photo_disk'] = 'public';

        $highlight = CompanyMonthlyHighlight::query()->create($data);

        if ($photo instanceof UploadedFile) {
            $path = $photo->store('monthly-highlights/'.$highlight->id, 'public');
            $highlight->update(['photo_path' => $path]);
        }

        return redirect()
            ->route('admin.destaques-mes.edit', $highlight)
            ->with('success', 'Destaque do mês criado.');
    }

    public function edit(CompanyMonthlyHighlight $destaque_mes): Response
    {
        $destaque_mes->load(['company:id,name']);

        return Inertia::render('Admin/DestaquesMes/Form', [
            'mode' => 'edit',
            'highlight' => $this->formPayload($destaque_mes),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => MonthlyHighlightCategory::options(),
            'selected_company_id' => $destaque_mes->company_id,
            'default_year' => $destaque_mes->year,
            'default_month' => $destaque_mes->month,
        ]);
    }

    public function update(Request $request, CompanyMonthlyHighlight $destaque_mes): RedirectResponse
    {
        $data = $this->validated($request, requirePhoto: false);
        $photo = $request->file('photo');
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        unset($data['photo'], $data['remove_photo']);

        $data['updated_by'] = $request->user()?->id;

        if ($photo instanceof UploadedFile) {
            $destaque_mes->deletePhoto();
            $data['photo_path'] = $photo->store('monthly-highlights/'.$destaque_mes->id, 'public');
            $data['photo_disk'] = 'public';
        } elseif ($removePhoto) {
            $destaque_mes->deletePhoto();
            $data['photo_path'] = null;
        }

        $destaque_mes->update($data);

        return redirect()
            ->route('admin.destaques-mes.edit', $destaque_mes)
            ->with('success', 'Destaque do mês atualizado.');
    }

    public function destroy(CompanyMonthlyHighlight $destaque_mes): RedirectResponse
    {
        $companyId = $destaque_mes->company_id;
        $destaque_mes->deletePhoto();
        $destaque_mes->delete();

        return redirect()
            ->route('admin.destaques-mes.index', ['company_id' => $companyId])
            ->with('success', 'Destaque do mês removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(CompanyMonthlyHighlight $row): array
    {
        return [
            'id' => $row->id,
            'person_name' => $row->person_name,
            'category' => $row->category->value,
            'category_label' => $row->category->label(),
            'year' => $row->year,
            'month' => $row->month,
            'period_label' => $row->periodLabel(),
            'photo_url' => $row->photoUrl(),
            'is_published' => $row->is_published,
            'company' => $row->company ? ['id' => $row->company->id, 'name' => $row->company->name] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(CompanyMonthlyHighlight $row): array
    {
        return [
            'id' => $row->id,
            'company_id' => $row->company_id,
            'person_name' => $row->person_name,
            'category' => $row->category->value,
            'year' => $row->year,
            'month' => $row->month,
            'description' => $row->description,
            'is_published' => $row->is_published,
            'photo_url' => $row->photoUrl(),
            'company' => $row->company ? ['id' => $row->company->id, 'name' => $row->company->name] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requirePhoto): array
    {
        $photoRules = $requirePhoto
            ? ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']
            : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'person_name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(MonthlyHighlightCategory::class)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_published' => ['boolean'],
            'photo' => $photoRules,
            'remove_photo' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = (bool) ($data['is_published'] ?? false);

        return $data;
    }
}
