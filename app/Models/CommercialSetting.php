<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'profiler_tier1_max' => 'integer',
            'profiler_tier1_cents' => 'integer',
            'profiler_tier2_max' => 'integer',
            'profiler_tier2_cents' => 'integer',
            'profiler_tier3_max' => 'integer',
            'profiler_tier3_cents' => 'integer',
            'profiler_tier4_cents' => 'integer',

            'pesquisas_tier1_max' => 'integer',
            'pesquisas_tier1_cents' => 'integer',
            'pesquisas_tier2_max' => 'integer',
            'pesquisas_tier2_cents' => 'integer',
            'pesquisas_tier3_max' => 'integer',
            'pesquisas_tier3_cents' => 'integer',
            'pesquisas_tier4_cents' => 'integer',

            'direcionamento_tier1_max' => 'integer',
            'direcionamento_tier1_cents' => 'integer',
            'direcionamento_tier2_max' => 'integer',
            'direcionamento_tier2_cents' => 'integer',
            'direcionamento_tier3_max' => 'integer',
            'direcionamento_tier3_cents' => 'integer',
            'direcionamento_tier4_cents' => 'integer',

            'nr1_tier1_max' => 'integer',
            'nr1_tier1_cents' => 'integer',
            'nr1_tier2_max' => 'integer',
            'nr1_tier2_cents' => 'integer',
            'nr1_tier3_max' => 'integer',
            'nr1_tier3_cents' => 'integer',
            'nr1_tier4_cents' => 'integer',

            'devolutiva_individual_cents' => 'integer',
            'devolutiva_grupo_cents' => 'integer',

            'nr1_implantacao_online_cents' => 'integer',
            'nr1_implantacao_presencial_cents' => 'integer',

            'palestras_base_cents' => 'integer',
            'palestras_threshold_funcionarios' => 'integer',
            'palestras_multiplier' => 'integer',

            'pdf_validade_dias' => 'integer',

            'default_prazo_dias' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Singleton: cria automaticamente se ainda não existir.
     */
    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();

        return $row ?: static::query()->create([]);
    }
}
