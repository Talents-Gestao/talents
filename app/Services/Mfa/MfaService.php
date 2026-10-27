<?php

declare(strict_types=1);

namespace App\Services\Mfa;

use App\Enums\UserRole;
use App\Exceptions\MfaException;
use App\Mail\MfaResetMail;
use App\Models\MfaAuditEvent;
use App\Models\User;
use App\Models\UserMfaCredential;
use App\Models\UserMfaRecoveryCode;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Verificação em duas etapas (TOTP) com a biblioteca pragmarx/google2fa: sem criptografia própria.
 * Regras: ±1 intervalo de tolerância; um código já usado não vale de novo (último intervalo guardado); 10 códigos de
 * recuperação de uso único, só com hash; novos códigos invalidam os antigos; segredo cifrado com a APP_KEY (se não
 * decifrar, exige nova inscrição: falha segura); limite de tentativas por usuário e por IP com bloqueio temporário.
 */
class MfaService
{
    public const SESSION_VERIFIED_AT = 'mfa_verified_at';

    public const RESULT_OK = 'ok';

    public const RESULT_INVALID = 'invalid';

    public const RESULT_LOCKED = 'locked';

    public const RESULT_NOT_ENROLLED = 'not_enrolled';

    public function __construct(private readonly Google2FA $totp) {}

    /* ----- estado ----- */

    /**
     * @return 'none'|'pending'|'active'|'broken'
     */
    public function status(User $user): string
    {
        $credential = UserMfaCredential::query()->where('user_id', $user->id)->first();
        if ($credential === null) {
            return 'none';
        }

        if ($credential->plainSecret() === null) {
            return 'broken';
        }

        return $credential->isConfirmed() ? 'active' : 'pending';
    }

    public function isActive(User $user): bool
    {
        return $this->status($user) === 'active';
    }

    public function isReviewer(User $user): bool
    {
        return $user->role === UserRole::ExternalReviewer;
    }

    /* ----- sessão ----- */

    public function markVerified(Request $request): void
    {
        $request->session()->put(self::SESSION_VERIFIED_AT, now()->getTimestamp());
    }

    public function clearVerified(Request $request): void
    {
        $request->session()->forget(self::SESSION_VERIFIED_AT);
    }

    /** Verificado nesta sessão (e, com $withinMinutes, há pouco tempo). */
    public function isVerified(Request $request, ?int $withinMinutes = null): bool
    {
        $at = $request->session()->get(self::SESSION_VERIFIED_AT);
        if (! is_int($at)) {
            return false;
        }

        return $withinMinutes === null || $at >= now()->subMinutes($withinMinutes)->getTimestamp();
    }

    /* ----- inscrição ----- */

    /**
     * Começa (ou recomeça) a inscrição. Quem já tem MFA ativo precisa desativar ou ser resetado antes.
     *
     * @return array{secret: string, otpauth_url: string, qr_svg: string}
     */
    public function beginEnrollment(User $user): array
    {
        if ($this->status($user) === 'active') {
            throw new MfaException('A verificação em duas etapas já está ativa.');
        }

        $secret = $this->totp->generateSecretKey(32);

        DB::transaction(function () use ($user, $secret): void {
            UserMfaCredential::query()->where('user_id', $user->id)->delete();
            $credential = new UserMfaCredential(['user_id' => $user->id, 'confirmed_at' => null, 'last_used_timestamp' => null]);
            $credential->setPlainSecret($secret);
            $credential->save();
        });

        $url = $this->totp->getQRCodeUrl((string) config('mfa.issuer'), $user->email, $secret);

        return ['secret' => $secret, 'otpauth_url' => $url, 'qr_svg' => $this->qrSvg($url)];
    }

    /**
     * Inscrição ainda não confirmada: devolve o mesmo segredo, para o usuário recarregar a página sem trocar o QR.
     *
     * @return array{secret: string, otpauth_url: string, qr_svg: string}|null
     */
    public function pendingEnrollment(User $user): ?array
    {
        $credential = UserMfaCredential::query()->where('user_id', $user->id)->whereNull('confirmed_at')->first();
        $secret = $credential?->plainSecret();
        if ($secret === null) {
            return null;
        }

        $url = $this->totp->getQRCodeUrl((string) config('mfa.issuer'), $user->email, $secret);

        return ['secret' => $secret, 'otpauth_url' => $url, 'qr_svg' => $this->qrSvg($url)];
    }

    /**
     * Confirma a inscrição com um código do aplicativo e devolve os códigos de recuperação (única vez em claro).
     *
     * @return list<string>
     */
    public function confirmEnrollment(User $user, string $code, Request $request): array
    {
        $credential = UserMfaCredential::query()->where('user_id', $user->id)->first();
        if ($credential === null || $credential->isConfirmed() || $credential->plainSecret() === null) {
            throw new MfaException('Comece a inscrição de novo.');
        }

        $result = $this->check($user, $credential, $code, $request);
        if ($result !== self::RESULT_OK) {
            throw new MfaException($result === self::RESULT_LOCKED ? $this->lockedMessage($user, $request) : 'Código incorreto. Confira o aplicativo e tente de novo.');
        }

        $credential->forceFill(['confirmed_at' => now()])->save();
        $codes = $this->issueRecoveryCodes($user);
        $this->audit('enrolled', $user, $user, $request->ip());
        $this->markVerified($request);

        return $codes;
    }

    /* ----- verificação ----- */

    /**
     * Código do aplicativo. Nunca libera se o segredo não decifra ou se não há inscrição confirmada.
     *
     * @return self::RESULT_*
     */
    public function attempt(User $user, string $code, Request $request): string
    {
        $credential = UserMfaCredential::query()->where('user_id', $user->id)->first();
        if ($credential === null || ! $credential->isConfirmed() || $credential->plainSecret() === null) {
            return self::RESULT_NOT_ENROLLED;
        }

        $result = $this->check($user, $credential, $code, $request);
        if ($result === self::RESULT_OK) {
            $this->markVerified($request);
        }

        return $result;
    }

    /**
     * Código de recuperação (uso único).
     *
     * @return self::RESULT_*
     */
    public function attemptRecovery(User $user, string $code, Request $request): string
    {
        if (! $this->isActive($user)) {
            return self::RESULT_NOT_ENROLLED;
        }

        if ($this->locked($user, $request)) {
            return self::RESULT_LOCKED;
        }

        $consumed = UserMfaRecoveryCode::query()
            ->where('user_id', $user->id)
            ->where('code_hash', UserMfaRecoveryCode::hashFor($code))
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        if ($consumed !== 1) {
            $this->fail($user, $request);

            return self::RESULT_INVALID;
        }

        $this->succeed($user);
        $this->audit('recovery_used', $user, $user, $request->ip());
        $this->markVerified($request);

        return self::RESULT_OK;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return UserMfaRecoveryCode::query()->where('user_id', $user->id)->whereNull('used_at')->count();
    }

    /**
     * Novos códigos de recuperação (os antigos deixam de valer). Exige MFA ativo.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user, Request $request): array
    {
        if (! $this->isActive($user)) {
            throw new MfaException('Ative a verificação em duas etapas primeiro.');
        }

        $codes = $this->issueRecoveryCodes($user);
        $this->audit('codes_regenerated', $user, $user, $request->ip());

        return $codes;
    }

    /** O próprio usuário desativa (não vale para o revisor externo, que é obrigatório). */
    public function disable(User $user, Request $request): void
    {
        if ($this->isReviewer($user)) {
            throw new MfaException('O revisor externo não pode desativar a verificação em duas etapas.');
        }

        $this->wipe($user);
        $this->clearVerified($request);
        $this->audit('disabled', $user, $user, $request->ip());
    }

    /* ----- reset ----- */

    /** Reset feito por outro admin: só o proprietário Talents. Registra e avisa o usuário por e-mail. */
    public function resetByOwner(User $actor, User $target, ?string $ip): void
    {
        if (! $actor->isOwner()) {
            throw new MfaException('Só o proprietário Talents reseta a verificação em duas etapas de outro usuário.');
        }
        if ($actor->is($target)) {
            throw new MfaException('Para o seu próprio acesso, use «Desativar» na sua conta.');
        }

        $this->wipe($target);
        $this->audit('reset_by_owner', $actor, $target, $ip);
        $this->notifyReset($target, 'um administrador Talents');
    }

    /** Reset de emergência pelo comando Artisan (sem ator humano). */
    public function resetByCommand(User $target): void
    {
        $this->wipe($target);
        $this->audit('reset_by_command', null, $target, null);
        $this->notifyReset($target, 'a equipe técnica (comando de emergência)');
    }

    /* ----- internos ----- */

    /**
     * @return self::RESULT_*
     */
    private function check(User $user, UserMfaCredential $credential, string $code, Request $request): string
    {
        if ($this->locked($user, $request)) {
            return self::RESULT_LOCKED;
        }

        $digits = preg_replace('/\D/', '', $code) ?? '';
        $secret = $credential->plainSecret();
        $timestamp = ($secret !== null && strlen($digits) === 6)
            ? $this->totp->verifyKeyNewer($secret, $digits, (int) ($credential->last_used_timestamp ?? 0), (int) config('mfa.window'))
            : false;

        if ($timestamp === false) {
            $this->fail($user, $request);

            return self::RESULT_INVALID;
        }

        // Atualização condicional: duas requisições com o mesmo código não passam as duas.
        $updated = UserMfaCredential::query()
            ->whereKey($credential->id)
            ->where(fn ($q) => $q->whereNull('last_used_timestamp')->orWhere('last_used_timestamp', '<', (int) $timestamp))
            ->update(['last_used_timestamp' => (int) $timestamp]);
        if ($updated !== 1) {
            $this->fail($user, $request);

            return self::RESULT_INVALID;
        }

        $this->succeed($user);

        return self::RESULT_OK;
    }

    private function userKey(User $user): string
    {
        return 'mfa:user:'.$user->id;
    }

    private function ipKey(Request $request): string
    {
        return 'mfa:ip:'.$request->ip();
    }

    private function locked(User $user, Request $request): bool
    {
        $max = (int) config('mfa.max_attempts');

        return RateLimiter::tooManyAttempts($this->userKey($user), $max) || RateLimiter::tooManyAttempts($this->ipKey($request), $max);
    }

    public function lockedMessage(User $user, Request $request): string
    {
        $seconds = max(RateLimiter::availableIn($this->userKey($user)), RateLimiter::availableIn($this->ipKey($request)));

        return 'Muitas tentativas. Tente de novo em '.max(1, (int) ceil($seconds / 60)).' minuto(s).';
    }

    private function fail(User $user, Request $request): void
    {
        $decay = (int) config('mfa.lockout_minutes') * 60;
        RateLimiter::hit($this->userKey($user), $decay);
        RateLimiter::hit($this->ipKey($request), $decay);
    }

    private function succeed(User $user): void
    {
        RateLimiter::clear($this->userKey($user));
    }

    /**
     * @return list<string>
     */
    private function issueRecoveryCodes(User $user): array
    {
        $codes = [];

        DB::transaction(function () use ($user, &$codes): void {
            UserMfaRecoveryCode::query()->where('user_id', $user->id)->delete();

            for ($i = 0; $i < (int) config('mfa.recovery_codes'); $i++) {
                $code = Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5));
                UserMfaRecoveryCode::query()->create(['user_id' => $user->id, 'code_hash' => UserMfaRecoveryCode::hashFor($code)]);
                $codes[] = $code;
            }
        });

        return $codes;
    }

    private function wipe(User $user): void
    {
        DB::transaction(function () use ($user): void {
            UserMfaRecoveryCode::query()->where('user_id', $user->id)->delete();
            UserMfaCredential::query()->where('user_id', $user->id)->delete();
        });
        RateLimiter::clear($this->userKey($user));
    }

    private function audit(string $action, ?User $actor, ?User $target, ?string $ip): void
    {
        MfaAuditEvent::query()->create(['actor_id' => $actor?->id, 'target_id' => $target?->id, 'action' => $action, 'ip' => $ip]);
    }

    private function notifyReset(User $target, string $by): void
    {
        try {
            Mail::to($target->email)->send(new MfaResetMail($target, $by));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function qrSvg(string $url): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($url);
    }
}
