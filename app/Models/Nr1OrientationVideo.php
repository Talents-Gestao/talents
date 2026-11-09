<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vídeo permanente da biblioteca global NR-1 (abertura ou encerramento da jornada).
 */
class Nr1OrientationVideo extends Model
{
    public const ROLE_INTRO = 'intro';

    public const ROLE_CLOSING = 'closing';

    public const ROLES = [self::ROLE_INTRO, self::ROLE_CLOSING];

    public const ROLE_LABELS = [
        self::ROLE_INTRO => 'Abertura',
        self::ROLE_CLOSING => 'Encerramento',
    ];

    protected $fillable = [
        'title',
        'role',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'duration_seconds',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function introSurveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'intro_video_id');
    }

    public function closingSurveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'closing_video_id');
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    public function isVideo(): bool
    {
        if (is_string($this->mime)) {
            $mime = strtolower($this->mime);
            if (str_starts_with($mime, 'video/')) {
                return true;
            }
            if (in_array($mime, ['application/mp4', 'application/x-mp4'], true)) {
                return true;
            }
        }

        $ext = strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm', 'm4v'], true);
    }

    public function toHttpResponse(): StreamedResponse|Response
    {
        $disk = Storage::disk($this->disk ?: 'local');
        abort_unless($this->path && $disk->exists($this->path), 404);

        $filename = str_replace('"', '', $this->original_name ?: 'video.mp4');
        $contentType = is_string($this->mime) && str_starts_with(strtolower($this->mime), 'video/')
            ? $this->mime
            : 'video/mp4';

        return $disk->response(
            $this->path,
            $this->original_name ?: 'video.mp4',
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    public function deleteStoredFile(): void
    {
        if (! $this->path) {
            return;
        }

        $disk = Storage::disk($this->disk ?: 'local');
        if ($disk->exists($this->path)) {
            $disk->delete($this->path);
        }
    }
}
