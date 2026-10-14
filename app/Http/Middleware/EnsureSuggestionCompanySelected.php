<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Suggestions\SuggestionCompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuggestionCompanySelected
{
    public function __construct(
        private SuggestionCompanyContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->needsCompanySelection($request)) {
            return redirect()
                ->route('admin.suggestions.index')
                ->with('info', 'Selecione uma empresa para continuar.');
        }

        return $next($request);
    }
}
