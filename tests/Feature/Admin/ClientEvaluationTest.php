<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ClientEvaluationStatus;
use App\Mail\ClientEvaluationInvitationMail;
use App\Models\ClientEvaluation;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_invite_and_open_public_form(): void
    {
        $this->withoutVite();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company('Cliente Alfa');

        $this->actingAs($admin)
            ->post(route('admin.client-evaluations.store'), [
                'company_id' => $company->id,
                'cycle_year' => 2026,
                'cycle_quarter' => 4,
                'respondent_name' => 'Ana Lima',
                'respondent_email' => 'ana@alfa.test',
                'respondent_phone' => '11 98765-4321',
                'expires_at' => now()->addDays(10)->toDateString(),
            ])
            ->assertRedirect();

        $evaluation = ClientEvaluation::query()->firstOrFail();
        $this->assertSame(ClientEvaluationStatus::Pending, $evaluation->status);
        $this->assertNotNull($evaluation->whatsappUrl());

        $this->get(route('client-evaluation.public', $evaluation->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientEvaluation/Take')
                ->where('evaluation.company_name', 'Cliente Alfa'));
    }

    public function test_new_invite_for_same_cycle_replaces_pending_link(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company('Cliente Beta');

        $this->actingAs($admin)->post(route('admin.client-evaluations.store'), $this->payload($company))->assertRedirect();
        $first = ClientEvaluation::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.client-evaluations.store'), $this->payload($company))->assertRedirect();

        $first->refresh();
        $this->assertSame(ClientEvaluationStatus::Superseded, $first->status);
        $this->assertSame(1, ClientEvaluation::query()->where('status', ClientEvaluationStatus::Pending)->count());

        $this->get(route('client-evaluation.public', $first->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ClientEvaluation/Unavailable'));
    }

    public function test_client_can_answer_once(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company('Cliente Gama');
        $this->actingAs($admin)->post(route('admin.client-evaluations.store'), $this->payload($company));
        $evaluation = ClientEvaluation::query()->firstOrFail();

        $this->post(route('client-evaluation.public.submit', $evaluation->public_token), [
            'nps_score' => 9,
            'service_score' => 5,
            'delivery_score' => 4,
            'communication_score' => 5,
            'comment' => 'Entrega clara e no prazo.',
            'answered_name' => 'Ana Lima',
            'answered_role' => 'Diretora',
        ])->assertRedirect(route('client-evaluation.public.thanks', $evaluation->public_token));

        $evaluation->refresh();
        $this->assertSame(ClientEvaluationStatus::Answered, $evaluation->status);
        $this->assertSame(9, $evaluation->nps_score);
        $this->assertSame('Promotor', $evaluation->npsBandLabel());

        $this->get(route('client-evaluation.public.thanks', $evaluation->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ClientEvaluation/ThankYou'));

        $this->post(route('client-evaluation.public.submit', $evaluation->public_token), [
            'nps_score' => 1,
            'service_score' => 1,
            'delivery_score' => 1,
            'communication_score' => 1,
            'answered_name' => 'Outra pessoa',
        ])->assertSessionHasErrors('nps_score');

        $this->assertSame(9, $evaluation->fresh()->nps_score);
    }

    public function test_answered_cycle_cannot_receive_another_invite(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company('Cliente Delta');
        $this->actingAs($admin)->post(route('admin.client-evaluations.store'), $this->payload($company));
        $evaluation = ClientEvaluation::query()->firstOrFail();

        $this->post(route('client-evaluation.public.submit', $evaluation->public_token), [
            'nps_score' => 8,
            'service_score' => 4,
            'delivery_score' => 4,
            'communication_score' => 4,
            'answered_name' => 'Ana Lima',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->from(route('admin.client-evaluations.create'))
            ->post(route('admin.client-evaluations.store'), $this->payload($company))
            ->assertRedirect(route('admin.client-evaluations.create'))
            ->assertSessionHasErrors('cycle_quarter');
    }

    public function test_expired_invite_is_closed_and_can_be_replaced(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company('Cliente Épsilon');
        $this->actingAs($admin)->post(route('admin.client-evaluations.store'), $this->payload($company));
        $evaluation = ClientEvaluation::query()->firstOrFail();
        $evaluation->update(['expires_at' => now()->subDay()]);

        $this->get(route('client-evaluation.public', $evaluation->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientEvaluation/Unavailable')
                ->where('message', 'O prazo desta avaliação encerrou.'));

        $this->actingAs($admin)
            ->post(route('admin.client-evaluations.store'), $this->payload($company))
            ->assertRedirect();

        $this->assertSame(ClientEvaluationStatus::Expired, $evaluation->fresh()->status);
        $this->assertSame(1, ClientEvaluation::query()->where('status', ClientEvaluationStatus::Pending)->count());
    }

    public function test_owner_can_send_invitation_email(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company('Cliente Zeta');
        $this->actingAs($admin)->post(route('admin.client-evaluations.store'), $this->payload($company));
        $evaluation = ClientEvaluation::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.client-evaluations.email', $evaluation))
            ->assertRedirect(route('admin.client-evaluations.show', $evaluation));

        Mail::assertSent(ClientEvaluationInvitationMail::class, fn (ClientEvaluationInvitationMail $mail): bool => $mail->hasTo('ana@alfa.test'));
        $this->assertNotNull($evaluation->fresh()->sent_at);
    }

    public function test_owner_can_delete_evaluation_and_free_the_cycle(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company('Cliente Teta');
        $this->actingAs($admin)->post(route('admin.client-evaluations.store'), $this->payload($company));
        $evaluation = ClientEvaluation::query()->firstOrFail();
        $token = $evaluation->public_token;

        $this->post(route('client-evaluation.public.submit', $token), [
            'nps_score' => 8,
            'service_score' => 4,
            'delivery_score' => 4,
            'communication_score' => 4,
            'answered_name' => 'Ana Lima',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('admin.client-evaluations.destroy', $evaluation))
            ->assertRedirect(route('admin.client-evaluations.index'))
            ->assertSessionHas('success', 'Avaliação excluída.');

        $this->assertDatabaseMissing('client_evaluations', ['id' => $evaluation->id]);
        $this->get(route('client-evaluation.public', $token))->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.client-evaluations.store'), $this->payload($company))
            ->assertRedirect();

        $this->assertSame(1, ClientEvaluation::query()->where('status', ClientEvaluationStatus::Pending)->count());
    }

    public function test_company_user_cannot_delete_admin_evaluation(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $company = $this->company('Cliente Iota');
        $this->actingAs($admin)->post(route('admin.client-evaluations.store'), $this->payload($company));
        $evaluation = ClientEvaluation::query()->firstOrFail();
        $user = User::factory()->companyUser($company->id)->create();

        $this->actingAs($user)
            ->delete(route('admin.client-evaluations.destroy', $evaluation))
            ->assertForbidden();

        $this->assertDatabaseHas('client_evaluations', ['id' => $evaluation->id]);
    }

    public function test_company_user_cannot_open_admin_evaluations(): void
    {
        $company = $this->company('Cliente Eta');
        $user = User::factory()->companyUser($company->id)->create();

        $this->actingAs($user)
            ->get(route('admin.client-evaluations.index'))
            ->assertForbidden();
    }

    private function company(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'contact_email' => 'contato@empresa.test',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Company $company): array
    {
        return [
            'company_id' => $company->id,
            'cycle_year' => 2026,
            'cycle_quarter' => 4,
            'respondent_name' => 'Ana Lima',
            'respondent_email' => 'ana@alfa.test',
            'respondent_phone' => '11987654321',
            'expires_at' => now()->addDays(15)->toDateString(),
        ];
    }
}
