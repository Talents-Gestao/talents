<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\MfaException;
use App\Http\Controllers\Controller;
use App\Models\UserMfaCredential;
use App\Services\Mfa\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inscrição, desafio, recuperação e gestão da verificação em duas etapas (TOTP) do próprio usuário.
 */
class MfaController extends Controller
{
    public function __construct(private readonly MfaService $mfa) {}

    public function enroll(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($this->mfa->isActive($user)) {
            return redirect()->route('mfa.settings');
        }

        // Inscrição em andamento: mostra o mesmo segredo; senão, começa uma nova.
        $pending = $this->mfa->status($user) === 'pending';
        $data = $pending ? $this->mfa->pendingEnrollment($user) : null;
        $data ??= $this->mfa->beginEnrollment($user);

        return Inertia::render('Auth/Mfa/Enroll', [
            'qrSvg' => $data['qr_svg'],
            'secret' => $data['secret'],
            'reviewer' => $this->mfa->isReviewer($user),
            'broken' => false,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        try {
            $codes = $this->mfa->confirmEnrollment($request->user(), $data['code'], $request);
        } catch (MfaException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        $request->session()->flash('mfa_recovery_codes', $codes);

        return redirect()->route('mfa.codes');
    }

    /** Códigos de recuperação: aparecem uma única vez (flash); recarregar a página não os mostra de novo. */
    public function codes(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->pull('mfa_recovery_codes');
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('mfa.settings');
        }

        return Inertia::render('Auth/Mfa/RecoveryCodes', [
            'codes' => $codes,
            'continueUrl' => $request->session()->pull('mfa_intended') ?? route('mfa.settings'),
        ]);
    }

    public function challenge(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $this->mfa->isActive($user)) {
            return redirect()->route('mfa.enroll');
        }

        return Inertia::render('Auth/Mfa/Challenge', [
            'reviewer' => $this->mfa->isReviewer($user),
            'remainingCodes' => $this->mfa->remainingRecoveryCodes($user),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        return $this->finish($request, $this->mfa->attempt($request->user(), $data['code'], $request));
    }

    public function recovery(Request $request): RedirectResponse
    {
        $data = $request->validate(['recovery_code' => ['required', 'string', 'max:32']]);

        return $this->finish($request, $this->mfa->attemptRecovery($request->user(), $data['recovery_code'], $request), 'recovery_code');
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();
        $status = $this->mfa->status($user);

        return Inertia::render('Auth/Mfa/Settings', [
            'status' => $status,
            'active' => $status === 'active',
            'remainingCodes' => $status === 'active' ? $this->mfa->remainingRecoveryCodes($user) : 0,
            'reviewer' => $this->mfa->isReviewer($user),
            'verifiedRecently' => $this->mfa->isVerified($request, (int) config('mfa.step_up_minutes')),
        ]);
    }

    public function regenerateCodes(Request $request): RedirectResponse
    {
        if (! $this->mfa->isVerified($request, (int) config('mfa.step_up_minutes'))) {
            $request->session()->put('mfa_intended', route('mfa.settings'));

            return redirect()->route('mfa.challenge');
        }

        try {
            $codes = $this->mfa->regenerateRecoveryCodes($request->user(), $request);
        } catch (MfaException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $request->session()->flash('mfa_recovery_codes', $codes);

        return redirect()->route('mfa.codes');
    }

    public function disable(Request $request): RedirectResponse
    {
        if (! $this->mfa->isVerified($request, (int) config('mfa.step_up_minutes'))) {
            $request->session()->put('mfa_intended', route('mfa.settings'));

            return redirect()->route('mfa.challenge');
        }

        try {
            $this->mfa->disable($request->user(), $request);
        } catch (MfaException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('mfa.settings')->with('success', 'Verificação em duas etapas desativada.');
    }

    /**
     * @param  'ok'|'invalid'|'locked'|'not_enrolled'  $result
     */
    private function finish(Request $request, string $result, string $field = 'code'): RedirectResponse
    {
        return match ($result) {
            MfaService::RESULT_OK => redirect()->to($request->session()->pull('mfa_intended') ?? $this->home($request)),
            MfaService::RESULT_LOCKED => back()->withErrors([$field => $this->mfa->lockedMessage($request->user(), $request)]),
            MfaService::RESULT_NOT_ENROLLED => redirect()->route('mfa.enroll'),
            default => back()->withErrors([$field => 'Código incorreto.']),
        };
    }

    private function home(Request $request): string
    {
        return $this->mfa->isReviewer($request->user()) ? route('external-review.index') : url('/');
    }
}
