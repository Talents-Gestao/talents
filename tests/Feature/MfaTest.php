<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\MfaException;
use App\Mail\MfaResetMail;
use App\Mail\MfaSecurityMail;
use App\Models\ApproverOrganization;
use App\Models\MfaAuditEvent;
use App\Models\User;
use App\Models\UserMfaCredential;
use App\Models\UserMfaRecoveryCode;
use App\Services\Mfa\MfaService;
use App\Services\Psychosocial\ReviewRoundService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use LogicException;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Verificação em duas etapas (TOTP): inscrição, código, recuperação, bloqueio por tentativas, falha ao decifrar, step-up
 * nas ações sensíveis do NR-1, obrigatoriedade para o revisor externo e reset (por proprietário e por comando).
 */
class MfaTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private Google2FA $totp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // O limitador de tentativas usa o cache; aqui ele roda em memória (o tempo do teste avança com `travel`, o Redis não).
        config(['cache.default' => 'array']);
        app()->forgetInstance('cache');
        app()->forgetInstance('cache.store');
        app()->forgetInstance(RateLimiter::class);
        Facade::clearResolvedInstances();
        Cache::flush();
        $this->totp = new Google2FA;
    }

    private function service(): MfaService
    {
        return app(MfaService::class);
    }

    private function request(string $ip = '127.0.0.1'): Request
    {
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => $ip]);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    /** Segredo em claro do usuário (só no teste). */
    private function secret(User $user): string
    {
        return UserMfaCredential::query()->where('user_id', $user->id)->firstOrFail()->plainSecret();
    }

    private function otp(User $user, int $stepOffset = 0): string
    {
        return $this->totp->oathTotp($this->secret($user), $this->totp->getTimestamp() + $stepOffset);
    }

    /** @return list<string> */
    private function enroll(User $user, ?Request $request = null): array
    {
        $request ??= $this->request();
        $this->service()->beginEnrollment($user);

        return $this->service()->confirmEnrollment($user, $this->otp($user), $request);
    }

    public function test_the_default_of_the_factory_is_unchanged_and_the_state_is_explicit(): void
    {
        $plain = User::factory()->talentsOwner()->create();
        $mfa = User::factory()->talentsOwner()->mfaVerified()->create();

        $this->assertSame('none', $this->service()->status($plain));
        $this->assertSame('active', $this->service()->status($mfa));
        $this->assertSame(0, UserMfaCredential::query()->where('user_id', $plain->id)->count());
    }

    public function test_enrollment_confirms_with_a_valid_code_and_returns_ten_recovery_codes_only_hashed(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $data = $this->service()->beginEnrollment($user);

        $this->assertSame('pending', $this->service()->status($user));
        $this->assertStringStartsWith('otpauth://totp/', $data['otpauth_url']);
        $this->assertStringContainsString('<svg', $data['qr_svg']);
        $this->assertSame($data['secret'], $this->secret($user));

        // Código errado não confirma.
        try {
            $this->service()->confirmEnrollment($user, '000000', $this->request());
            $this->fail('Código errado confirmou a inscrição.');
        } catch (MfaException) {
        }
        $this->assertSame('pending', $this->service()->status($user));

        $codes = $this->service()->confirmEnrollment($user, $this->otp($user), $this->request());

        $this->assertSame('active', $this->service()->status($user));
        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        $stored = UserMfaRecoveryCode::query()->where('user_id', $user->id)->pluck('code_hash')->all();
        $this->assertCount(10, $stored);
        foreach ($codes as $code) {
            $this->assertNotContains($code, $stored);
            $this->assertNotContains(str_replace('-', '', $code), $stored);
            $this->assertContains(UserMfaRecoveryCode::hashFor($code), $stored);
        }
        $this->assertSame('enrolled', MfaAuditEvent::query()->where('target_id', $user->id)->sole()->action);
    }

    public function test_the_secret_is_encrypted_at_rest_and_never_exposed(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->service()->beginEnrollment($user);
        $secret = $this->secret($user);

        $raw = DB::table('user_mfa_credentials')->where('user_id', $user->id)->value('secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, (string) $raw);
        $this->assertArrayNotHasKey('secret', UserMfaCredential::query()->firstOrFail()->toArray());
    }

    public function test_a_valid_code_passes_a_wrong_one_does_not_and_the_window_is_one_step(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->enroll($user);
        $request = $this->request();
        $this->service()->clearVerified($request);

        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attempt($user, '123456', $request));
        $this->assertFalse($this->service()->isVerified($request));

        // ±1 intervalo é aceito; ±2 não.
        $user2 = User::factory()->talentsOwner()->create();
        $this->enroll($user2);
        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attempt($user2, $this->otp($user2, -2), $this->request()));
        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attempt($user2, $this->otp($user2, 2), $this->request()));
        $this->assertSame(MfaService::RESULT_OK, $this->service()->attempt($user2, $this->otp($user2, 1), $this->request()));
    }

    public function test_a_code_already_used_is_never_valid_again_and_older_steps_neither(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->enroll($user); // a confirmação já consumiu o código atual
        $request = $this->request();

        $current = $this->otp($user);
        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attempt($user, $current, $request), 'O código usado na inscrição não vale de novo.');
        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attempt($user, $this->otp($user, -1), $request), 'Intervalo mais antigo que o último usado não vale.');

        $next = $this->otp($user, 1);
        $this->assertSame(MfaService::RESULT_OK, $this->service()->attempt($user, $next, $request));
        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attempt($user, $next, $request), 'O mesmo código não passa duas vezes.');
        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attempt($user, $current, $request));
    }

    public function test_attempts_are_limited_per_user_and_the_lockout_blocks_even_the_right_code(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->enroll($user);
        $max = (int) config('mfa.max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attempt($user, '000000', $this->request()));
        }

        $this->assertSame(MfaService::RESULT_LOCKED, $this->service()->attempt($user, $this->otp($user, 1), $this->request()));
        $this->assertSame(MfaService::RESULT_LOCKED, $this->service()->attemptRecovery($user, 'AAAAA-BBBBB', $this->request()));

        // O bloqueio é temporário.
        $this->travel((int) config('mfa.lockout_minutes') + 1)->minutes();
        $this->assertSame(MfaService::RESULT_OK, $this->service()->attempt($user, $this->otp($user, 1), $this->request()));
    }

    public function test_attempts_are_limited_per_ip_across_users(): void
    {
        $a = User::factory()->talentsOwner()->create();
        $b = User::factory()->talentsOwner()->create();
        $this->enroll($a);
        $this->enroll($b);

        for ($i = 0; $i < (int) config('mfa.max_attempts'); $i++) {
            $this->service()->attempt($a, '000000', $this->request('10.9.9.9'));
        }

        // Outro usuário, mesmo IP: bloqueado. Mesmo usuário, outro IP: bloqueado pelo usuário. Usuário e IP novos: livre.
        $this->assertSame(MfaService::RESULT_LOCKED, $this->service()->attempt($b, $this->otp($b, 1), $this->request('10.9.9.9')));
        $this->assertSame(MfaService::RESULT_LOCKED, $this->service()->attempt($a, $this->otp($a, 1), $this->request('10.8.8.8')));
        $this->assertSame(MfaService::RESULT_OK, $this->service()->attempt($b, $this->otp($b, 1), $this->request('10.8.8.8')));
    }

    public function test_when_the_secret_cannot_be_decrypted_it_fails_safe_and_asks_for_a_new_enrollment(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->enroll($user);
        $secret = $this->secret($user);
        DB::table('user_mfa_credentials')->where('user_id', $user->id)->update(['secret' => 'conteudo-que-nao-decifra']);

        $this->assertSame('broken', $this->service()->status($user));
        $this->assertFalse($this->service()->isActive($user));
        $request = $this->request();
        $this->service()->clearVerified($request);
        $this->assertSame(MfaService::RESULT_NOT_ENROLLED, $this->service()->attempt($user, $this->totp->oathTotp($secret, $this->totp->getTimestamp() + 1), $request));
        $this->assertFalse($this->service()->isVerified($request));

        // Rotas protegidas mandam para a inscrição (nunca liberam), mesmo com a sessão marcada como verificada.
        $campaign = $this->v11Campaign(false);
        $this->actingAsMfa($user)->post(route('admin.companies.surveys.internal-review.store', [$campaign->company->id, $campaign->survey->id]), ['decision' => 'suficiente'])
            ->assertRedirect(route('mfa.enroll'));

        // A nova inscrição substitui o segredo quebrado.
        $this->service()->beginEnrollment($user);
        $this->assertSame('pending', $this->service()->status($user));
    }

    public function test_recovery_codes_work_once_and_new_codes_invalidate_the_old_ones(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $codes = $this->enroll($user);
        $request = $this->request();

        $this->assertSame(MfaService::RESULT_OK, $this->service()->attemptRecovery($user, strtolower($codes[0]), $request), 'Maiúsculas e hífen não importam.');
        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attemptRecovery($user, $codes[0], $request), 'Código de recuperação é de uso único.');
        $this->assertSame(9, $this->service()->remainingRecoveryCodes($user));
        $this->assertSame('recovery_used', MfaAuditEvent::query()->where('action', 'recovery_used')->sole()->action);

        $new = $this->service()->regenerateRecoveryCodes($user, $request);
        $this->assertCount(10, $new);
        $this->assertSame(MfaService::RESULT_INVALID, $this->service()->attemptRecovery($user, $codes[1], $request), 'Os antigos deixam de valer.');
        $this->assertSame(MfaService::RESULT_OK, $this->service()->attemptRecovery($user, $new[0], $request));
        $this->assertSame(10, UserMfaRecoveryCode::query()->where('user_id', $user->id)->count());
    }

    public function test_recovery_codes_are_shown_once_over_http(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->actingAs($user)->get(route('mfa.enroll'))->assertOk();
        $code = $this->otp($user);

        $this->post(route('mfa.enroll.confirm'), ['code' => $code])->assertRedirect(route('mfa.codes'));
        $this->get(route('mfa.codes'))->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Mfa/RecoveryCodes')->has('codes', 10));
        $this->get(route('mfa.codes'))->assertRedirect(route('mfa.settings'));
    }

    public function test_the_challenge_over_http_logs_attempts_and_redirects_to_where_the_user_was_going(): void
    {
        $user = User::factory()->talentsOwner()->mfaVerified()->create();
        $campaign = $this->v11Campaign(false);
        $url = route('admin.companies.surveys.internal-review.store', [$campaign->company->id, $campaign->survey->id]);

        // Sem verificação: o passo adicional leva ao desafio e guarda o destino.
        $this->actingAs($user)->post($url, ['decision' => 'suficiente'])->assertRedirect(route('mfa.challenge'));
        $this->get(route('mfa.challenge'))->assertOk();
        $this->post(route('mfa.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('mfa.verify'), ['code' => $this->otp($user)])->assertRedirect();
        $this->assertTrue(session()->has(MfaService::SESSION_VERIFIED_AT));
    }

    public function test_security_events_mail_the_user_without_leaking_codes(): void
    {
        Mail::fake();
        $user = User::factory()->talentsOwner()->create();

        $codes = $this->enroll($user);
        Mail::assertSent(MfaSecurityMail::class, fn ($mail) => $mail->event === 'enrolled' && $mail->hasTo($user->email));

        $this->service()->attemptRecovery($user, $codes[0], $this->request('10.1.1.1'));
        Mail::assertSent(MfaSecurityMail::class, fn ($mail) => $mail->event === 'recovery_used' && $mail->hasTo($user->email) && $mail->ip === '10.1.1.1');

        $new = $this->service()->regenerateRecoveryCodes($user, $this->request());
        Mail::assertSent(MfaSecurityMail::class, fn ($mail) => $mail->event === 'codes_regenerated' && $mail->hasTo($user->email));
        Mail::assertSent(MfaSecurityMail::class, 3);

        $body = (new MfaSecurityMail($user, 'recovery_used', '10.1.1.1', '01/01/2026 10:00'))->render();
        $this->assertStringContainsString('10.1.1.1', $body);
        $this->assertStringNotContainsString($codes[1], $body);
        $this->assertStringNotContainsString($new[0], $body);

        // Falha de envio não derruba a operação.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp fora'));
        $this->assertCount(10, $this->service()->regenerateRecoveryCodes($user, $this->request()));
    }

    public function test_a_wrong_recovery_code_and_a_failed_check_send_no_security_mail(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->enroll($user);
        Mail::fake();

        $this->service()->attemptRecovery($user, 'ZZZZZ-ZZZZZ', $this->request());
        $this->service()->attempt($user, '000000', $this->request());

        Mail::assertNothingSent();
    }

    public function test_the_limiter_fails_closed_when_the_cache_is_unavailable(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $codes = $this->enroll($user);
        $valid = $this->otp($user, 1);
        session()->forget(MfaService::SESSION_VERIFIED_AT); // a inscrição acima já marcou a sessão

        // Cache indisponível: toda operação do limitador lança, como o Redis fora do ar.
        $broken = new class(Cache::store()) extends RateLimiter
        {
            public function tooManyAttempts($key, $maxAttempts)
            {
                throw new \RuntimeException('Connection refused');
            }

            public function hit($key, $decaySeconds = 60)
            {
                throw new \RuntimeException('Connection refused');
            }

            public function availableIn($key)
            {
                throw new \RuntimeException('Connection refused');
            }

            public function clear($key)
            {
                throw new \RuntimeException('Connection refused');
            }
        };
        app()->instance(RateLimiter::class, $broken);
        Facade::clearResolvedInstances();

        // Código certo e código de recuperação certo: negados (nem liberados, nem sem limite).
        $this->assertSame(MfaService::RESULT_LOCKED, $this->service()->attempt($user, $valid, $this->request()));
        $this->assertSame(MfaService::RESULT_LOCKED, $this->service()->attemptRecovery($user, $codes[0], $this->request()));
        $this->assertSame(10, $this->service()->remainingRecoveryCodes($user), 'O código de recuperação não foi consumido.');
        $this->assertNotNull($this->service()->lockedMessage($user, $this->request()));

        // Pela web: o desafio com o código certo não marca a sessão como verificada (o próprio throttle da rota também falha).
        $response = $this->actingAs($user)->post(route('mfa.verify'), ['code' => $valid]);
        $this->assertTrue($response->status() >= 500 || $response->getSession()->has('errors'));
        $this->assertFalse(session()->has(MfaService::SESSION_VERIFIED_AT));

        // Inscrição nova também não confirma.
        $other = User::factory()->talentsOwner()->create();
        $this->service()->beginEnrollment($other);
        try {
            $this->service()->confirmEnrollment($other, $this->otp($other), $this->request());
            $this->fail('A inscrição não pode confirmar com o limitador fora do ar.');
        } catch (MfaException) {
        }
        $this->assertSame('pending', $this->service()->status($other));
    }

    public function test_only_the_owner_resets_another_users_mfa_and_it_is_audited_and_mailed(): void
    {
        Mail::fake();
        $owner = User::factory()->talentsOwner()->create();
        $target = User::factory()->superAdmin()->create(['is_owner' => false]);
        $other = User::factory()->superAdmin()->create(['is_owner' => false]);
        $this->enroll($target);

        // Sem is_owner: recusa, nada muda.
        $this->actingAs($other)->post(route('admin.users.mfa.reset', $target->id))->assertForbidden();
        try {
            $this->service()->resetByOwner($other, $target, '127.0.0.1');
            $this->fail('Reset sem is_owner.');
        } catch (MfaException) {
        }
        $this->assertSame('active', $this->service()->status($target));
        Mail::assertNotSent(MfaResetMail::class);

        // Proprietário não reseta o próprio.
        $this->actingAs($owner)->post(route('admin.users.mfa.reset', $owner->id))->assertSessionHas('error');

        $this->actingAs($owner)->post(route('admin.users.mfa.reset', $target->id))->assertSessionHas('success');
        $this->assertSame('none', $this->service()->status($target));
        $this->assertSame(0, UserMfaRecoveryCode::query()->where('user_id', $target->id)->count());
        $event = MfaAuditEvent::query()->where('action', 'reset_by_owner')->sole();
        $this->assertSame([$owner->id, $target->id], [$event->actor_id, $event->target_id]);
        $this->assertNotNull($event->created_at);
        Mail::assertSent(MfaResetMail::class, fn ($mail) => $mail->hasTo($target->email));
    }

    public function test_the_emergency_command_resets_audits_and_mails(): void
    {
        Mail::fake();
        $user = User::factory()->superAdmin()->create(['is_owner' => false]);
        $this->enroll($user);

        $this->artisan('mfa:reset', ['email' => strtoupper($user->email)])->assertSuccessful();

        $this->assertSame('none', $this->service()->status($user));
        $event = MfaAuditEvent::query()->where('action', 'reset_by_command')->sole();
        $this->assertNull($event->actor_id);
        $this->assertSame($user->id, $event->target_id);
        Mail::assertSent(MfaResetMail::class, fn ($mail) => $mail->hasTo($user->email));

        $this->artisan('mfa:reset', ['email' => 'ninguem@example.com'])->assertFailed();
    }

    public function test_the_audit_log_is_append_only(): void
    {
        $user = User::factory()->talentsOwner()->create();
        $this->enroll($user);
        $event = MfaAuditEvent::query()->firstOrFail();

        foreach ([fn () => $event->fill(['action' => 'x'])->save(), fn () => $event->delete()] as $mutation) {
            try {
                $mutation();
                $this->fail('Auditoria foi alterada.');
            } catch (LogicException) {
            }
        }

        $this->assertSame('enrolled', $event->fresh()->action);
        $this->assertSame(1, MfaAuditEvent::query()->count());
    }

    public function test_the_user_disables_optional_mfa_but_not_when_it_is_the_reviewers(): void
    {
        $admin = User::factory()->talentsOwner()->create();
        $this->enroll($admin);
        $request = $this->request();
        $this->service()->disable($admin, $request);
        $this->assertSame('none', $this->service()->status($admin));

        $reviewer = User::factory()->externalReviewer(ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true])->id)->create();
        $this->enroll($reviewer);
        try {
            $this->service()->disable($reviewer, $this->request());
            $this->fail('O revisor desativou o MFA.');
        } catch (MfaException) {
        }
        $this->assertSame('active', $this->service()->status($reviewer));
    }

    public function test_sensitive_nr1_actions_need_a_recent_mfa_and_the_rest_of_the_platform_does_not(): void
    {
        $campaign = $this->v11Campaign(true);
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => now()])->save();
        $company = $campaign->company->id;
        $survey = $campaign->survey->id;
        $owner = User::factory()->talentsOwner()->create();
        $round = app(ReviewRoundService::class)->start($campaign->survey->fresh(), $owner);
        $sensitive = [
            ['post', route('admin.companies.surveys.internal-review.store', [$company, $survey]), ['decision' => 'suficiente']],
            ['get', route('admin.companies.surveys.raw-export.csv', [$company, $survey]), []],
            ['post', route('admin.companies.surveys.documents.publish', [$company, $survey, $round->id]), []],
        ];

        // Super admin que NÃO se inscreveu: as ações sensíveis levam à inscrição; o resto da plataforma segue livre.
        foreach ($sensitive as [$method, $url, $data]) {
            $this->actingAs($owner)->{$method}($url, $data)->assertRedirect(route('mfa.enroll'));
        }
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('admin.companies.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.companies.surveys.results.show', [$company, $survey]))->assertOk();

        // Inscrito, sem verificar nesta sessão: desafio.
        $enrolled = User::factory()->talentsOwner()->mfaVerified()->create();
        foreach ($sensitive as [$method, $url, $data]) {
            $this->actingAs($enrolled)->{$method}($url, $data)->assertRedirect(route('mfa.challenge'));
        }

        // Verificado agora: passa (a ação responde com a sua própria regra, nunca com o redirecionamento de MFA).
        foreach ($sensitive as [$method, $url, $data]) {
            $response = $this->actingAsMfa($enrolled)->{$method}($url, $data);
            $this->assertNotContains($response->headers->get('Location'), [route('mfa.challenge'), route('mfa.enroll')]);
        }

        // A verificação vale por `mfa.step_up_minutes`; depois, pede de novo.
        $this->travel((int) config('mfa.step_up_minutes') + 1)->minutes();
        $this->actingAs($enrolled)->withSession([MfaService::SESSION_VERIFIED_AT => now()->subMinutes((int) config('mfa.step_up_minutes') + 1)->getTimestamp()])
            ->post($sensitive[0][1], $sensitive[0][2])->assertRedirect(route('mfa.challenge'));
    }

    public function test_step_up_can_be_switched_off_by_configuration(): void
    {
        config(['mfa.step_up_required' => false]);
        $owner = User::factory()->talentsOwner()->create();
        $campaign = $this->v11Campaign(false);

        $response = $this->actingAs($owner)->post(route('admin.companies.surveys.internal-review.store', [$campaign->company->id, $campaign->survey->id]), ['decision' => 'suficiente']);
        $this->assertNotContains($response->headers->get('Location'), [route('mfa.challenge'), route('mfa.enroll')]);
    }

    public function test_general_mfa_at_login_is_off_by_default_and_on_by_configuration(): void
    {
        $this->assertFalse(config('mfa.required_at_login'));
        $owner = User::factory()->talentsOwner()->create();
        $this->actingAs($owner)->get(route('admin.companies.index'))->assertOk();

        config(['mfa.required_at_login' => true]);
        $this->actingAs($owner)->get(route('admin.companies.index'))->assertRedirect(route('mfa.enroll'));

        $enrolled = User::factory()->talentsOwner()->mfaVerified()->create();
        $this->actingAs($enrolled)->get(route('admin.companies.index'))->assertRedirect(route('mfa.challenge'));
        $this->actingAsMfa($enrolled)->get(route('admin.companies.index'))->assertOk();
    }

    public function test_external_reviewer_needs_mfa_in_the_review_area_and_logs_in_through_it(): void
    {
        config(['nr1.external_review.enabled' => true, 'nr1.external_review.mfa_required' => true]);
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        $reviewer = User::factory()->externalReviewer($organization->id)->create(['password' => bcrypt('Senha-Forte-123')]);

        // Login: o revisor cai direto na inscrição (ainda sem MFA).
        $this->post(route('login'), ['email' => $reviewer->email, 'password' => 'Senha-Forte-123'])->assertRedirect(route('mfa.enroll'));
        $this->get(route('external-review.index'))->assertRedirect(route('mfa.enroll'));

        // Inscreveu e verificou: entra.
        $this->enroll($reviewer);
        $this->actingAsMfa($reviewer)->get(route('external-review.index'))->assertOk();

        // Nova sessão: o desafio vem antes da área.
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $reviewer->email, 'password' => 'Senha-Forte-123'])->assertRedirect(route('mfa.challenge'));
        $this->get(route('external-review.index'))->assertRedirect(route('mfa.challenge'));
        $this->post(route('mfa.verify'), ['code' => $this->otp($reviewer, 1)])->assertRedirect(route('external-review.index'));
        $this->get(route('external-review.index'))->assertOk();
    }

    public function test_external_review_stays_off_by_default_and_without_mfa_flag_it_does_not_ask(): void
    {
        $organization = ApproverOrganization::query()->create(['name' => 'Org', 'is_active' => true]);
        $reviewer = User::factory()->externalReviewer($organization->id)->create();

        // Desligada por padrão: a área não existe.
        $this->assertFalse(config('nr1.external_review.enabled'));
        $this->actingAs($reviewer)->get(route('external-review.index'))->assertNotFound();

        // Ligada, com a exigência de MFA desligada por configuração: não pede.
        config(['nr1.external_review.enabled' => true, 'nr1.external_review.mfa_required' => false]);
        $this->actingAs($reviewer)->get(route('external-review.index'))->assertOk();
    }

    public function test_guests_and_other_profiles_cannot_use_the_reset_or_the_pages(): void
    {
        $target = User::factory()->superAdmin()->create(['is_owner' => false]);
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->post(route('admin.users.mfa.reset', $target->id))->assertRedirect(route('login'));
        $this->get(route('mfa.settings'))->assertRedirect(route('login'));
        $this->get(route('mfa.challenge'))->assertRedirect(route('login'));
        $this->actingAs($companyAdmin)->post(route('admin.users.mfa.reset', $target->id))->assertForbidden();
    }
}
