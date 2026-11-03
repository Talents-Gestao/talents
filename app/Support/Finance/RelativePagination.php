<?php

declare(strict_types=1);

namespace App\Support\Finance;

use Illuminate\Http\Request;

/**
 * Caminho relativo (sem esquema/host/porta) para os links do paginador.
 *
 * O paginador do Laravel monta URLs absolutas a partir da requisição; no Docker local
 * o nginx repassa X-Forwarded-Port = porta interna (80), e o link vira
 * http://localhost/... em vez de http://localhost:8080/... — outra origem para o
 * navegador, e o «Próximo» do Inertia falha (Network Error). Mesmo critério do
 * resources/js/plugins/ziggyRelative.js, que já deixa as rotas do front relativas.
 */
final class RelativePagination
{
    public static function path(Request $request): string
    {
        return $request->getBaseUrl().$request->getPathInfo();
    }
}
