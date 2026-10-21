<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Catálogo de métodos de cálculo NR-1. Linhas são imutáveis: regra nova = código novo.
 */
class SurveyMethodVersion extends Model
{
    public const CODE_PRODUCTION_V1 = 'production_v1';

    public const CODE_TALENTS39_V1 = 'talents39_v1';

    public const UPDATED_AT = null;

    protected $fillable = [
        'code',
        'label',
        'aggregation',
        'rounding_mode',
        'rounding_scale',
        'band_basis',
        'green_max',
        'yellow_max',
        'weighting',
        'min_item_coverage',
        'min_group_size_new_outputs',
        'manual_reference',
    ];

    protected function casts(): array
    {
        return [
            'rounding_scale' => 'integer',
            'green_max' => 'float',
            'yellow_max' => 'float',
            'min_item_coverage' => 'float',
            'min_group_size_new_outputs' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Versões de método são imutáveis. Crie um código novo.');
        });

        static::deleting(function (): never {
            throw new LogicException('Versões de método não podem ser apagadas.');
        });
    }

    public static function idForCode(string $code): int
    {
        $id = static::query()->where('code', $code)->value('id');

        if ($id === null) {
            throw new LogicException("Versão de método «{$code}» não encontrada no catálogo.");
        }

        return (int) $id;
    }
}
