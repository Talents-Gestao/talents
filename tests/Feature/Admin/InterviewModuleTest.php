<?php

namespace Tests\Feature\Admin;

use App\Models\InterviewQuestionnaire;
use App\Models\User;
use Database\Seeders\InterviewQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_interviews_index(): void
    {
        $this->get(route('admin.entrevistas.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_access_interviews_index(): void
    {
        $admin = User::factory()->talentsOwner()->create();

        $this->actingAs($admin)
            ->get(route('admin.entrevistas.index'))
            ->assertOk();
    }

    public function test_questionnaire_seeder_creates_default_roteiro(): void
    {
        $this->seed(InterviewQuestionnaireSeeder::class);

        $questionnaire = InterviewQuestionnaire::query()->where('is_default', true)->first();

        $this->assertNotNull($questionnaire);
        $this->assertSame('Entrevista RH — Padrão Talents', $questionnaire->name);
        $this->assertSame(7, $questionnaire->sections()->count());
        $this->assertGreaterThan(30, $questionnaire->sections()->withCount('questions')->get()->sum('questions_count'));
    }
}
