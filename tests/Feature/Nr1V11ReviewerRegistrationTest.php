<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkspaceType;
use App\Mail\ExternalReviewerInvitationMail;
use App\Models\ApproverOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

/**
 * Cadastro do revisor externo (PASSEG) pela Talents: nome, e-mail, organização aprovadora e convite. Continua desligado
 * por padrão e sem acesso enquanto a revisão externa estiver desligada (MFA, D14).
 */
class Nr1V11ReviewerRegistrationTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private function talents(): User
    {
        return User::factory()->talentsOwner()->create();
    }

    public function test_talents_registers_a_reviewer_with_organization_and_sends_the_invitation(): void
    {
        Mail::fake();
        $organization = ApproverOrganization::query()->create(['name' => 'Organização aprovadora', 'is_active' => true]);

        $this->actingAs($this->talents())->post(route('admin.external-reviewers.store'), [
            'name' => 'Revisora PASSEG',
            'email' => 'Revisora@Passeg.Example',
            'organization_id' => $organization->id,
        ])->assertSessionHas('success');

        $user = User::query()->where('email', 'revisora@passeg.example')->sole();
        $this->assertSame(UserRole::ExternalReviewer, $user->role);
        $this->assertFalse($user->hasCompletedRegistration(), 'A senha só é definida pelo próprio revisor, pelo convite.');
        $workspace = $user->workspaces()->sole();
        $this->assertSame(WorkspaceType::ExternalReview, $workspace->workspace_type);
        $this->assertSame($organization->id, $workspace->approver_organization_id);
        $this->assertNull($workspace->company_id);

        Mail::assertSent(ExternalReviewerInvitationMail::class, fn ($mail) => $mail->hasTo('revisora@passeg.example') && str_contains($mail->setPasswordUrl, 'password'));
    }

    public function test_a_new_organization_can_be_created_inline_and_one_is_required(): void
    {
        Mail::fake();
        $talents = $this->talents();

        $this->actingAs($talents)->post(route('admin.external-reviewers.store'), ['name' => 'Fulano', 'email' => 'fulano@passeg.example'])->assertSessionHas('error');
        $this->assertSame(0, User::query()->where('email', 'fulano@passeg.example')->count());

        $this->actingAs($talents)->post(route('admin.external-reviewers.store'), ['name' => 'Fulano', 'email' => 'fulano@passeg.example', 'new_organization' => 'Nova organização'])->assertSessionHas('success');
        $this->assertSame('Nova organização', ApproverOrganization::query()->sole()->name);
    }

    public function test_existing_emails_are_refused_and_validation_applies(): void
    {
        Mail::fake();
        $talents = $this->talents();
        $existing = User::factory()->create(['email' => 'ja@existe.example']);
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);

        $this->actingAs($talents)->post(route('admin.external-reviewers.store'), ['name' => 'X', 'email' => 'ja@existe.example', 'organization_id' => $organization->id])->assertSessionHas('error');
        $this->actingAs($talents)->post(route('admin.external-reviewers.store'), ['name' => '', 'email' => 'invalido'])->assertSessionHasErrors(['name', 'email']);

        $this->assertSame($existing->role, $existing->fresh()->role, 'A conta existente não vira revisora.');
        Mail::assertNothingSent();
    }

    public function test_the_registration_gives_no_access_while_external_review_is_off(): void
    {
        Mail::fake();
        $this->assertFalse(config('nr1.external_review.enabled'), 'Desligado por padrão.');
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        $this->actingAs($this->talents())->post(route('admin.external-reviewers.store'), ['name' => 'Rev', 'email' => 'rev@passeg.example', 'organization_id' => $organization->id]);
        $reviewer = User::query()->where('email', 'rev@passeg.example')->sole();

        $this->actingAs($reviewer)->get(route('external-review.index'))->assertStatus(404);
        $this->actingAs($reviewer)->get(route('admin.external-reviewers.index'))->assertForbidden();
    }

    public function test_resend_deactivate_and_reactivate(): void
    {
        Mail::fake();
        $talents = $this->talents();
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        $this->actingAs($talents)->post(route('admin.external-reviewers.store'), ['name' => 'Rev', 'email' => 'rev@passeg.example', 'organization_id' => $organization->id]);
        $reviewer = User::query()->where('email', 'rev@passeg.example')->sole();

        $this->actingAs($talents)->post(route('admin.external-reviewers.resend', $reviewer->id))->assertSessionHas('success');
        Mail::assertSent(ExternalReviewerInvitationMail::class, 2);

        $this->actingAs($talents)->post(route('admin.external-reviewers.toggle', $reviewer->id), ['active' => false])->assertSessionHas('success');
        $this->assertFalse($reviewer->fresh()->is_active);
        $this->assertFalse((bool) $reviewer->workspaces()->sole()->is_active);

        $this->actingAs($talents)->post(route('admin.external-reviewers.toggle', $reviewer->id), ['active' => true])->assertSessionHas('success');
        $this->assertTrue($reviewer->fresh()->is_active);

        // Só revisor externo: um usuário comum não é alvo destas ações.
        $other = User::factory()->companyAdmin()->create();
        $this->actingAs($talents)->post(route('admin.external-reviewers.toggle', $other->id), ['active' => false])->assertSessionHas('error');
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_the_page_lists_reviewers_and_warns_that_review_is_off(): void
    {
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        User::factory()->externalReviewer($organization->id)->create(['name' => 'Rev Um']);

        $this->actingAs($this->talents())->get(route('admin.external-reviewers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ExternalReviewers/Index')
            ->where('externalReviewEnabled', false)
            ->has('reviewers', 1)
            ->where('reviewers.0.name', 'Rev Um')
            ->where('reviewers.0.organization', 'Org')
            ->has('organizations', 1));
    }

    public function test_only_the_talents_team_registers_reviewers(): void
    {
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        $payload = ['name' => 'X', 'email' => 'x@passeg.example', 'organization_id' => $organization->id];
        $companyAdmin = User::factory()->companyAdmin()->create();
        $plain = User::factory()->superAdmin()->create(['is_owner' => false]);
        $reviewer = User::factory()->externalReviewer($organization->id)->create();

        foreach ([$companyAdmin, $plain, $reviewer] as $user) {
            $this->actingAs($user)->get(route('admin.external-reviewers.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.external-reviewers.store'), $payload)->assertForbidden();
        }

        $this->assertSame(0, User::query()->where('email', 'x@passeg.example')->count());
    }
}
