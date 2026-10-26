<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\PsychosocialDocumentException;
use App\Models\ApproverOrganization;
use App\Models\DocumentShare;
use App\Models\DocumentShareReview;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\User;
use App\Services\Psychosocial\DocumentShareService;
use App\Services\Psychosocial\PsychosocialDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Etapa 4A: revisor externo (organização aprovadora). Só lê, comenta e aprova a versão compartilhada
 * com ele; qualquer outra coisa é 404. Tudo desligado por padrão até o MFA (D14).
 */
class Nr1V11ExternalReviewTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private function enable(bool $mfaRequired = false): void
    {
        config([
            'nr1.external_review.enabled' => true,
            'nr1.external_review.mfa_required' => $mfaRequired,
        ]);
    }

    private function organization(string $name = 'Organização aprovadora', bool $active = true): ApproverOrganization
    {
        return ApproverOrganization::query()->create(['name' => $name, 'is_active' => $active]);
    }

    private function reviewer(ApproverOrganization $organization): User
    {
        return User::factory()->externalReviewer($organization->id)->create();
    }

    private function version(?object $fx = null): PsychosocialDocumentVersion
    {
        $fx ??= $this->createSurveyFixture();
        $document = PsychosocialDocument::query()->create([
            'survey_id' => $fx->survey->id,
            'company_id' => $fx->company->id,
            'type' => PsychosocialDocument::TYPE_PGR_SUBSIDY,
        ]);
        $snapshot = ['schema' => 'pgr_subsidy.v1', 'factors' => [['label' => 'Demandas', 'band' => 'yellow']]];

        return PsychosocialDocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'public_identifier' => 'PGR-'.Str::upper(Str::random(10)),
            'kind' => PsychosocialDocumentVersion::KIND_RELEASED,
            'method_version_id' => $fx->survey->method_version_id,
            'instrument_version_id' => $fx->survey->instrument_version_id,
            'snapshot_schema' => 'pgr_subsidy.v1',
            'snapshot' => $snapshot,
            'snapshot_sha256' => hash('sha256', json_encode($snapshot)),
            'content_sha256' => hash('sha256', 'released:'.json_encode($snapshot)),
            'seal' => PsychosocialDocumentVersion::SEAL_PENDING_VALIDATION,
        ]);
    }

    private function share(PsychosocialDocumentVersion $version, User $reviewer, ?User $by = null, ?\DateTimeInterface $expires = null): DocumentShare
    {
        $this->enable();

        return app(DocumentShareService::class)->share(
            $version,
            $reviewer,
            $by ?? User::factory()->superAdmin()->create(['is_owner' => true]),
            Carbon::instance($expires ?? now()->addDays(30)),
        );
    }

    public function test_external_review_is_off_by_default_everywhere(): void
    {
        $this->assertFalse(config('nr1.external_review.enabled'));

        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $version = $this->version();

        $this->actingAs($reviewer)->get(route('external-review.index'))->assertNotFound();
        $this->actingAs($reviewer)->get(route('external-review.show', $version->id))->assertNotFound();

        $this->expectException(PsychosocialDocumentException::class);
        app(DocumentShareService::class)->share($version, $reviewer, User::factory()->superAdmin()->create(), now()->addDay());
    }

    public function test_admin_share_route_does_not_exist_while_off(): void
    {
        $fx = $this->createSurveyFixture();
        $version = $this->version($fx);
        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $reviewer = $this->reviewer($this->organization());

        $this->actingAs($owner)->post(
            route('admin.companies.surveys.psychosocial-documents.share', [$fx->company->id, $fx->survey->id, 'pgr_subsidy']),
            ['reviewer_id' => $reviewer->id, 'expires_at' => now()->addDays(10)->toDateString()],
        )->assertNotFound();

        $this->assertSame(0, DocumentShare::query()->count());
        $this->assertNotNull($version);
    }

    public function test_mfa_extension_point_blocks_the_area_until_implemented(): void
    {
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $version = $this->version();
        $this->share($version, $reviewer);

        config(['nr1.external_review.mfa_required' => true]);

        $this->actingAs($reviewer)->get(route('external-review.index'))->assertForbidden();
        $this->actingAs($reviewer)->get(route('external-review.show', $version->id))->assertForbidden();
    }

    public function test_reviewer_reads_comments_and_approves_the_shared_version(): void
    {
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $version = $this->version();
        $this->share($version, $reviewer);
        $hashBefore = $version->content_sha256;

        $this->actingAs($reviewer)->get(route('external-review.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ExternalReview/Index')->has('shares', 1));

        $this->actingAs($reviewer)->get(route('external-review.show', $version->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ExternalReview/Show')->where('share.approved', false));

        $this->actingAs($reviewer)
            ->post(route('external-review.comment', $version->id), ['body' => 'Ajustar o texto da dimensão Demandas.'])
            ->assertRedirect();
        $this->actingAs($reviewer)->post(route('external-review.comment', $version->id), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($reviewer)->post(route('external-review.approve', $version->id))->assertRedirect();
        $this->actingAs($reviewer)->post(route('external-review.approve', $version->id))
            ->assertSessionHas('error');

        $this->assertSame(1, DocumentShareReview::query()->where('kind', 'approval')->count());
        $this->assertSame(1, DocumentShareReview::query()->where('kind', 'comment')->count());
        $this->assertSame($hashBefore, $version->fresh()->content_sha256, 'Aprovar não altera a versão.');
    }

    public function test_download_goes_through_the_share_and_is_logged(): void
    {
        Storage::fake(PsychosocialDocumentService::DISK);
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $version = $this->version();
        Storage::disk(PsychosocialDocumentService::DISK)->put('docs/x.pdf', '%PDF-fake');
        $version->update([
            'pdf_status' => PsychosocialDocumentVersion::PDF_READY,
            'pdf_path' => 'docs/x.pdf',
            'pdf_sha256' => hash('sha256', '%PDF-fake'),
        ]);
        $this->share($version, $reviewer);

        $this->actingAs($reviewer)->get(route('external-review.download', $version->id))->assertOk();

        $log = PsychosocialAccessLog::query()->where('resource', 'document_download')->firstOrFail();
        $this->assertSame($reviewer->id, $log->user_id);
        $this->assertSame($version->id, (int) $log->document_version_id);
    }

    public function test_unshared_version_is_not_found_for_every_action(): void
    {
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $shared = $this->version();
        $other = $this->version($this->createSurveyFixture());
        $this->share($shared, $reviewer);

        $this->actingAs($reviewer)->get(route('external-review.show', $other->id))->assertNotFound();
        $this->actingAs($reviewer)->get(route('external-review.download', $other->id))->assertNotFound();
        $this->actingAs($reviewer)->post(route('external-review.comment', $other->id), ['body' => 'x'])->assertNotFound();
        $this->actingAs($reviewer)->post(route('external-review.approve', $other->id))->assertNotFound();
        $this->actingAs($reviewer)->get(route('external-review.show', 999999))->assertNotFound();

        $this->assertSame(0, DocumentShareReview::query()->count());
    }

    public function test_another_reviewer_of_the_same_organization_cannot_read_it(): void
    {
        $organization = $this->organization();
        $owner = $this->reviewer($organization);
        $other = $this->reviewer($organization);
        $version = $this->version();
        $this->share($version, $owner);

        $this->actingAs($other)->get(route('external-review.show', $version->id))->assertNotFound();
        $this->actingAs($other)->post(route('external-review.approve', $version->id))->assertNotFound();
        $this->actingAs($other)->get(route('external-review.index'))
            ->assertInertia(fn ($page) => $page->has('shares', 0));
    }

    public function test_expired_and_revoked_shares_stop_working(): void
    {
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $version = $this->version();
        $share = $this->share($version, $reviewer);

        $this->actingAs($reviewer)->get(route('external-review.show', $version->id))->assertOk();

        $this->travel(31)->days();
        $this->actingAs($reviewer)->get(route('external-review.show', $version->id))->assertNotFound();
        $this->travelBack();

        $this->actingAs($reviewer)->get(route('external-review.show', $version->id))->assertOk();
        app(DocumentShareService::class)->revoke($share);
        $this->actingAs($reviewer)->get(route('external-review.show', $version->id))->assertNotFound();
        $this->actingAs($reviewer)->post(route('external-review.comment', $version->id), ['body' => 'x'])->assertNotFound();
    }

    public function test_inactive_organization_loses_access(): void
    {
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $version = $this->version();
        $this->share($version, $reviewer);

        $organization->update(['is_active' => false]);

        $this->actingAs($reviewer)->get(route('external-review.index'))->assertForbidden();
        $this->actingAs($reviewer)->get(route('external-review.show', $version->id))->assertForbidden();
    }

    public function test_share_integrity_rules(): void
    {
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $fx = $this->createSurveyFixture();
        $version = $this->version($fx);
        $by = User::factory()->superAdmin()->create(['is_owner' => true]);
        $this->enable();
        $service = app(DocumentShareService::class);

        // Só revisor externo recebe compartilhamento.
        $companyUser = User::factory()->companyUser($fx->company->id)->create();
        try {
            $service->share($version, $companyUser, $by, now()->addDays(5));
            $this->fail('Usuário de empresa não pode receber compartilhamento.');
        } catch (PsychosocialDocumentException) {
        }

        // Validade máxima.
        try {
            $service->share($version, $reviewer, $by, now()->addDays(400));
            $this->fail('Validade acima do limite.');
        } catch (PsychosocialDocumentException) {
        }

        // Empresa do compartilhamento precisa ser a do documento.
        $foreign = $this->createSurveyFixture();
        try {
            DocumentShare::query()->create([
                'company_id' => $foreign->company->id,
                'survey_id' => $foreign->survey->id,
                'document_version_id' => $version->id,
                'reviewer_id' => $reviewer->id,
                'approver_organization_id' => $organization->id,
                'shared_by' => $by->id,
                'shared_at' => now(),
                'expires_at' => now()->addDays(5),
            ]);
            $this->fail('Empresa divergente do documento.');
        } catch (PsychosocialDocumentException) {
        }

        // Organização divergente da do revisor.
        $otherOrganization = $this->organization('Outra');
        try {
            DocumentShare::query()->create([
                'company_id' => $fx->company->id,
                'survey_id' => $fx->survey->id,
                'document_version_id' => $version->id,
                'reviewer_id' => $reviewer->id,
                'approver_organization_id' => $otherOrganization->id,
                'shared_by' => $by->id,
                'shared_at' => now(),
                'expires_at' => now()->addDays(5),
            ]);
            $this->fail('Organização divergente do revisor.');
        } catch (PsychosocialDocumentException) {
        }

        $share = $service->share($version, $reviewer, $by, now()->addDays(5));

        // Duplicado e imutabilidade.
        try {
            $service->share($version, $reviewer, $by, now()->addDays(5));
            $this->fail('Compartilhamento duplicado.');
        } catch (PsychosocialDocumentException) {
        }
        try {
            $share->update(['expires_at' => now()->addDays(60)]);
            $this->fail('Só a revogação pode mudar.');
        } catch (LogicException) {
        }
        try {
            $share->delete();
            $this->fail('Compartilhamento não é apagado.');
        } catch (LogicException) {
        }

        $review = DocumentShareReview::query()->create([
            'document_share_id' => $share->id,
            'user_id' => $reviewer->id,
            'kind' => 'comment',
            'body' => 'ok',
        ]);
        try {
            $review->update(['body' => 'mudou']);
            $this->fail('Revisão é imutável.');
        } catch (LogicException) {
        }

        $this->assertSame(1, DocumentShare::query()->count());
        $this->assertSame('ok', $review->fresh()->body);
        $this->assertNull($share->fresh()->revoked_at);
    }

    public function test_every_other_profile_is_kept_out_of_the_review_area(): void
    {
        $this->enable();
        $fx = $this->createSurveyFixture();
        $version = $this->version($fx);

        $profiles = [
            'super_admin' => User::factory()->superAdmin()->create(['is_owner' => true]),
            'company_admin' => User::factory()->companyAdmin($fx->company->id)->create(),
            'company_user' => User::factory()->companyUser($fx->company->id)->create(),
        ];

        foreach ($profiles as $label => $user) {
            $this->actingAs($user)->get(route('external-review.index'))->assertForbidden($label);
            $this->actingAs($user)->get(route('external-review.show', $version->id))->assertForbidden($label);
            $this->actingAs($user)->post(route('external-review.approve', $version->id))->assertForbidden($label);
        }
    }

    public function test_reviewer_does_not_inherit_any_company_or_admin_access(): void
    {
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);
        $fx = $this->createSurveyFixture();
        $version = $this->version($fx);
        $this->share($version, $reviewer);

        $this->assertSame(UserRole::ExternalReviewer, $reviewer->fresh()->role);

        $forbidden = [
            ['get', route('client.dashboard')],
            ['get', route('client.surveys.psychosocial-dashboard', $fx->survey->id)],
            ['get', route('client.surveys.psychosocial-documents.download', [$fx->survey->id, 'pgr_subsidy'])],
            ['get', route('admin.dashboard')],
            ['get', route('admin.companies.surveys.psychosocial-documents.download', [$fx->company->id, $fx->survey->id, 'pgr_subsidy'])],
            ['get', route('admin.companies.surveys.raw-export.csv', [$fx->company->id, $fx->survey->id])],
        ];

        foreach ($forbidden as [$method, $url]) {
            $status = $this->actingAs($reviewer)->{$method}($url)->getStatusCode();
            $this->assertContains($status, [302, 403, 404], "{$method} {$url} respondeu {$status} ao revisor externo.");
            $this->assertNotSame(200, $status);
        }
    }

    public function test_login_lands_the_reviewer_on_the_review_area(): void
    {
        $this->enable();
        $organization = $this->organization();
        $reviewer = $this->reviewer($organization);

        $this->post('/login', ['email' => $reviewer->email, 'password' => 'password'])
            ->assertRedirect(route('external-review.index', absolute: false));
    }

    public function test_admin_shares_and_revokes_through_the_routes(): void
    {
        $this->enable();
        $fx = $this->createSurveyFixture();
        $version = $this->version($fx);
        $fx->survey->update(['status' => 'active']);
        PsychosocialDocument::query()->where('id', $version->document_id)->update(['current_version_id' => $version->id]);

        $owner = User::factory()->superAdmin()->create(['is_owner' => true]);
        $reviewer = $this->reviewer($this->organization());

        $this->actingAs($owner)->post(
            route('admin.companies.surveys.psychosocial-documents.share', [$fx->company->id, $fx->survey->id, 'pgr_subsidy']),
            ['reviewer_id' => $reviewer->id, 'expires_at' => now()->addDays(10)->toDateString()],
        )->assertRedirect()->assertSessionHas('success');

        $share = DocumentShare::query()->firstOrFail();
        $this->assertSame($owner->id, (int) $share->shared_by);
        $this->assertSame($fx->company->id, (int) $share->company_id);

        $other = $this->createSurveyFixture();
        $this->actingAs($owner)->delete(route('admin.companies.document-shares.revoke', [$other->company->id, $share->id]))->assertNotFound();
        $this->actingAs($owner)->delete(route('admin.companies.document-shares.revoke', [$fx->company->id, $share->id]))->assertRedirect();
        $this->assertNotNull($share->fresh()->revoked_at);
    }
}
