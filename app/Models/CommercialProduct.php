<?php

namespace App\Models;

use App\Enums\CommercialProductPricingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommercialProduct extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'pricing_type',
        'pricing_config',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pricing_config' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'pricing_type' => CommercialProductPricingType::class,
        ];
    }

    public function proposalLines(): HasMany
    {
        return $this->hasMany(CommercialProposalProductLine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'produto';
        }

        $slug = $base;
        $n = 1;
        while (static::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalogArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'pricing_type' => $this->pricing_type->value,
            'pricing_config' => $this->pricing_config ?? [],
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
