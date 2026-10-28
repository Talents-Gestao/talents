<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\MfaException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mfa\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reset da verificação em duas etapas de outro usuário: só o proprietário Talents; registra a auditoria e avisa o
 * usuário por e-mail.
 */
class MfaResetController extends Controller
{
    public function __construct(private readonly MfaService $mfa) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        try {
            $this->mfa->resetByOwner($request->user(), $user, $request->ip());
        } catch (MfaException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Verificação em duas etapas resetada. O usuário foi avisado por e-mail e fará nova inscrição.');
    }
}
