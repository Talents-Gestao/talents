<?php

declare(strict_types=1);

namespace App\Services\Nr1;

use App\Exceptions\Nr1CampaignException;
use App\Models\Nr1OrientationVideo;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Biblioteca global de vídeos de orientação NR-1 e vínculo por campanha.
 */
class Nr1OrientationVideoService
{
    public const DISK = 'local';

    public function maxUploadKb(): int
    {
        return max(1, (int) config('nr1.orientation_videos.max_upload_mb', 200)) * 1024;
    }

    /**
     * @return list<array{id: int, title: string, role: string, role_label: string, is_active: bool, original_name: string, size_bytes: int, mime: ?string}>
     */
    public function library(?string $role = null, bool $onlyActive = false): array
    {
        $query = Nr1OrientationVideo::query()->orderBy('role')->orderBy('title');
        if ($role !== null) {
            $query->where('role', $role);
        }
        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->get()->map(fn (Nr1OrientationVideo $video): array => $this->toListItem($video))->all();
    }

    /**
     * @return array{id: int, title: string, role: string, role_label: string, is_active: bool, original_name: string, size_bytes: int, mime: ?string}
     */
    public function toListItem(Nr1OrientationVideo $video): array
    {
        return [
            'id' => $video->id,
            'title' => $video->title,
            'role' => $video->role,
            'role_label' => $video->roleLabel(),
            'is_active' => (bool) $video->is_active,
            'original_name' => $video->original_name,
            'size_bytes' => (int) $video->size_bytes,
            'mime' => $video->mime,
        ];
    }

    public function store(User $actor, string $title, string $role, UploadedFile $file): Nr1OrientationVideo
    {
        $this->assertTalents($actor);
        $this->assertRole($role);
        $this->assertVideoFile($file);

        return DB::transaction(function () use ($title, $role, $file): Nr1OrientationVideo {
            $video = Nr1OrientationVideo::query()->create([
                'title' => trim($title),
                'role' => $role,
                'disk' => self::DISK,
                'path' => 'pending',
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?: 'video/mp4',
                'size_bytes' => (int) $file->getSize(),
                'is_active' => true,
            ]);

            $bytes = (string) file_get_contents($file->getRealPath());
            $ext = strtolower($file->getClientOriginalExtension() ?: 'mp4');
            $path = 'nr1/orientation-videos/'.$video->id.'/'.hash('sha256', $bytes).'.'.$ext;
            Storage::disk(self::DISK)->put($path, $bytes);

            $video->forceFill(['path' => $path])->save();

            return $video->fresh();
        });
    }

    public function updateMeta(User $actor, Nr1OrientationVideo $video, string $title, ?bool $isActive = null): Nr1OrientationVideo
    {
        $this->assertTalents($actor);
        $video->title = trim($title);
        if ($isActive !== null) {
            $video->is_active = $isActive;
        }
        $video->save();

        return $video;
    }

    public function replaceFile(User $actor, Nr1OrientationVideo $video, UploadedFile $file): Nr1OrientationVideo
    {
        $this->assertTalents($actor);
        $this->assertVideoFile($file);

        return DB::transaction(function () use ($video, $file): Nr1OrientationVideo {
            $oldPath = $video->path;
            $bytes = (string) file_get_contents($file->getRealPath());
            $ext = strtolower($file->getClientOriginalExtension() ?: 'mp4');
            $path = 'nr1/orientation-videos/'.$video->id.'/'.hash('sha256', $bytes).'.'.$ext;
            Storage::disk(self::DISK)->put($path, $bytes);

            $video->forceFill([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?: 'video/mp4',
                'size_bytes' => (int) $file->getSize(),
            ])->save();

            if ($oldPath && $oldPath !== $path && Storage::disk(self::DISK)->exists($oldPath)) {
                Storage::disk(self::DISK)->delete($oldPath);
            }

            return $video->fresh();
        });
    }

    public function delete(User $actor, Nr1OrientationVideo $video): void
    {
        $this->assertTalents($actor);

        DB::transaction(function () use ($video): void {
            Survey::query()->where('intro_video_id', $video->id)->update([
                'intro_video_id' => null,
                'intro_video_required' => false,
            ]);
            Survey::query()->where('closing_video_id', $video->id)->update([
                'closing_video_id' => null,
                'closing_video_required' => false,
            ]);

            $video->deleteStoredFile();
            $video->delete();
        });
    }

    /**
     * @param  array{
     *     intro_video_required?: bool,
     *     closing_video_required?: bool,
     *     intro_video_id?: int|null,
     *     closing_video_id?: int|null
     * }  $data
     */
    public function configureSurvey(User $actor, Survey $survey, array $data): Survey
    {
        $this->assertTalents($actor);
        abort_unless($survey->usesReviewFlow(), 404);

        $introRequired = (bool) ($data['intro_video_required'] ?? false);
        $closingRequired = (bool) ($data['closing_video_required'] ?? false);
        $introId = isset($data['intro_video_id']) && $data['intro_video_id'] !== '' && $data['intro_video_id'] !== null
            ? (int) $data['intro_video_id']
            : null;
        $closingId = isset($data['closing_video_id']) && $data['closing_video_id'] !== '' && $data['closing_video_id'] !== null
            ? (int) $data['closing_video_id']
            : null;

        if ($introRequired) {
            $intro = $this->requireActiveRole($introId, Nr1OrientationVideo::ROLE_INTRO);
            $introId = $intro->id;
        } else {
            $introId = $introId !== null ? $this->requireActiveRole($introId, Nr1OrientationVideo::ROLE_INTRO)->id : null;
        }

        if ($closingRequired) {
            $closing = $this->requireActiveRole($closingId, Nr1OrientationVideo::ROLE_CLOSING);
            $closingId = $closing->id;
        } else {
            $closingId = $closingId !== null ? $this->requireActiveRole($closingId, Nr1OrientationVideo::ROLE_CLOSING)->id : null;
        }

        $survey->forceFill([
            'intro_video_required' => $introRequired,
            'closing_video_required' => $closingRequired,
            'intro_video_id' => $introId,
            'closing_video_id' => $closingId,
        ])->save();

        return $survey->fresh();
    }

    /**
     * Payload público da jornada / questionário (URLs só com token da pesquisa).
     *
     * @return array{
     *     intro: ?array{id: int, title: string, required: bool, url: string},
     *     closing: ?array{id: int, title: string, required: bool, url: string}
     * }
     */
    public function publicVideosForSurvey(Survey $survey, string $token): array
    {
        $survey->loadMissing(['introVideo', 'closingVideo']);

        return [
            'intro' => $this->publicSlot(
                $survey->intro_video_required,
                $survey->introVideo,
                $token,
            ),
            'closing' => $this->publicSlot(
                $survey->closing_video_required,
                $survey->closingVideo,
                $token,
            ),
        ];
    }

    /**
     * @return array{id: int, title: string, required: bool, url: string}|null
     */
    private function publicSlot(bool $required, ?Nr1OrientationVideo $video, string $token): ?array
    {
        if (! $required || $video === null || ! $video->is_active) {
            return null;
        }

        return [
            'id' => $video->id,
            'title' => $video->title,
            'required' => true,
            'url' => route('survey.public.orientation-video', [$token, $video->id]),
        ];
    }

    private function requireActiveRole(?int $id, string $role): Nr1OrientationVideo
    {
        if ($id === null) {
            throw new Nr1CampaignException(
                $role === Nr1OrientationVideo::ROLE_INTRO
                    ? 'Selecione o vídeo de abertura na biblioteca.'
                    : 'Selecione o vídeo de encerramento na biblioteca.'
            );
        }

        $video = Nr1OrientationVideo::query()->whereKey($id)->first();
        if ($video === null || ! $video->is_active || $video->role !== $role) {
            throw new Nr1CampaignException('O vídeo escolhido não está disponível para esta etapa.');
        }

        return $video;
    }

    private function assertVideoFile(UploadedFile $file): void
    {
        $maxKb = $this->maxUploadKb();
        if ($file->getSize() > $maxKb * 1024) {
            throw new Nr1CampaignException('O vídeo pode ter no máximo '.((int) config('nr1.orientation_videos.max_upload_mb', 200)).' MB.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $mime = strtolower((string) $file->getMimeType());
        $okExt = in_array($ext, ['mp4', 'webm'], true);
        $okMime = str_starts_with($mime, 'video/') || in_array($mime, ['application/mp4', 'application/octet-stream'], true);
        if (! $okExt || ! $okMime) {
            throw new Nr1CampaignException('Envie um vídeo MP4 ou WebM.');
        }
    }

    private function assertRole(string $role): void
    {
        if (! in_array($role, Nr1OrientationVideo::ROLES, true)) {
            throw new Nr1CampaignException('Papel do vídeo inválido.');
        }
    }

    private function assertTalents(User $actor): void
    {
        if (! $actor->isSuperAdmin() && ! $actor->isOwner()) {
            throw new Nr1CampaignException('Apenas a Talents pode gerir os vídeos da jornada NR-1.');
        }
    }
}
