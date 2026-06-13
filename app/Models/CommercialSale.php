<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommercialSale extends Model
{
    public const STATUS_ABERTA = 'aberta';

    public const STATUS_PARCIAL = 'parcial';

    public const STATUS_QUITADA = 'quitada';

    public const STATUS_CANCELADA = 'cancelada';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'commission_percent' => 'float',
            'commission_cents' => 'integer',
            'installments_count' => 'integer',
            'sold_at' => 'datetime',
        ];
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
        return $this->hasOne(CommercialCommission::class, 'sale_id');
    }

    public function recalculateStatus(): void
    {
        if ($this->status === self::STATUS_CANCELADA) {
            return;
        }

        $installments = $this->installments()->get();
        if ($installments->isEmpty()) {
            return;
        }

        $paidCount = $installments->where('status', CommercialSaleInstallment::STATUS_PAGO)->count();
        $totalCount = $installments->count();

        $newStatus = match (true) {
            $paidCount === 0 => self::STATUS_ABERTA,
            $paidCount >= $totalCount => self::STATUS_QUITADA,
            default => self::STATUS_PARCIAL,
        };

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
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
