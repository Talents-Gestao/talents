<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Ferias\FeriasCompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeriasCompanySelected
{
    public function __construct(
        private FeriasCompanyContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->needsCompanySelection($request)) {
            return redirect()
                ->route('admin.ferias.index')
                ->with('info', 'Selecione uma empresa para continuar.');
        }

        return $next($request);
    }
}
