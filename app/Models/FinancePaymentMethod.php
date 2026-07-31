<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FinancePaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $method): void {
            if ($method->slug === null || $method->slug === '') {
                $method->slug = Str::slug($method->name);
            }
        });
    }

    public function payables(): HasMany
    {
        return $this->hasMany(FinancePayable::class, 'payment_method_id');
    }
}
