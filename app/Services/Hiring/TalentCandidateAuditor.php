<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\TalentCandidateAuditAction;
use App\Models\TalentCandidate;
use App\Models\TalentCandidateAuditLog;

/** Grava a auditoria do candidato (só inserção; meta nunca leva conteúdo de arquivo). */
final class TalentCandidateAuditor
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        TalentCandidate $candidate,
        TalentCandidateAuditAction $action,
        ?int $userId,
        array $meta = [],
        ?string $ip = null,
    ): TalentCandidateAuditLog {
        return TalentCandidateAuditLog::query()->create([
            'talent_candidate_id' => $candidate->id,
            'user_id' => $userId,
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
            'ip' => $ip ?? request()?->ip(),
        ]);
    }
}
