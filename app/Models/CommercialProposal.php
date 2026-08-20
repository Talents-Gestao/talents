<?php

namespace App\Models;

use App\Support\Commercial\ProposalListStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommercialProposal extends Model
{
    protected $guarded = ['id'];

    /**
     * Compatível com propostas antigas: secção e permanência no PDF por defeito.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'include_publico_atendido' => true,
        'include_minimum_stay' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (CommercialProposal $proposal): void {
            if (! filled($proposal->list_status)) {
                $proposal->list_status = $proposal->is_closed
                    ? ProposalListStatus::APPROVED
                    : ProposalListStatus::OPEN;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'employee_count' => 'integer',
            'include_publico_atendido' => 'boolean',
            'svc_pesquisas' => 'boolean',
            'svc_profiler' => 'boolean',
            'svc_nr1' => 'boolean',
            'svc_contratacao' => 'boolean',
            'svc_contratacao_salario_cents' => 'integer',
            'svc_direcionamento' => 'boolean',
            'direcionamento_horas' => 'decimal:2',
            'svc_palestras' => 'boolean',

            'total_pesquisas_cents' => 'integer',
            'total_profiler_cents' => 'integer',
            'total_devolutiva_cents' => 'integer',
            'total_nr1_cents' => 'integer',
            'total_nr1_implantacao_cents' => 'integer',
            'total_contratacao_cents' => 'integer',
            'total_direcionamento_cents' => 'integer',
            'total_palestras_cents' => 'integer',
            'total_catalog_products_cents' => 'integer',
            'total_final_cents' => 'integer',

            'commission_percent' => 'float',
            'commission_cents' => 'integer',

            'is_closed' => 'boolean',
            'list_status' => 'string',
            'closed_at' => 'datetime',

            'palestra_event_date' => 'date',
            'palestra_audience_estimate' => 'integer',

            // Snapshot slug (legado); preferir paymentMethod() + payment_method_label.
            'payment_method' => 'string',
            'payment_method_id' => 'integer',
            'include_minimum_stay' => 'boolean',

            'is_recurring' => 'boolean',
            'recurring_months' => 'integer',
            'recurring_monthly_cents' => 'integer',

            'service_descriptions' => 'array',
            'pdf_optional_sections' => 'array',
        ];
    }

    public function isRecurringService(): bool
    {
        return (bool) $this->is_recurring
            && (int) $this->recurring_months > 0
            && (int) $this->recurring_monthly_cents > 0;
    }

    public function recurringPeriodTotalCents(): int
    {
        if (! $this->isRecurringService()) {
            return 0;
        }

        return (int) $this->recurring_months * (int) $this->recurring_monthly_cents;
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentMethod::class, 'payment_method_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(CommercialContract::class, 'proposal_id');
    }

    public function catalogLines(): HasMany
    {
        return $this->hasMany(CommercialProposalProductLine::class, 'commercial_proposal_id');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(CommercialSale::class, 'proposal_id');
    }

    public function isWon(): bool
    {
        return (bool) $this->is_closed;
    }

    /**
     * Nome exibido no PDF/contrato (snapshot; não muda se o método for renomeado depois).
     */
    public function paymentMethodDisplayName(): ?string
    {
        if (filled($this->payment_method_label)) {
            return (string) $this->payment_method_label;
        }

        $related = $this->relationLoaded('paymentMethod')
            ? $this->paymentMethod
            : $this->paymentMethod()->first();

        return $related?->name;
    }

    public function paymentMethodPdfBullet(): ?string
    {
        $name = $this->paymentMethodDisplayName();

        return $name !== null && $name !== '' ? '• Pagamento via '.$name.';' : null;
    }

    public function paymentMethodContractText(): ?string
    {
        $name = $this->paymentMethodDisplayName();

        return $name !== null && $name !== '' ? 'Pagamento via '.$name.'.' : null;
    }

    /**
     * Status da lista: open|negotiation|approved|ended (persistido em list_status).
     */
    public function listStatus(): string
    {
        return ProposalListStatus::for($this);
    }

    public function listStatusLabel(): string
    {
        return ProposalListStatus::labelFor($this);
    }

    public function hasLegacyServices(): bool
    {
        return (bool) $this->svc_pesquisas
            || (bool) $this->svc_profiler
            || filled($this->svc_devolutiva)
            || (bool) $this->svc_nr1
            || filled($this->svc_nr1_implantacao_modo)
            || (bool) $this->svc_contratacao
            || (bool) $this->svc_direcionamento
            || (bool) $this->svc_palestras;
    }

    public function hasContractableServices(): bool
    {
        if ($this->isRecurringService() || $this->hasLegacyServices()) {
            return true;
        }

        $this->loadMissing('catalogLines');

        return $this->catalogLines->contains(function ($line): bool {
            if ((int) $line->total_cents > 0) {
                return true;
            }

            $options = is_array($line->options) ? $line->options : [];

            return ($options['adjustment'] ?? '') === 'bonus'
                && (int) ($options['subtotal_cents'] ?? 0) > 0;
        });
    }

    public function legacyTotalsCents(): int
    {
        return (int) $this->total_pesquisas_cents
            + (int) $this->total_profiler_cents
            + (int) $this->total_devolutiva_cents
            + (int) $this->total_nr1_cents
            + (int) $this->total_nr1_implantacao_cents
            + (int) $this->total_contratacao_cents
            + (int) $this->total_direcionamento_cents
            + (int) $this->total_palestras_cents;
    }

    public function hasSale(): bool
    {
        if ($this->relationLoaded('sale')) {
            return $this->sale !== null;
        }

        return $this->sale()->exists();
    }

    /**
     * Pode voltar a negociar (aprovada/encerrada/fechada), desde que não haja venda.
     */
    public function canReopen(): bool
    {
        if ($this->hasSale()) {
            return false;
        }

        $status = ProposalListStatus::for($this);

        return (bool) $this->is_closed
            || $status === ProposalListStatus::APPROVED
            || $status === ProposalListStatus::ENDED;
    }

    public function hasSignedContract(): bool
    {
        if ($this->relationLoaded('contracts')) {
            return $this->contracts->contains(fn (CommercialContract $c) => $c->isZapSignSigned());
        }

        return $this->contracts()
            ->get(['id', 'zapsign_status', 'zapsign_document_token', 'zapsign_sent_at'])
            ->contains(fn (CommercialContract $c) => $c->isZapSignSigned());
    }

    public function hasZapSignSentContract(): bool
    {
        if ($this->relationLoaded('contracts')) {
            return $this->contracts->contains(fn (CommercialContract $c) => $c->wasSentToZapSign());
        }

        return $this->contracts()
            ->where(function ($q): void {
                $q->whereNotNull('zapsign_document_token')
                    ->orWhereNotNull('zapsign_sent_at');
            })
            ->exists();
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('is_closed', true);
    }

    /**
     * Gera código único e crescente para a proposta. Ex.: PROP-2026-0001.
     */
    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $prefix = "PROP-{$year}-";

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
