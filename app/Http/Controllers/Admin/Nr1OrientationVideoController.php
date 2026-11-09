<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\Nr1CampaignException;
use App\Http\Controllers\Controller;
use App\Models\Nr1OrientationVideo;
use App\Services\Nr1\Nr1OrientationVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Biblioteca global permanente de vídeos de orientação NR-1 (abertura e encerramento).
 */
class Nr1OrientationVideoController extends Controller
{
    public function __construct(private readonly Nr1OrientationVideoService $videos) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Admin/Nr1Videos/Index', [
            'videos' => $this->videos->library(),
            'roles' => collect(Nr1OrientationVideo::ROLES)->map(fn (string $role) => [
                'value' => $role,
                'label' => Nr1OrientationVideo::ROLE_LABELS[$role],
            ])->values()->all(),
            'maxUploadMb' => (int) config('nr1.orientation_videos.max_upload_mb', 200),
            'urls' => [
                'store' => route('admin.nr1-videos.store'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxKb = $this->videos->maxUploadKb();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:'.implode(',', Nr1OrientationVideo::ROLES)],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:mp4,webm'],
        ], [
            'file.max' => 'O vídeo pode ter no máximo '.((int) config('nr1.orientation_videos.max_upload_mb', 200)).' MB.',
        ]);

        try {
            $this->videos->store($request->user(), $data['title'], $data['role'], $request->file('file'));
        } catch (Nr1CampaignException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Vídeo adicionado à biblioteca.');
    }

    public function update(Request $request, Nr1OrientationVideo $nr1Video): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $this->videos->updateMeta(
                $request->user(),
                $nr1Video,
                $data['title'],
                array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            );
        } catch (Nr1CampaignException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Vídeo atualizado.');
    }

    public function replace(Request $request, Nr1OrientationVideo $nr1Video): RedirectResponse
    {
        $maxKb = $this->videos->maxUploadKb();
        $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:mp4,webm'],
        ], [
            'file.max' => 'O vídeo pode ter no máximo '.((int) config('nr1.orientation_videos.max_upload_mb', 200)).' MB.',
        ]);

        try {
            $this->videos->replaceFile($request->user(), $nr1Video, $request->file('file'));
        } catch (Nr1CampaignException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Arquivo do vídeo substituído.');
    }

    public function destroy(Request $request, Nr1OrientationVideo $nr1Video): RedirectResponse
    {
        try {
            $this->videos->delete($request->user(), $nr1Video);
        } catch (Nr1CampaignException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Vídeo removido da biblioteca.');
    }

    public function stream(Nr1OrientationVideo $nr1Video): StreamedResponse|Response
    {
        return $nr1Video->toHttpResponse();
    }
}
