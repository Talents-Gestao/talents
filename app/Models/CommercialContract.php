<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialContract extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'zapsign_sent_at' => 'datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(CommercialProposal::class, 'proposal_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommercialContractTemplate::class, 'template_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Documento já enviado à ZapSign (token ou data de envio).
     */
    public function wasSentToZapSign(): bool
    {
        return filled($this->zapsign_document_token) || $this->zapsign_sent_at !== null;
    }

    /**
     * Assinatura concluída segundo o status guardado na ZapSign.
     * Nota: sem webhook, o status só muda se for atualizado manualmente/via API.
     */
    public function isZapSignSigned(): bool
    {
        $status = mb_strtolower(trim((string) ($this->zapsign_status ?? '')));

        return in_array($status, [
            'signed',
            'assinado',
            'completed',
            'concluido',
            'concluído',
        ], true);
    }

    public function zapSignStatusLabel(): string
    {
        if ($this->isZapSignSigned()) {
            return 'Assinado';
        }

        if ($this->wasSentToZapSign()) {
            return 'Enviado (aguardando assinatura)';
        }

        return 'PDF gerado';
    }

    /**
     * Código único crescente. Ex.: CONTR-2026-0001.
     */
    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $prefix = "CONTR-{$year}-";

        $last = static::query()
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('code');

        $nextSeq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
