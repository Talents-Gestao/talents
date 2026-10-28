<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApproverOrganization;
use App\Models\User;
use App\Services\Mfa\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Telas da verificação em duas etapas: inscrição (QR e chave), código, recuperação, gestão da conta e o reset pelo
 * proprietário (usuários e revisores).
 */
class MfaScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');
        app()->forgetInstance(\Illuminate\Cache\RateLimiter::class);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        Cache::flush();
    }

    public function test_enrollment_page_shows_the_qr_and_the_key_and_the_step_up_message(): void
    {
        $user = User::factory()->talentsOwner()->create();

        $this->actingAs($user)->withSession(['error' => 'x'])->get(route('mfa.enroll'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Mfa/Enroll')
            ->where('reviewer', false)
            ->whereType('secret', 'string')
            ->where('qrSvg', fn ($svg) => str_contains((string) $svg, '<svg')));

        // Recarregar a página mostra o MESMO segredo (a inscrição segue pendente).
        $first = $this->actingAs($user)->get(route('mfa.enroll'))->viewData('page')['props']['secret'];
        $second = $this->actingAs($user)->get(route('mfa.enroll'))->viewData('page')['props']['secret'];
        $this->assertSame($first, $second);
    }

    public function test_reviewer_enrollment_says_it_is_mandatory(): void
    {
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        $reviewer = User::factory()->externalReviewer($organization->id)->create();

        $this->actingAs($reviewer)->get(route('mfa.enroll'))->assertInertia(fn (Assert $page) => $page->where('reviewer', true));
    }

    public function test_challenge_and_settings_pages(): void
    {
        $user = User::factory()->talentsOwner()->mfaVerified()->create();

        $this->actingAs($user)->get(route('mfa.challenge'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Mfa/Challenge')->where('remainingCodes', 0)->where('reviewer', false));
        $this->actingAs($user)->get(route('mfa.settings'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Mfa/Settings')->where('active', true)->where('status', 'active')->where('verifiedRecently', false)->where('reviewer', false));

        // Sem inscrição: a tela de desafio manda para a inscrição.
        $none = User::factory()->talentsOwner()->create();
        $this->actingAs($none)->get(route('mfa.challenge'))->assertRedirect(route('mfa.enroll'));
        $this->actingAs($none)->get(route('mfa.settings'))->assertInertia(fn (Assert $page) => $page->where('active', false)->where('status', 'none'));
    }

    public function test_settings_regenerate_and_disable_ask_for_a_recent_verification(): void
    {
        $user = User::factory()->talentsOwner()->mfaVerified()->create();

        $this->actingAs($user)->post(route('mfa.codes.regenerate'))->assertRedirect(route('mfa.challenge'));
        $this->post(route('mfa.disable'))->assertRedirect(route('mfa.challenge'));
        $this->assertTrue(app(MfaService::class)->isActive($user));

        // Verificado: gera 10 códigos novos (mostrados uma vez) e depois desativa.
        $this->actingAsMfa($user)->post(route('mfa.codes.regenerate'))->assertRedirect(route('mfa.codes'));
        $this->get(route('mfa.codes'))->assertInertia(fn (Assert $page) => $page->component('Auth/Mfa/RecoveryCodes')->has('codes', 10));
        $this->post(route('mfa.disable'))->assertRedirect(route('mfa.settings'));
        $this->assertFalse(app(MfaService::class)->isActive($user));
    }

    public function test_the_user_form_offers_the_reset_only_to_the_owner_and_not_on_himself(): void
    {
        $owner = User::factory()->talentsOwner()->create();
        $target = User::factory()->superAdmin()->mfaVerified()->create(['is_owner' => false]);
        $plain = User::factory()->superAdmin()->create(['is_owner' => false]);

        $this->actingAs($owner)->get(route('admin.users.edit', $target->id))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('mfa.active', true)->where('mfa.can_reset', true));
        $this->actingAs($owner)->get(route('admin.users.edit', $owner->id))->assertInertia(fn (Assert $page) => $page
            ->where('mfa.can_reset', false));
    }

    public function test_the_reviewers_page_shows_mfa_state_and_the_reset_for_the_owner(): void
    {
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        User::factory()->externalReviewer($organization->id)->mfaVerified()->create(['name' => 'Com MFA']);
        User::factory()->externalReviewer($organization->id)->create(['name' => 'Sem MFA']);

        $this->actingAs(User::factory()->talentsOwner()->create())->get(route('admin.external-reviewers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('canResetMfa', true)
            ->where('reviewers', fn ($rows) => collect($rows)->pluck('mfa_active', 'name')->all() === ['Com MFA' => true, 'Sem MFA' => false]));
    }

    public function test_enrolling_over_http_with_a_real_code_ends_verified_and_continues_to_the_destination(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->actingAs($user)->withSession(['mfa_intended' => '/admin'])->get(route('mfa.enroll'));
        $secret = app(MfaService::class)->pendingEnrollment($user)['secret'];

        $this->post(route('mfa.enroll.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(route('mfa.codes'));
        $this->get(route('mfa.codes'))->assertInertia(fn (Assert $page) => $page->where('continueUrl', '/admin')->has('codes', 10));
        $this->assertTrue(app(MfaService::class)->isActive($user));
        $this->assertTrue(session()->has(MfaService::SESSION_VERIFIED_AT));
    }
}
