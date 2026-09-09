<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialProcess;
use App\Services\Commercial\DocxToHtmlService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommercialProcessController extends Controller
{
    public function __construct(
        private readonly DocxToHtmlService $docxToHtml,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim($request->string('q')->toString());

        $processes = CommercialProcess::query()
            ->with(['updatedBy:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($inner) use ($q, $operator) {
                    $inner->where('title', $operator, '%'.$q.'%')
                        ->orWhere('summary', $operator, '%'.$q.'%');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString()
            ->through(static fn (CommercialProcess $row) => [
                'id' => $row->id,
                'title' => $row->title,
                'summary' => $row->summary,
                'is_published' => $row->is_published,
                'has_file' => $row->hasFile(),
                'file_name' => $row->file_name,
                'updated_at' => $row->updated_at?->toIso8601String(),
                'updated_by' => $row->updatedBy?->only(['id', 'name']),
            ]);

        return Inertia::render('Admin/Commercial/Processes/Index', [
            'processes' => $processes,
            'filters' => [
                'q' => $q !== '' ? $q : null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Commercial/Processes/Form', [
            'mode' => 'create',
            'process' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $bodyHtml = $this->resolveBodyHtml($request, $data['body_html'] ?? null);

        $process = CommercialProcess::query()->create([
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'body_html' => $bodyHtml,
            'sort_order' => $data['sort_order'],
            'is_published' => $data['is_published'],
            'updated_by' => $request->user()?->id,
        ]);

        $this->syncFile($request, $process, replaceBodyFromFile: $bodyHtml === null || $bodyHtml === '');

        return redirect()
            ->route('admin.comercial.processos.edit', $process)
            ->with('success', 'Processo comercial criado.');
    }

    public function show(CommercialProcess $processo): Response
    {
        $processo->load(['updatedBy:id,name']);

        return Inertia::render('Admin/Commercial/Processes/Show', [
            'process' => $this->toInertiaProcess($processo),
        ]);
    }

    public function edit(CommercialProcess $processo): Response
    {
        $processo->load(['updatedBy:id,name']);

        return Inertia::render('Admin/Commercial/Processes/Form', [
            'mode' => 'edit',
            'process' => $this->toInertiaProcess($processo),
        ]);
    }

    public function update(Request $request, CommercialProcess $processo): RedirectResponse
    {
        $data = $this->validated($request);
        $replaceFromFile = $request->boolean('replace_body_from_file');
        $bodyHtml = $this->resolveBodyHtml($request, $data['body_html'] ?? null, $replaceFromFile);

        $processo->update([
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'body_html' => $bodyHtml,
            'sort_order' => $data['sort_order'],
            'is_published' => $data['is_published'],
            'updated_by' => $request->user()?->id,
        ]);

        $this->syncFile($request, $processo, replaceBodyFromFile: $replaceFromFile);

        return redirect()
            ->route('admin.comercial.processos.edit', $processo)
            ->with('success', 'Processo comercial atualizado.');
    }

    public function destroy(CommercialProcess $processo): RedirectResponse
    {
        $processo->deleteFile();
        $processo->delete();

        return redirect()
            ->route('admin.comercial.processos.index')
            ->with('success', 'Processo comercial removido.');
    }

    public function download(CommercialProcess $processo): StreamedResponse
    {
        abort_unless(
            $processo->hasFile()
            && Storage::disk('local')->exists($processo->file_path),
            404,
        );

        return Storage::disk('local')->download(
            $processo->file_path,
            $processo->file_name ?? 'processo',
        );
    }

    /**
     * @return array{
     *     title: string,
     *     summary: ?string,
     *     body_html: ?string,
     *     sort_order: int,
     *     is_published: bool
     * }
     */
    private function validated(Request $request): array
    {
        /** @var array{
         *     title: string,
         *     summary?: ?string,
         *     body_html?: ?string,
         *     sort_order?: int|string|null,
         *     is_published?: bool|string|null
         * } $data
         */
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body_html' => ['nullable', 'string', 'max:2000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'remove_file' => ['nullable', 'boolean'],
            'replace_body_from_file' => ['nullable', 'boolean'],
        ], [
            'file.mimes' => 'O anexo deve ser PDF, DOC ou DOCX.',
            'file.max' => 'O anexo não pode exceder 20 MB.',
        ]);

        return [
            'title' => $data['title'],
            'summary' => isset($data['summary']) ? trim((string) $data['summary']) ?: null : null,
            'body_html' => $data['body_html'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_published' => (bool) ($data['is_published'] ?? true),
        ];
    }

    private function resolveBodyHtml(Request $request, ?string $bodyHtml, bool $forceFromFile = false): ?string
    {
        $uploaded = $request->file('file');
        $shouldImport = $forceFromFile
            || ($uploaded !== null && ($bodyHtml === null || trim($bodyHtml) === ''));

        if ($shouldImport && $uploaded instanceof UploadedFile) {
            $imported = $this->htmlFromUpload($uploaded);
            if ($imported !== null) {
                return HtmlSanitizer::sanitizeRichText($imported);
            }
        }

        return HtmlSanitizer::sanitizeRichText($bodyHtml);
    }

    private function syncFile(Request $request, CommercialProcess $process, bool $replaceBodyFromFile = false): void
    {
        $uploaded = $request->file('file');
        $remove = $request->boolean('remove_file');

        if ($uploaded === null && ! $remove) {
            return;
        }

        $existingPath = $process->file_path;

        if ($uploaded !== null) {
            $path = $uploaded->store('commercial-processes/'.$process->id, 'local');
            $process->forceFill([
                'file_path' => $path,
                'file_name' => $uploaded->getClientOriginalName(),
            ])->save();

            if ($replaceBodyFromFile || blank($process->body_html)) {
                $imported = $this->htmlFromStoredPath($path, $uploaded->getClientOriginalName());
                if ($imported !== null) {
                    $process->forceFill([
                        'body_html' => HtmlSanitizer::sanitizeRichText($imported),
                    ])->save();
                }
            }

            if ($existingPath && $existingPath !== $path && Storage::disk('local')->exists($existingPath)) {
                Storage::disk('local')->delete($existingPath);
            }

            return;
        }

        if ($remove) {
            $process->deleteFile();
        }
    }

    private function htmlFromUpload(UploadedFile $file): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, ['doc', 'docx'], true)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'procdocx');
        if ($tmp === false) {
            return null;
        }

        try {
            $path = $tmp.'.'.$ext;
            if (! @copy($file->getRealPath(), $path)) {
                return null;
            }

            return DocxToHtmlService::extractFromAbsolutePath($path);
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($tmp);
            if (isset($path)) {
                @unlink($path);
            }
        }
    }

    private function htmlFromStoredPath(string $relativePath, ?string $originalName = null): ?string
    {
        $ext = strtolower(pathinfo($originalName ?: $relativePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['doc', 'docx'], true)) {
            return null;
        }

        try {
            return $this->docxToHtml->extract($relativePath);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toInertiaProcess(CommercialProcess $process): array
    {
        return [
            'id' => $process->id,
            'title' => $process->title,
            'summary' => $process->summary,
            'body_html' => $process->body_html,
            'sort_order' => $process->sort_order,
            'is_published' => $process->is_published,
            'file_name' => $process->file_name,
            'has_file' => $process->hasFile(),
            'updated_at' => $process->updated_at?->toIso8601String(),
            'updated_by' => $process->updatedBy?->only(['id', 'name']),
            'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $process->body_html)) ?: ''), 160),
        ];
    }
}
