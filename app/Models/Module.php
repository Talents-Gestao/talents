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

    /** @var string Calendário estratégico (eventos e Rituais Talents) */
    public const KEY_CALENDARIO_ESTRATEGICO = 'calendario_estrategico';

    /** @var string Quadros Kanban / Tarefas */
    public const KEY_TAREFAS = 'tarefas';

    /** @var string Integração RHID / ponto eletrônico Control iD */
    public const KEY_RHID = 'rhid';

    /** @var string Canal de denúncias (Lei 14.457/2022) */
    public const KEY_DENUNCIAS = 'denuncias';

    /** @var string Feedbacks internos líder ↔ colaborador */
    public const KEY_FEEDBACKS = 'feedbacks';

    /** @var string Gestão de férias dos colaboradores */
    public const KEY_FERIAS = 'ferias';

    /** @var string Pesquisa / entrevista de desligamento */
    public const KEY_DESLIGAMENTO = 'desligamento';

    /** @var string Acompanhamento visual das fases de contratação */
    public const KEY_ACOMPANHAMENTO = 'acompanhamento';

    protected $fillable = ['key', 'name', 'description'];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)->withTimestamps();
    }
}
