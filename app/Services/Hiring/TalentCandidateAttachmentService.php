<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\TalentCandidateAuditAction;
use App\Models\TalentCandidate;
use App\Models\TalentCandidateAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Anexos internos do candidato: guardados no disco privado com nome gerado, baixados só pelo controller
 * e sempre auditados. Nunca analisados automaticamente nem enviados a exportação, relatório, IA, dossiê ou portal.
 */
final class TalentCandidateAttachmentService
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Tipo detectado pelo conteúdo (finfo) => extensões aceitas para ele. */
    public const ALLOWED = [
        'application/pdf' => ['pdf'],
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
    ];

    private const STORED_EXTENSION = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    public function __construct(
        private readonly TalentCandidateAuditor $auditor,
    ) {}

    /**
     * @param  array{sender_name: string, received_on: string, purpose: string}  $data
     */
    public function store(TalentCandidate $candidate, UploadedFile $file, array $data, ?int $userId): TalentCandidateAttachment
    {
        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_file($realPath)) {
            $this->fail('Não foi possível ler o arquivo enviado.');
        }

        $size = (int) filesize($realPath);
        if ($size <= 0) {
            $this->fail('O arquivo está vazio.');
        }
        if ($size > self::MAX_BYTES) {
            $this->fail('O arquivo pode ter até 5 MB.');
        }

        $mime = $this->detectMime($realPath);
        $extension = mb_strtolower($file->getClientOriginalExtension());
        if (! isset(self::ALLOWED[$mime]) || ! in_array($extension, self::ALLOWED[$mime], true)) {
            $this->fail('Envie um arquivo PDF, PNG, JPG ou JPEG. O conteúdo do arquivo precisa corresponder à extensão.');
        }

        $sha256 = hash_file('sha256', $realPath);
        if (TalentCandidateAttachment::query()
            ->where('talent_candidate_id', $candidate->id)
            ->where('sha256', $sha256)
            ->exists()) {
            $this->fail('Este arquivo já foi anexado a este candidato.');
        }

        $directory = 'candidates/'.$candidate->id;
        $storedName = Str::uuid()->toString().'.'.self::STORED_EXTENSION[$mime];
        $path = Storage::disk(TalentCandidateAttachment::DISK)->putFileAs($directory, $file, $storedName);
        if ($path === false) {
            $this->fail('Não foi possível guardar o arquivo. Tente novamente.');
        }

        try {
            return DB::transaction(function () use ($candidate, $file, $data, $userId, $path, $mime, $size, $sha256): TalentCandidateAttachment {
                $attachment = TalentCandidateAttachment::query()->create([
                    'talent_candidate_id' => $candidate->id,
                    'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
                    'path' => $path,
                    'mime' => $mime,
                    'size' => $size,
                    'sha256' => $sha256,
                    'sender_name' => trim($data['sender_name']),
                    'received_on' => Carbon::parse($data['received_on'])->toDateString(),
                    'purpose' => trim($data['purpose']),
                    'privacy_confirmed_at' => now(),
                    'uploaded_by' => $userId,
                ]);

                $this->auditor->record($candidate, TalentCandidateAuditAction::AttachmentUploaded, $userId, $this->meta($attachment));

                return $attachment;
            });
        } catch (\Throwable $e) {
            Storage::disk(TalentCandidateAttachment::DISK)->delete($path);
            throw $e;
        }
    }

    public function download(TalentCandidateAttachment $attachment, ?int $userId): StreamedResponse
    {
        $disk = Storage::disk(TalentCandidateAttachment::DISK);
        abort_unless($disk->exists($attachment->path), 404, 'Arquivo não encontrado.');

        $this->auditor->record($attachment->candidate, TalentCandidateAuditAction::AttachmentDownloaded, $userId, $this->meta($attachment));

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function delete(TalentCandidateAttachment $attachment, ?int $userId): void
    {
        $candidate = $attachment->candidate;
        $path = $attachment->path;
        $meta = $this->meta($attachment);

        DB::transaction(function () use ($attachment, $candidate, $userId, $meta): void {
            $attachment->delete();
            $this->auditor->record($candidate, TalentCandidateAuditAction::AttachmentDeleted, $userId, $meta);
        });

        Storage::disk(TalentCandidateAttachment::DISK)->delete($path);
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    private function safeOriginalName(string $name): string
    {
        $name = trim(str_replace(["\r", "\n", '"', '/', '\\'], ' ', $name));

        return $name !== '' ? mb_substr($name, 0, 255) : 'anexo';
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(TalentCandidateAttachment $attachment): array
    {
        return [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime' => $attachment->mime,
            'size' => $attachment->size,
        ];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
