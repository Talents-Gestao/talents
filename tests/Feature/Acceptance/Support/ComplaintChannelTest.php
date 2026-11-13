<?php

// Cartão do quadro SUPORTE: «CANAL DE DENÚNCIAS» (dentro de Voz do Time no admin e no portal; Talents e cliente acessam;
// colaborador denuncia pelo link público sem login).
// Já cobertos: protocolo sequencial (Public/ComplaintProtocolTest), limite de envios (Public/PublicComplaintThrottleTest),
// placeholder se a chave mudar (Client/ComplaintDecryptionTest), visibilidade do menu por permissão
// (Unit/ClientSidebarDenunciasVisibilityTest).

namespace Tests\Feature\Acceptance\Support;

use App\Models\Company;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComplaintChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_reports_through_the_public_link_and_both_company_and_talents_can_read_it(): void
    {
        $this->withoutVite();
        Mail::fake();
        $token = (string) Str::uuid();
        $company = Company::query()->create([
            'name' => 'Empresa Denúncias',
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'complaints_public_token' => $token,
        ]);
        $this->subscribeCompanyToNr1($company);

        // Colaborador, sem login.
        $this->get(route('denuncia.create', ['token' => $token]))->assertOk();
        $this->post(route('denuncia.store', ['token' => $token]), [
            'category' => 'outros',
            'description' => 'Relato de teste de aceitação com mais de vinte caracteres.',
            'is_anonymous' => true,
        ])->assertRedirect();

        $complaint = Complaint::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertNotSame('Relato de teste de aceitação com mais de vinte caracteres.', $complaint->getRawOriginal('description'));

        // Cliente (portal).
        $clientAdmin = User::factory()->companyAdmin($company->id)->create();
        $this->actingAs($clientAdmin)
            ->get(route('client.complaints.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Client/Complaints/Index')
                ->where('complaints.data.0.id', $complaint->id));
        $this->get(route('client.complaints.show', $complaint))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('complaint.description', 'Relato de teste de aceitação com mais de vinte caracteres.'));

        // Talents (admin), escolhendo a empresa.
        $talents = User::factory()->talentsOwner()->create();
        $this->actingAs($talents)
            ->post(route('admin.complaints.company.store'), ['company_id' => $company->id])
            ->assertRedirect();
        $this->get(route('admin.complaints.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('isAdminContext', true)
                ->where('complaints.data.0.id', $complaint->id));
        $this->get(route('admin.complaints.show', $complaint))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('complaint.protocol', $complaint->protocol));
    }

    public function test_the_complaint_channel_menu_lives_inside_voz_do_time_in_admin_and_portal(): void
    {
        foreach ([
            'resources/js/Layouts/AdminLayout.vue' => ['label="Voz do Time"', 'label="Canal de denúncias"', '</SidebarNavGroup>'],
            'resources/js/Layouts/ClientLayout.vue' => ['label="Voz do Time"', 'label="Denúncias"', '</SidebarNavSection>'],
        ] as $file => [$section, $item, $close]) {
            $source = file_get_contents(base_path($file));
            $start = strpos($source, $section);
            $this->assertNotFalse($start, "{$file}: seção Voz do Time não encontrada");
            $end = strpos($source, $close, $start);
            $block = substr($source, $start, $end - $start);
            $this->assertStringContainsString($item, $block, "{$file}: denúncias fora de Voz do Time");
        }
    }
}
