<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminDashboardSettings extends Model
{
    protected $table = 'admin_dashboard_settings';

    protected $fillable = [
        'monthly_revenue_goal_cents',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_revenue_goal_cents' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Singleton: cria a linha se ainda não existir.
     */
    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();

        return $row ?: static::query()->create([]);
    }

    /**
     * Meta vigente em centavos: valor na BD se preenchido, senão fallback de config/env.
     */
    public static function resolvedMonthlyRevenueGoalCents(): int
    {
        $row = static::query()->orderBy('id')->first();
        $stored = $row?->monthly_revenue_goal_cents;

        if ($stored !== null && $stored > 0) {
            return (int) $stored;
        }

        return (int) config('talents.dashboard.monthly_revenue_goal_cents', 2_000_000);
    }
}
