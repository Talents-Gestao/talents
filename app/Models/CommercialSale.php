<?php

namespace App\Models;

use App\Actions\Commercial\RefreshCommissionPayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class CommercialSale extends Model
{
    public const STATUS_ABERTA = 'aberta';

    public const STATUS_PARCIAL = 'parcial';

    public const STATUS_QUITADA = 'quitada';

    public const STATUS_CANCELADA = 'cancelada';

    protected $guarded = ['id'];

    /**
     * Teto das comissões acompanha o valor da venda (cancelar/reativar/editar parcela),
     * venha a mudança de onde vier.
     */
    protected static function booted(): void
    {
        static::updated(function (self $sale): void {
            if ($sale->wasChanged('total_cents')) {
                app(RefreshCommissionPayout::class)->forSale($sale);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'commission_percent' => 'float',
            'commission_cents' => 'integer',
            'installments_count' => 'integer',
            'is_recurring' => 'boolean',
            'recurring_months' => 'integer',
            'recurring_monthly_cents' => 'integer',
            'sold_at' => 'datetime',
        ];
    }

    public function isRecurringBilling(): bool
    {
        return (bool) $this->is_recurring
            && (int) $this->recurring_months > 0;
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(CommercialProposal::class, 'proposal_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CommercialSaleInstallment::class, 'sale_id')->orderBy('number');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(CommercialCommission::class, 'sale_id')->oldest('id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CommercialCommission::class, 'sale_id');
    }

    public function recalculateStatus(): void
    {
        if ($this->status === self::STATUS_CANCELADA) {
            return;
        }

        $newStatus = self::statusFromInstallments($this->installments()->get());
        if ($newStatus === null) {
            return;
        }

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }

    /**
     * Status derivado das parcelas. Parcelas canceladas não entram no total nem no pago.
     *
     * Todas canceladas: «aberta» (nada recebido nem a receber; nunca «quitada»).
     * Não vira «cancelada» sozinha: esse status é terminal e não volta a ser recalculado.
     * Sem parcelas: null (mantém o status atual).
     *
     * @param  Collection<int, CommercialSaleInstallment>  $installments
     */
    public static function statusFromInstallments(Collection $installments): ?string
    {
        if ($installments->isEmpty()) {
            return null;
        }

        $active = $installments->where('status', '!=', CommercialSaleInstallment::STATUS_CANCELADO);
        $paidCount = $active->where('status', CommercialSaleInstallment::STATUS_PAGO)->count();

        return match (true) {
            $paidCount === 0 => self::STATUS_ABERTA,
            $paidCount >= $active->count() => self::STATUS_QUITADA,
            default => self::STATUS_PARCIAL,
        };
    }

    /**
     * Valor da venda = soma das parcelas não canceladas.
     *
     * @param  Collection<int, CommercialSaleInstallment>  $installments
     */
    public static function totalFromInstallments(Collection $installments): int
    {
        return (int) $installments
            ->where('status', '!=', CommercialSaleInstallment::STATUS_CANCELADO)
            ->sum('amount_cents');
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $prefix = "VENDA-{$year}-";

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
