<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PsychosocialReviewRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Cabeçalho do shell NR-1: estado com tom (Publicado verde, Em revisão âmbar, Em edição cinza), empresa em destaque,
 * colaborador sem nome da empresa, seletor de campanha acessível e AA.
 */
class Nr1ShellHeaderTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['nr1.shell_enabled' => true]);
    }

    private function badge(User $user, array $query = []): array
    {
        return $this->actingAs($user)->get(route('nr1.visao-geral', $query))->assertOk()->viewData('page')['props']['context']['survey']['statusBadge'];
    }

    public function test_the_status_chip_follows_the_publication_and_review_state_with_a_tone(): void
    {
        $c = $this->v11Campaign(true);
        $owner = User::factory()->talentsOwner()->create();
        $q = ['empresa' => $c->company->id, 'campanha' => $c->survey->id];

        // Exige publicação, sem rodada: Em edição (cinza).
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => null])->save();
        $this->assertSame(['label' => 'Em edição', 'tone' => 'gray'], $this->badge($owner, $q));

        // Rodada enviada à PASSEG: Em revisão (âmbar).
        $round = PsychosocialReviewRound::query()->create(['survey_id' => $c->survey->id, 'company_id' => $c->company->id, 'round_number' => 1, 'status' => PsychosocialReviewRound::STATUS_DRAFT]);
        // O model protege as transições; o teste só precisa do estado, então grava direto.
        DB::table('psychosocial_review_rounds')->where('id', $round->id)->update(['status' => PsychosocialReviewRound::STATUS_IN_REVIEW]);
        $this->assertSame(['label' => 'Em revisão', 'tone' => 'warn'], $this->badge($owner, $q));
        DB::table('psychosocial_review_rounds')->where('id', $round->id)->update(['status' => PsychosocialReviewRound::STATUS_DRAFT]);
        $this->assertSame(['label' => 'Em edição', 'tone' => 'gray'], $this->badge($owner, $q));

        // Publicada: Publicado (verde).
        $c->survey->forceFill(['published_at' => now()])->save();
        $this->assertSame(['label' => 'Publicado', 'tone' => 'ok'], $this->badge($owner, $q));

        // Sem exigência de publicação: Ativa (verde) ou Encerrada (cinza).
        $c->survey->forceFill(['requires_publication' => false, 'published_at' => null, 'status' => 'active'])->save();
        $this->assertSame(['label' => 'Ativa', 'tone' => 'ok'], $this->badge($owner, $q));
        $c->survey->forceFill(['status' => 'closed'])->save();
        $this->assertSame(['label' => 'Encerrada', 'tone' => 'gray'], $this->badge($owner, $q));
    }

    public function test_the_header_payload_has_the_company_for_staff_and_none_for_the_collaborator(): void
    {
        $c = $this->v11Campaign(true);
        $c->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $owner = User::factory()->talentsOwner()->create();

        $this->actingAs($owner)->get(route('nr1.visao-geral'))->assertInertia(fn (Assert $p) => $p
            ->where('context.company.name', $c->company->name)->has('context.company.logo_url')->where('shell.backLabel', '← Voltar à empresa'));

        $page = $this->get(route('nr1.jornada', $c->survey->public_token))->assertOk();
        $props = $page->viewData('page')['props'];
        $this->assertNull($props['context']['company']);
        $this->assertStringNotContainsString($c->company->name, (string) json_encode($props));
    }

    public function test_the_header_components_follow_the_brief_and_accessibility_rules(): void
    {
        $header = (string) file_get_contents(resource_path('js/Components/Nr1Shell/ShellHeader.vue'));
        $select = (string) file_get_contents(resource_path('js/Components/Nr1Shell/CampaignSelect.vue'));
        $chip = (string) file_get_contents(resource_path('js/Components/Nr1Shell/StatusChip.vue'));
        $css = (string) file_get_contents(resource_path('css/nr1-shell-header.css'));

        // Botões só pelas variantes do Nr1Button; sem <button> solto no cabeçalho.
        $this->assertStringContainsString('variant="ghost-on-light"', $header);
        $this->assertStringNotContainsString('<button', $header);
        $this->assertStringContainsString('<Nr1Button', $select);
        // Colaborador não vê a empresa; título truncado com tooltip.
        $this->assertStringContainsString('context.collaborator', $header);
        $this->assertStringContainsString(':title="title"', $header);
        // Seletor: listbox, opção marcada, teclado e rótulo.
        foreach (['role="listbox"', 'role="option"', ':aria-selected', 'ArrowDown', 'Escape', 'aria-haspopup="listbox"', 'Campanha'] as $needle) {
            $this->assertStringContainsString($needle, $select);
        }
        // Chip com ícone e texto (nunca só cor).
        $this->assertStringContainsString('CheckCircleIcon', $chip);
        $this->assertStringContainsString('role="status"', $chip);
        // Visual: faixa de 3px, nome de 22 a 26px em 800, reticências e prefers-reduced-motion.
        $this->assertStringContainsString('height: 3px', $css);
        $this->assertStringContainsString('font-size: 24px', $css);
        $this->assertStringContainsString('font-weight: 800', $css);
        $this->assertStringContainsString('text-overflow: ellipsis', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }
}
