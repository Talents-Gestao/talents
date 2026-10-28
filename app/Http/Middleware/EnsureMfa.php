<?php

namespace App\Http\Middleware;

use App\Services\Mfa\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige a marca de MFA verificado na sessão.
 *  - `mfa:login`  : MFA concluído nesta sessão (revisor externo; super_admin quando `mfa.required_at_login`).
 *  - `mfa:stepup` : MFA concluído há pouco (`mfa.step_up_minutes`), para as ações sensíveis do NR-1. Quem não se
 *                   inscreveu é levado à inscrição: só estas ações pedem isso, o resto da plataforma não trava.
 * Sem inscrição ativa (inclusive segredo que não decifra) nunca passa.
 */
class EnsureMfa
{
    public function __construct(private readonly MfaService $mfa) {}

    public function handle(Request $request, Closure $next, string $mode = 'stepup'): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($mode === 'stepup' && ! config('mfa.step_up_required')) {
            return $next($request);
        }

        $minutes = $mode === 'stepup' ? (int) config('mfa.step_up_minutes') : null;

        if ($this->mfa->isActive($user) && $this->mfa->isVerified($request, $minutes)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(Response::HTTP_LOCKED, 'Verificação em duas etapas necessária.');
        }

        $request->session()->put('mfa_intended', $request->isMethod('GET') ? $request->fullUrl() : url()->previous());

        if ($this->mfa->isActive($user)) {
            return redirect()->route('mfa.challenge');
        }

        return redirect()->route('mfa.enroll')->with('error', 'Para esta ação, ative a verificação em duas etapas na sua conta.');
    }
}
