<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SolidesSetting;
use App\Services\Solides\SolidesClient;
use App\Services\Solides\SolidesInferredVacancyCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SolidesCurriculumController extends Controller
{
    public function index(Request $request): Response
    {
        $request->merge([
            'data_inicial' => filled($request->query('data_inicial')) ? $request->query('data_inicial') : null,
            'data_final' => filled($request->query('data_final')) ? $request->query('data_final') : null,
            'origem_contains' => filled($request->query('origem_contains')) ? $request->query('origem_contains') : null,
            'grupo_contains' => filled($request->query('grupo_contains')) ? $request->query('grupo_contains') : null,
            'view' => filled($request->query('view')) ? $request->query('view') : 'list',
        ]);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'data_inicial' => ['nullable', 'string', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'data_final' => ['nullable', 'string', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'view' => ['sometimes', 'in:list,grouped'],
            'origem_contains' => ['nullable', 'string', 'max:120'],
            'grupo_contains' => ['nullable', 'string', 'max:120'],
        ]);

        $viewMode = $validated['view'] ?? 'list';
        if (! in_array($viewMode, ['list', 'grouped'], true)) {
            $viewMode = 'list';
        }

        $filterProps = [
            'data_inicial' => $validated['data_inicial'] ?? '',
            'data_final' => $validated['data_final'] ?? '',
            'origem_contains' => $validated['origem_contains'] ?? '',
            'grupo_contains' => $validated['grupo_contains'] ?? '',
        ];

        $setting = SolidesSetting::current();
        $configured = $setting !== null && $setting->safeApiToken() !== null;

        if (! $configured) {
            return Inertia::render('Admin/Solides/Resumes/Index', [
                'configured' => false,
                'view_mode' => $viewMode,
                'filters' => $filterProps,
                'page' => 1,
                'curricula' => [],
                'pagination' => [
                    'current_page' => 1,
                    'has_prev' => false,
                    'has_next' => false,
                    'prev_url' => null,
                    'next_url' => null,
                ],
                'grouped_summary' => null,
                'grouped_meta' => null,
                'grouped' => [],
                'error' => null,
            ]);
        }

        if ($viewMode === 'grouped') {
            try {
                $catalog = new SolidesInferredVacancyCatalog(
                    (int) config('solides.grouped_max_curriculo_pages', 50),
                    (int) config('solides.grouped_http_timeout', 90),
                );
                $result = $catalog->loadGrouped(
                    $setting,
                    $validated['data_inicial'] ?? null,
                    $validated['data_final'] ?? null,
                    $validated['origem_contains'] ?? null,
                    $validated['grupo_contains'] ?? null,
                );
            } catch (\Throwable $e) {
                report($e);

                return Inertia::render('Admin/Solides/Resumes/Index', [
                    'configured' => true,
                    'view_mode' => 'grouped',
                    'filters' => $filterProps,
                    'page' => 1,
                    'curricula' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'has_prev' => false,
                        'has_next' => false,
                        'prev_url' => null,
                        'next_url' => null,
                    ],
                    'grouped_summary' => null,
                    'grouped_meta' => null,
                    'grouped' => [],
                    'error' => $e->getMessage(),
                ]);
            }

            return Inertia::render('Admin/Solides/Resumes/Index', [
                'configured' => true,
                'view_mode' => 'grouped',
                'filters' => $filterProps,
                'page' => 1,
                'curricula' => [],
                'pagination' => [
                    'current_page' => 1,
                    'has_prev' => false,
                    'has_next' => false,
                    'prev_url' => null,
                    'next_url' => null,
                ],
                'grouped_summary' => $result['summary'],
                'grouped_meta' => [
                    'curriculos_pages_fetched' => $result['curriculos_pages_fetched'],
                    'passaportes_count' => $result['passaportes_count'],
                ],
                'grouped' => $result['groups'],
                'error' => null,
            ]);
        }

        $page = max(1, (int) ($validated['page'] ?? 1));
        $query = array_filter([
            'page' => $page,
            'data_inicial' => $validated['data_inicial'] ?? null,
            'data_final' => $validated['data_final'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $client = new SolidesClient($setting);
            $items = $client->getCurriculos($query);
        } catch (\Throwable $e) {
            report($e);

            return Inertia::render('Admin/Solides/Resumes/Index', [
                'configured' => true,
                'view_mode' => 'list',
                'filters' => $filterProps,
                'page' => $page,
                'curricula' => [],
                'pagination' => [
                    'current_page' => $page,
                    'has_prev' => $page > 1,
                    'has_next' => false,
                    'prev_url' => $page > 1 ? $this->paginationUrl($request, $page - 1, $validated) : null,
                    'next_url' => null,
                ],
                'grouped_summary' => null,
                'grouped_meta' => null,
                'grouped' => [],
                'error' => $e->getMessage(),
            ]);
        }

        $curricula = array_map(fn (array $row) => self::scrubRow($row), $items);
        $hasNext = count($items) > 0;

        return Inertia::render('Admin/Solides/Resumes/Index', [
            'configured' => true,
            'view_mode' => 'list',
            'filters' => $filterProps,
            'page' => $page,
            'curricula' => $curricula,
            'pagination' => [
                'current_page' => $page,
                'has_prev' => $page > 1,
                'has_next' => $hasNext,
                'prev_url' => $page > 1 ? $this->paginationUrl($request, $page - 1, $validated) : null,
                'next_url' => $hasNext ? $this->paginationUrl($request, $page + 1, $validated) : null,
            ],
            'grouped_summary' => null,
            'grouped_meta' => null,
            'grouped' => [],
            'error' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function paginationUrl(Request $request, int $page, array $validated): string
    {
        $params = array_filter([
            'view' => 'list',
            'page' => $page,
            'data_inicial' => $validated['data_inicial'] ?? null,
            'data_final' => $validated['data_final'] ?? null,
            'origem_contains' => $validated['origem_contains'] ?? null,
            'grupo_contains' => $validated['grupo_contains'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return route('admin.solides.curriculos.index', $params, false);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function scrubRow(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'fullName' => self::asUtf8String($row['fullName'] ?? null, ''),
            'mainEmail' => self::asUtf8String($row['mainEmail'] ?? null, ''),
            'idNumber' => self::asUtf8String($row['idNumber'] ?? null),
            'mobile' => self::asUtf8String($row['mobile'] ?? null),
            'phone' => self::asUtf8String($row['phone'] ?? null),
            'birthDate' => self::asUtf8String($row['birthDate'] ?? null),
            'gender' => self::asUtf8String($row['gender'] ?? null),
            'seniority' => self::asUtf8String($row['seniority'] ?? null),
            'origin' => self::asUtf8String($row['origin'] ?? null),
            'city' => self::asUtf8String(data_get($row, 'adress.city.name')),
            'state' => self::asUtf8String(data_get($row, 'adress.city.state.initials')),
            'experiences_count' => is_array($row['professionalExperiences'] ?? null) ? count($row['professionalExperiences']) : 0,
            'education_count' => is_array($row['academicEducations'] ?? null) ? count($row['academicEducations']) : 0,
        ];
    }

    private static function asUtf8String(mixed $value, ?string $whenEmpty = null): ?string
    {
        if ($value === null) {
            return $whenEmpty;
        }
        if (! is_string($value)) {
            $value = (string) $value;
        }
        if ($value === '') {
            return $whenEmpty;
        }

        $clean = mb_scrub($value, 'UTF-8');

        return $clean === '' ? $whenEmpty : $clean;
    }
}
