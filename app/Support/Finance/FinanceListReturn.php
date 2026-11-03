<?php

declare(strict_types=1);

namespace App\Support\Finance;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Depois de gravar (modal na lista ou página Editar/Novo), volta para a lista como ela
 * estava: busca, aba, origem, ordenação, agrupamento e página.
 *
 * A lista guarda na sessão a própria URI relativa (caminho + query) a cada visita; a
 * gravação redireciona para ela. Relativa de propósito: o host/porta vêm do APP_URL
 * (URL::forceRootUrl), nunca dos headers da requisição.
 */
final class FinanceListReturn
{
    public const RECEIVABLES = 'admin.financeiro.contas-a-receber.index';

    public const PAYABLES = 'admin.financeiro.contas-a-pagar.index';

    public static function remember(Request $request, string $listRoute): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::key($listRoute), $request->getRequestUri());
        }
    }

    public static function redirect(Request $request, string $listRoute): RedirectResponse
    {
        $uri = $request->hasSession() ? $request->session()->get(self::key($listRoute)) : null;
        $path = route($listRoute, [], false);

        // Só aceita a própria lista (evita open redirect por valor adulterado na sessão).
        if (is_string($uri) && ($uri === $path || str_starts_with($uri, $path.'?'))) {
            return redirect()->to($uri);
        }

        return redirect()->route($listRoute);
    }

    private static function key(string $listRoute): string
    {
        return 'finance.list_return.'.$listRoute;
    }
}
