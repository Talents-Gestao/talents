<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Enums\HiringCommunicationKind;
use App\Enums\HiringCommunicationStatus;
use App\Mail\HiringFollowupInvitationMail;
use App\Models\HiringCommunication;
use App\Models\HiringFollowupInvitation;
use App\Services\Hiring\EmploymentCycleService;
use App\Services\Hiring\HiringCommunicationService;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

class FollowupInvitationTest extends FollowupTestCase
{
    private function sendUrl($cycle, $milestone): string
    {
        return route('admin.contratacao.pos.milestones.send', [$cycle, $milestone]);
    }

    public function test_both_with_ineligible_manager_sends_only_to_collaborator_and_explains(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        [$application, $vacancy] = $this->hiredApplication();
        $unreviewed = $this->manager($vacancy->company_id, ['reviewed_at' => null]);
        $cycle = app(EmploymentCycleService::class)->registerAdmission($application, [
            'manager_contact_id' => $unreviewed->id,
            'admission_date' => now()->subDays(16)->toDateString(),
            'collaborator_email_opt_in' => true,
        ], null);
        $milestone = $cycle->milestones()->first();

        $response = $this->actingAs($owner)
            ->postJson($this->sendUrl($cycle, $milestone), ['target' => 'both', 'channel' => 'email'])
            ->assertOk();

        $results = collect($response->json('results'))->keyBy('audience');
        $this->assertSame('emailed', $results['collaborator']['outcome']);
        $this->assertSame('skipped', $results['manager']['outcome']);
        $this->assertSame('Gestor sem conferência.', $results['manager']['reason']);

        Mail::assertSent(HiringFollowupInvitationMail::class, fn ($mail) => $mail->hasTo('ana.souza@example.com'));
        Mail::assertSentCount(1);
        $this->assertSame(1, HiringFollowupInvitation::query()->count());
        $invitation = HiringFollowupInvitation::query()->firstOrFail();
        $this->assertNotNull($invitation->emailed_at);
        $this->assertNull($invitation->copied_at);
    }

    public function test_incident_overdue_milestone_without_manager_does_not_block_collaborator(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        [$application] = $this->hiredApplication();
        // Caso D15: admissão antiga, nenhum gestor definido.
        $cycle = app(EmploymentCycleService::class)->registerAdmission($application, [
            'admission_date' => now()->subDays(40)->toDateString(),
            'collaborator_email_opt_in' => true,
        ], null);
        $d15 = $cycle->milestones()->first();

        $this->actingAs($owner)
            ->get(route('admin.contratacao.pos.show', $cycle))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('milestones.0.status_label', 'Vencido · revisar')
                ->where('milestones.0.eligibility.email.manager', 'Gestor não definido.')
                ->where('milestones.0.eligibility.email.collaborator', null)
                ->where('cycle.manager', null));

        $results = collect($this->actingAs($owner)->postJson($this->sendUrl($cycle, $d15), ['target' => 'both', 'channel' => 'email'])
            ->assertOk()->json('results'))->keyBy('audience');
        $this->assertSame('emailed', $results['collaborator']['outcome']);
        $this->assertSame('Gestor não definido.', $results['manager']['reason']);

        // Só gestor, sem gestor: nada sai, motivo claro, sem erro.
        $results = collect($this->actingAs($owner)->postJson($this->sendUrl($cycle, $d15), ['target' => 'manager', 'channel' => 'copy'])
            ->assertOk()->json('results'));
        $this->assertSame('skipped', $results[0]['outcome']);
    }

    public function test_retry_reuses_invitation_and_manager_change_revokes_and_creates_new(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $cycle = $this->activeCycle();
        $milestone = $cycle->milestones()->first();

        $first = $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'manager', 'channel' => 'copy'])->json('results.0');
        $again = $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'manager', 'channel' => 'copy'])->json('results.0');
        $this->assertSame('ready_to_copy', $first['outcome']);
        $this->assertSame($first['invitation_id'], $again['invitation_id']);
        $this->assertSame($first['link'], $again['link']);
        $this->assertSame(1, HiringFollowupInvitation::query()->count());

        $newManager = $this->manager($cycle->company_id, ['name' => 'Nina Nova', 'email' => 'nina@empresa.test']);
        $this->actingAs($owner)->patch(route('admin.contratacao.pos.update', $cycle), [
            'role_title' => 'Analista de RH',
            'manager_contact_id' => $newManager->id,
            'collaborator_email' => 'ana.souza@example.com',
            'collaborator_email_opt_in' => true,
        ])->assertSessionHasNoErrors();

        $third = $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'manager', 'channel' => 'copy'])->json('results.0');
        $this->assertNotSame($first['invitation_id'], $third['invitation_id']);

        $old = HiringFollowupInvitation::query()->find($first['invitation_id']);
        $this->assertNotNull($old->revoked_at);
        $this->assertStringContainsString('Destinatário alterado', $old->revoke_reason);
        $this->assertSame('Nina Nova', HiringFollowupInvitation::query()->find($third['invitation_id'])->recipient_name);

        // O link antigo mostra a mensagem de revogado.
        $this->get(route('hiring-followup.public', $old->token))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('HiringFollowup/Unavailable'));
    }

    public function test_copy_records_copied_at_and_never_emailed_at(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $cycle = $this->activeCycle();
        $milestone = $cycle->milestones()->first();

        $result = $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'collaborator', 'channel' => 'copy'])->json('results.0');
        $this->assertStringContainsString('/pos-contratacao/', $result['message']);
        $invitation = HiringFollowupInvitation::query()->findOrFail($result['invitation_id']);
        $this->assertNull($invitation->copied_at);

        $this->actingAs($owner)->postJson($result['copy_url'])->assertOk()->assertJsonPath('state', 'copied')->assertJsonPath('state_label', 'Link copiado');

        $invitation->refresh();
        $this->assertNotNull($invitation->copied_at);
        $this->assertNull($invitation->emailed_at);
        Mail::assertNothingSent();
    }

    public function test_followup_emails_share_the_daily_limit_of_twenty(): void
    {
        Mail::fake();
        $this->enableSmtp();
        $owner = $this->owner();
        $cycle = $this->activeCycle();
        $milestone = $cycle->milestones()->first();
        $contact = $this->contact($cycle->company_id);

        for ($i = 0; $i < HiringCommunicationService::DAILY_EMAIL_LIMIT - 1; $i++) {
            HiringCommunication::query()->create([
                'hiring_vacancy_id' => $cycle->application->hiring_vacancy_id,
                'company_contact_id' => $contact->id,
                'kind' => HiringCommunicationKind::StageUpdate,
                'channel' => 'email',
                'body' => 'Anterior '.$i,
                'idempotency_key' => hash('sha256', 'anterior'.$i),
                'status' => HiringCommunicationStatus::Sent,
                'sent_at' => now(),
            ]);
        }

        // Dois destinos com 1 restante: recusado inteiro.
        $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'both', 'channel' => 'email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.limit.0', 'Limite de 20 e-mails de Contratação por dia. Hoje restam 1.');
        Mail::assertNothingSent();

        $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'collaborator', 'channel' => 'email'])->assertOk();
        $this->assertSame(0, app(HiringCommunicationService::class)->emailsRemainingToday());

        $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'manager', 'channel' => 'email'])->assertStatus(422);
        Mail::assertSentCount(1);
    }

    public function test_smtp_failure_is_recorded_and_paused_cycle_refuses(): void
    {
        $this->enableSmtp();
        $owner = $this->owner();
        $cycle = $this->activeCycle();
        $milestone = $cycle->milestones()->first();

        Mail::shouldReceive('to->send')->once()->andThrow(new \RuntimeException('Connection refused'));
        $result = $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'collaborator', 'channel' => 'email'])->json('results.0');
        $this->assertSame('failed', $result['outcome']);
        $invitation = HiringFollowupInvitation::query()->firstOrFail();
        $this->assertNull($invitation->emailed_at);
        $this->assertStringContainsString('Connection refused', $invitation->email_failure_reason);
        $this->assertSame('failed', $invitation->state()->value);

        $this->actingAs($owner)->post(route('admin.contratacao.pos.pause', $cycle), ['reason' => 'Licença']);
        $this->actingAs($owner)->postJson($this->sendUrl($cycle, $milestone), ['target' => 'collaborator', 'channel' => 'copy'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('followup');
    }
}
