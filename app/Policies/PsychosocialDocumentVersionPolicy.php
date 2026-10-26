<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DocumentShare;
use App\Models\PsychosocialDocumentVersion;
use App\Models\User;
use App\Services\Psychosocial\DocumentShareService;
use Illuminate\Auth\Access\Response;

/**
 * Revisor externo: lê, comenta e aprova só a versão explicitamente compartilhada com ele, dentro da
 * validade, com a organização ativa. Qualquer outro caso nega como 404 (não confirma que a versão existe).
 * Esta policy cobre só o revisor externo; os demais perfis seguem as regras já existentes.
 */
class PsychosocialDocumentVersionPolicy
{
    public function view(User $user, PsychosocialDocumentVersion $version): Response
    {
        return $this->check($user, $version);
    }

    public function comment(User $user, PsychosocialDocumentVersion $version): Response
    {
        return $this->check($user, $version);
    }

    public function approve(User $user, PsychosocialDocumentVersion $version): Response
    {
        return $this->check($user, $version);
    }

    public function activeShare(User $user, PsychosocialDocumentVersion $version): ?DocumentShare
    {
        if (! DocumentShareService::enabled() || $user->contextRole() !== UserRole::ExternalReviewer) {
            return null;
        }

        return DocumentShare::query()
            ->active()
            ->where('reviewer_id', $user->id)
            ->where('document_version_id', $version->id)
            ->whereHas('organization', fn ($q) => $q->where('is_active', true))
            ->first();
    }

    private function check(User $user, PsychosocialDocumentVersion $version): Response
    {
        return $this->activeShare($user, $version) !== null
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
