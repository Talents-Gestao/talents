<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    /** @var string Módulo base NR-1 (pesquisas, denúncias, estrutura, etc.) */
    public const KEY_NR1 = 'nr1';

    /** @var string Chave usada em planos e em Company::hasMethodologyEnabled() */
    public const KEY_METODOLOGIA = 'metodologia';

    /** @var string Calendário estratégico (eventos e ritos Talents) */
    public const KEY_CALENDARIO_ESTRATEGICO = 'calendario_estrategico';

    protected $fillable = ['key', 'name', 'description'];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)->withTimestamps();
    }
}
