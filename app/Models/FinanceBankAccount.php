<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinanceBankAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceBankAccount extends Model
{
    protected $fillable = [
        'name',
        'bank_name',
        'agency',
        'account_number',
        'type',
        'initial_balance_cents',
        'initial_balance_at',
        'is_active',
        'sort_order',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => FinanceBankAccountType::class,
            'initial_balance_cents' => 'integer',
            'initial_balance_at' => 'date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(FinanceReceivable::class, 'bank_account_id');
    }

    public function payables(): HasMany
    {
        return $this->hasMany(FinancePayable::class, 'bank_account_id');
    }

    public function saleInstallments(): HasMany
    {
        return $this->hasMany(CommercialSaleInstallment::class, 'bank_account_id');
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(FinanceBankTransfer::class, 'from_bank_account_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(FinanceBankTransfer::class, 'to_bank_account_id');
    }
}
