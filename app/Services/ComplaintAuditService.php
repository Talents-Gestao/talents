<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class ComplaintAuditService
{
    /**
     * Ações originadas por usuários autenticados (admin/empresa): IP registrado para rastreabilidade interna.
     * Ações originadas pelo denunciante público: IP NÃO registrado para preservar o anonimato e
     * cumprir o princípio de minimização de dados da LGPD (Art. 6º, III).
     *
     * @param  bool  $recordIp  Passar false em ações do denunciante público.
     */
    public static function log(
        Complaint $complaint,
        string $action,
        ?Request $request = null,
        ?User $user = null,
        ?array $meta = null,
        bool $recordIp = true,
    ): void {
        ComplaintAuditLog::create([
            'complaint_id' => $complaint->id,
            'user_id' => $user?->id,
            'action' => $action,
            'meta' => $meta,
            'ip_address' => ($recordIp && $request !== null) ? $request->ip() : null,
        ]);
    }
}
