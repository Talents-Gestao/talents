<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialProposal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'employee_count' => 'integer',
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
            'closed_at' => 'datetime',

            'palestra_event_date' => 'date',
            'palestra_audience_estimate' => 'integer',

            'service_descriptions' => 'array',
            'pdf_optional_sections' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(CommercialContract::class, 'proposal_id');
    }

    public function catalogLines(): HasMany
    {
        return $this->hasMany(CommercialProposalProductLine::class, 'commercial_proposal_id');
    }

    public function sale(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CommercialSale::class, 'proposal_id');
    }

    public function isWon(): bool
    {
        return (bool) $this->is_closed;
    }

    public function hasLegacyServices(): bool
    {
        return (bool) $this->svc_pesquisas
            || (bool) $this->svc_profiler
            || filled($this->svc_devolutiva)
            || (bool) $this->svc_nr1
            || (bool) $this->svc_contratacao
            || (bool) $this->svc_direcionamento
            || (bool) $this->svc_palestras;
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
