<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Complaints\ComplaintCompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureComplaintCompanySelected
{
    public function __construct(
        private ComplaintCompanyContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->needsCompanySelection($request)) {
            return redirect()
                ->route('admin.complaints.index')
                ->with('info', 'Selecione uma empresa para continuar.');
        }

        return $next($request);
    }
}
