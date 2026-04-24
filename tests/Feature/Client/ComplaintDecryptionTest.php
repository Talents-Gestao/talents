<?php

namespace Tests\Feature\Client;

use App\Models\Company;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComplaintDecryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_complaint_show_returns_placeholder_when_encrypted_payloads_invalid(): void
    {
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
            'description' => 'Descricao valida inicial',
            'status' => 'new',
            'is_anonymous' => false,
            'reporter_name' => 'Nome',
            'reporter_email' => 'rep@test.com',
        ]);

        $msg = ComplaintMessage::query()->create([
            'complaint_id' => $complaint->id,
            'author_type' => ComplaintMessage::AUTHOR_SYSTEM,
            'user_id' => null,
            'content' => 'Mensagem ok',
        ]);

        DB::table('complaints')->where('id', $complaint->id)->update([
            'description' => 'invalid-ciphertext',
            'reporter_name' => 'invalid-ciphertext',
            'reporter_email' => 'invalid-ciphertext',
        ]);

        DB::table('complaint_messages')->where('id', $msg->id)->update([
            'content' => 'invalid-ciphertext',
        ]);

        $placeholder = Complaint::UNREADABLE_ENCRYPTED_PLACEHOLDER;

        $this->actingAs($user)
            ->get('/client/complaints/'.$complaint->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Complaints/Show')
                ->where('complaint.description', $placeholder)
                ->where('complaint.reporter_name', $placeholder)
                ->where('complaint.reporter_email', $placeholder)
                ->where('complaint.messages.0.content', $placeholder));
    }
}
