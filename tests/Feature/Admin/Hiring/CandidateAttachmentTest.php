<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\TalentCandidateAuditAction;
use App\Models\TalentCandidate;
use App\Models\TalentCandidateAttachment;
use App\Models\TalentCandidateAuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

class CandidateAttachmentTest extends HiringVacancyTestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_attachments_need_their_own_permission(): void
    {
        $admin = $this->adminWith(['contratacao_candidatos' => ['view', 'create', 'edit', 'delete']]);
        $candidate = TalentCandidate::query()->create(['name' => 'Ana']);
        $attachment = $this->storeAttachment($candidate);

        $this->actingAs($admin)
            ->get(route('admin.contratacao.candidatos.anexos.index', $candidate))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('admin.contratacao.candidatos.anexos.store', $candidate), $this->payload())
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.contratacao.candidatos.anexos.download', [$candidate, $attachment]))
            ->assertForbidden();
        $this->actingAs($admin)
            ->delete(route('admin.contratacao.candidatos.anexos.destroy', [$candidate, $attachment]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.contratacao.candidatos.show', $candidate))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('attachments_count')
                ->missing('attachments_url')
                ->where('can.view_attachments', false)
                ->where('audit.0.action', TalentCandidateAuditAction::AttachmentUploaded->value)
                ->where('audit.0.attachment_name', null));

        $this->assertSame(1, TalentCandidateAttachment::query()->count());
        $this->assertSame(0, TalentCandidateAuditLog::query()
            ->where('action', TalentCandidateAuditAction::AttachmentDownloaded->value)->count());
    }

    public function test_module_is_owner_only_until_granted_and_not_backfilled_from_legacy(): void
    {
        $legacy = $this->adminWith(['solides' => ['view', 'create']]);
        $candidate = TalentCandidate::query()->create(['name' => 'Bia']);

        $this->actingAs($legacy)
            ->get(route('admin.contratacao.candidatos.anexos.index', $candidate))
            ->assertForbidden();

        $granted = $this->adminWith([
            'contratacao_candidatos' => ['view'],
            'contratacao_anexos' => ['view'],
        ]);
        $this->actingAs($granted)
            ->get(route('admin.contratacao.candidatos.anexos.index', $candidate))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Hiring/Candidates/Attachments')
                ->where('can.create', false));
    }

    public function test_upload_stores_privately_with_generated_name(): void
    {
        $owner = $this->owner();
        $candidate = TalentCandidate::query()->create(['name' => 'Caio']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.anexos.store', $candidate), $this->payload('Laudo do Caio.pdf'))
            ->assertSessionHasNoErrors();

        $attachment = TalentCandidateAttachment::query()->firstOrFail();
        $this->assertSame('Laudo do Caio.pdf', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime);
        $this->assertSame(hash('sha256', self::PDF), $attachment->sha256);
        $this->assertStringStartsWith('candidates/'.$candidate->id.'/', $attachment->path);
        $this->assertStringNotContainsString('Laudo', $attachment->path);
        $this->assertNotNull($attachment->privacy_confirmed_at);
        Storage::disk('local')->assertExists($attachment->path);

        $this->assertArrayNotHasKey('path', $attachment->toArray());
        $this->assertSame(1, TalentCandidateAuditLog::query()
            ->where('action', TalentCandidateAuditAction::AttachmentUploaded->value)->count());
    }

    public function test_requires_sender_date_purpose_and_privacy_confirmation(): void
    {
        $owner = $this->owner();
        $candidate = TalentCandidate::query()->create(['name' => 'Davi']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.anexos.store', $candidate), [
                'file' => UploadedFile::fake()->createWithContent('doc.pdf', self::PDF),
            ])
            ->assertSessionHasErrors(['sender_name', 'received_on', 'purpose', 'privacy_confirmed']);

        $this->assertSame(0, TalentCandidateAttachment::query()->count());
    }

    public function test_invalid_type_fake_pdf_and_oversized_file_are_refused(): void
    {
        $owner = $this->owner();
        $candidate = TalentCandidate::query()->create(['name' => 'Eva']);
        $url = route('admin.contratacao.candidatos.anexos.store', $candidate);

        // Tipo não permitido.
        $this->actingAs($owner)
            ->post($url, [...$this->payload(), 'file' => UploadedFile::fake()->createWithContent('notas.txt', 'texto simples')])
            ->assertSessionHasErrors('file');

        // Conteúdo falso com extensão .pdf.
        $this->actingAs($owner)
            ->post($url, [...$this->payload(), 'file' => UploadedFile::fake()->createWithContent('falso.pdf', "isto não é um PDF\n")])
            ->assertSessionHasErrors('file');

        // PNG verdadeiro disfarçado de .pdf.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $this->actingAs($owner)
            ->post($url, [...$this->payload(), 'file' => UploadedFile::fake()->createWithContent('imagem.pdf', $png)])
            ->assertSessionHasErrors('file');

        // Maior que 5 MB.
        $big = self::PDF.str_repeat('0', 5 * 1024 * 1024);
        $this->actingAs($owner)
            ->post($url, [...$this->payload(), 'file' => UploadedFile::fake()->createWithContent('grande.pdf', $big)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, TalentCandidateAttachment::query()->count());

        // PNG verdadeiro com extensão .png é aceito.
        $this->actingAs($owner)
            ->post($url, [...$this->payload(), 'file' => UploadedFile::fake()->createWithContent('foto.png', $png)])
            ->assertSessionHasNoErrors();
        $this->assertSame('image/png', TalentCandidateAttachment::query()->value('mime'));
    }

    public function test_duplicate_sha256_in_same_candidate_is_refused(): void
    {
        $owner = $this->owner();
        $candidate = TalentCandidate::query()->create(['name' => 'Fil']);
        $other = TalentCandidate::query()->create(['name' => 'Gil']);

        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.anexos.store', $candidate), $this->payload('a.pdf'))
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.anexos.store', $candidate), $this->payload('outro-nome.pdf'))
            ->assertSessionHasErrors(['file' => 'Este arquivo já foi anexado a este candidato.']);

        // O mesmo arquivo em outro candidato é permitido.
        $this->actingAs($owner)
            ->post(route('admin.contratacao.candidatos.anexos.store', $other), $this->payload('a.pdf'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $candidate->attachments()->count());
        $this->assertSame(1, $other->attachments()->count());
    }

    public function test_download_and_delete_are_audited(): void
    {
        $owner = $this->owner();
        $candidate = TalentCandidate::query()->create(['name' => 'Hugo']);
        $attachment = $this->storeAttachment($candidate, 'Comprovante.pdf');

        $response = $this->actingAs($owner)
            ->get(route('admin.contratacao.candidatos.anexos.download', [$candidate, $attachment]))
            ->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Comprovante.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $download = TalentCandidateAuditLog::query()
            ->where('action', TalentCandidateAuditAction::AttachmentDownloaded->value)
            ->firstOrFail();
        $this->assertSame($owner->id, $download->user_id);
        $this->assertSame($attachment->id, $download->meta['attachment_id']);
        $this->assertArrayNotHasKey('content', $download->meta);
        $this->assertNotNull($download->ip);

        $path = $attachment->path;
        $this->actingAs($owner)
            ->delete(route('admin.contratacao.candidatos.anexos.destroy', [$candidate, $attachment]))
            ->assertSessionHasNoErrors();

        $this->assertNull($attachment->fresh());
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(1, TalentCandidateAuditLog::query()
            ->where('action', TalentCandidateAuditAction::AttachmentDeleted->value)
            ->where('talent_candidate_id', $candidate->id)
            ->count());
    }

    public function test_attachment_of_another_candidate_returns_404(): void
    {
        $owner = $this->owner();
        $candidateA = TalentCandidate::query()->create(['name' => 'A']);
        $candidateB = TalentCandidate::query()->create(['name' => 'B']);
        $attachmentB = $this->storeAttachment($candidateB);

        $this->actingAs($owner)
            ->get(route('admin.contratacao.candidatos.anexos.download', [$candidateA, $attachmentB]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->delete(route('admin.contratacao.candidatos.anexos.destroy', [$candidateA, $attachmentB]))
            ->assertNotFound();

        $this->assertNotNull($attachmentB->fresh());
        $this->assertSame(0, TalentCandidateAuditLog::query()
            ->where('action', TalentCandidateAuditAction::AttachmentDownloaded->value)->count());
    }

    public function test_audit_logs_are_insert_only(): void
    {
        $candidate = TalentCandidate::query()->create(['name' => 'Ivo']);
        $this->storeAttachment($candidate);
        $log = TalentCandidateAuditLog::query()->firstOrFail();

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $name = 'documento.pdf'): array
    {
        return [
            'file' => UploadedFile::fake()->createWithContent($name, self::PDF),
            'sender_name' => 'Dra. Paula',
            'received_on' => now()->subDay()->toDateString(),
            'purpose' => 'Comprovação de formação para a vaga.',
            'privacy_confirmed' => '1',
        ];
    }

    private function storeAttachment(TalentCandidate $candidate, string $name = 'documento.pdf'): TalentCandidateAttachment
    {
        $this->actingAs($this->owner())
            ->post(route('admin.contratacao.candidatos.anexos.store', $candidate), $this->payload($name))
            ->assertSessionHasNoErrors();

        return TalentCandidateAttachment::query()->where('talent_candidate_id', $candidate->id)->latest('id')->firstOrFail();
    }
}
