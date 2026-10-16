<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\ClientEvaluationStatus;
use App\Mail\ClientEvaluationInvitationMail;
use App\Models\ClientEvaluation;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_creates_invite_only_for_own_company(): void
    {
        $this->withoutVite();

        $own = $this->company('Empresa Dona');
        $other = $this->company('Outra Empresa');
        $admin = User::factory()->companyAdmin($own->id)->create();

        $this->actingAs($admin)
            ->post(route('client.avaliacoes.store'), [
                'company_id' => $other->id,
                'cycle_year' => 2026,
                'cycle_quarter' => 4,
                'respondent_name' => 'Ana Lima',
                'respondent_email' => 'ana@dona.test',
                'respondent_phone' => '11987654321',
                'expires_at' => now()->addDays(10)->toDateString(),
            ])
            ->assertRedirect();

        $evaluation = ClientEvaluation::query()->firstOrFail();
        $this->assertSame($own->id, $evaluation->company_id);
        $this->assertSame(ClientEvaluationStatus::Pending, $evaluation->status);

        $this->actingAs($admin)
            ->get(route('client.avaliacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Evaluations/Index')
                ->where('companyName', 'Empresa Dona')
                ->has('evaluations.data', 1));
    }

    public function test_company_admin_cannot_open_another_companys_evaluation(): void
    {
        $own = $this->company('Empresa Dona');
        $other = $this->company('Outra Empresa');
        $admin = User::factory()->companyAdmin($own->id)->create();

        $foreign = ClientEvaluation::query()->create([
            'company_id' => $other->id,
            'cycle_year' => 2026,
            'cycle_quarter' => 4,
            'public_token' => (string) Str::uuid(),
            'status' => ClientEvaluationStatus::Pending,
            'respondent_name' => 'Carlos',
            'respondent_email' => 'carlos@outra.test',
            'expires_at' => now()->addDays(5),
        ]);

        $this->actingAs($admin)
            ->get(route('client.avaliacoes.show', $foreign))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('client.avaliacoes.email', $foreign))
            ->assertNotFound();
    }

    public function test_company_user_cannot_open_evaluations(): void
    {
        $company = $this->company('Empresa Dona');
        $user = User::factory()->companyUser($company->id)->create();

        $this->actingAs($user)
            ->get(route('client.avaliacoes.index'))
            ->assertForbidden();
    }

    public function test_company_admin_can_send_email_for_own_invite(): void
    {
        Mail::fake();

        $company = $this->company('Empresa Dona');
        $admin = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($admin)->post(route('client.avaliacoes.store'), [
            'cycle_year' => 2026,
            'cycle_quarter' => 1,
            'respondent_name' => 'Ana Lima',
            'respondent_email' => 'ana@dona.test',
            'expires_at' => now()->addDays(8)->toDateString(),
        ]);

        $evaluation = ClientEvaluation::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('client.avaliacoes.email', $evaluation))
            ->assertRedirect(route('client.avaliacoes.show', $evaluation));

        Mail::assertSent(ClientEvaluationInvitationMail::class, fn (ClientEvaluationInvitationMail $mail): bool => $mail->hasTo('ana@dona.test'));
    }

    private function company(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'contact_email' => 'contato@empresa.test',
            'is_active' => true,
        ]);
    }
}
