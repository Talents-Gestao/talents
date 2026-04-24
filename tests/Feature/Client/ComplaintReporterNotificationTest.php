<?php

namespace Tests\Feature\Client;

use App\Mail\ComplaintReporterNotificationMail;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComplaintReporterNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_mail_when_company_updates_status_for_identified_reporter(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '11.111.111/0001-11',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $this->subscribeCompanyToNr1($company);

        $user = User::factory()->companyAdmin($company->id)->create();

        $protocol = (string) Str::uuid();

        $complaint = Complaint::query()->create([
            'company_id' => $company->id,
            'protocol' => $protocol,
            'category' => 'outros',
            'description' => 'Descrição com pelo menos vinte caracteres aqui.',
            'status' => 'new',
            'is_anonymous' => false,
            'reporter_name' => 'Nome',
            'reporter_email' => 'reporter@example.com',
        ]);

        $this->actingAs($user)
            ->patch('/client/complaints/'.$complaint->id.'/status', [
                'status' => 'under_review',
            ])
            ->assertRedirect();

        Mail::assertSent(ComplaintReporterNotificationMail::class, function (ComplaintReporterNotificationMail $mail) {
            return $mail->eventType === 'status'
                && ($mail->meta['status'] ?? null) === 'under_review';
        });
    }

    public function test_does_not_send_mail_when_status_unchanged(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '22.222.222/0001-22',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $this->subscribeCompanyToNr1($company);

        $user = User::factory()->companyAdmin($company->id)->create();

        $protocol = (string) Str::uuid();

        $complaint = Complaint::query()->create([
            'company_id' => $company->id,
            'protocol' => $protocol,
            'category' => 'outros',
            'description' => 'Descrição com pelo menos vinte caracteres aqui.',
            'status' => 'new',
            'is_anonymous' => false,
            'reporter_name' => 'Nome',
            'reporter_email' => 'reporter@example.com',
        ]);

        $this->actingAs($user)
            ->patch('/client/complaints/'.$complaint->id.'/status', [
                'status' => 'new',
            ])
            ->assertRedirect();

        Mail::assertNothingSent();
    }

    public function test_sends_mail_when_company_posts_message_for_identified_reporter(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $this->subscribeCompanyToNr1($company);

        $user = User::factory()->companyAdmin($company->id)->create();

        $protocol = (string) Str::uuid();

        $complaint = Complaint::query()->create([
            'company_id' => $company->id,
            'protocol' => $protocol,
            'category' => 'outros',
            'description' => 'Descrição com pelo menos vinte caracteres aqui.',
            'status' => 'new',
            'is_anonymous' => false,
            'reporter_name' => 'Nome',
            'reporter_email' => 'reporter@example.com',
        ]);

        $this->actingAs($user)
            ->post('/client/complaints/'.$complaint->id.'/messages', [
                'content' => 'Resposta da empresa ao denunciante.',
            ])
            ->assertRedirect();

        Mail::assertSent(ComplaintReporterNotificationMail::class, function (ComplaintReporterNotificationMail $mail) {
            return $mail->eventType === 'message';
        });
    }

    public function test_does_not_send_mail_for_anonymous_complaint(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Empresa Teste',
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);

        $this->subscribeCompanyToNr1($company);

        $user = User::factory()->companyAdmin($company->id)->create();

        $protocol = (string) Str::uuid();

        $complaint = Complaint::query()->create([
            'company_id' => $company->id,
            'protocol' => $protocol,
            'category' => 'outros',
            'description' => 'Descrição com pelo menos vinte caracteres aqui.',
            'status' => 'new',
            'is_anonymous' => true,
            'reporter_name' => null,
            'reporter_email' => null,
        ]);

        $this->actingAs($user)
            ->patch('/client/complaints/'.$complaint->id.'/status', [
                'status' => 'under_review',
            ])
            ->assertRedirect();

        Mail::assertNothingSent();
    }
}
