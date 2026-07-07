<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Feedback\FeedbackCompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeedbackCompanySelected
{
    public function __construct(
        private FeedbackCompanyContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->needsCompanySelection($request)) {
            return redirect()
                ->route('admin.feedbacks.index')
                ->with('info', 'Selecione uma empresa para continuar.');
        }

        return $next($request);
    }
}
