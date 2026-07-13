<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Desligamento\DesligamentoCompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDesligamentoCompanySelected
{
    public function __construct(
        private DesligamentoCompanyContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->needsCompanySelection($request)) {
            return redirect()
                ->route('admin.desligamento.index')
                ->with('info', 'Selecione uma empresa para continuar.');
        }

        return $next($request);
    }
}
