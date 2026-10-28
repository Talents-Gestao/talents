<?php

namespace App\Http\Middleware;

use App\Support\Nr1Shell\Nr1Shell;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas /nr1: com a flag `nr1.shell_enabled` desligada respondem 404, como se não existissem (o sistema fica como antes).
 */
class EnsureNr1Shell
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Nr1Shell::enabled(), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
