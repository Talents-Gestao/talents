<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mede o tempo de processamento PHP em visitas Inertia (header X-Inertia).
 * Ativo apenas com APP_DEBUG=true: grava em log e envia X-Inertia-Server-Ms na resposta.
 */
class LogInertiaRequestTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.debug') || ! $request->headers->has('X-Inertia')) {
            return $next($request);
        }

        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $ms = round((microtime(true) - $startedAt) * 1000, 2);

        $response->headers->set('X-Inertia-Server-Ms', (string) $ms);

        Log::debug('[Inertia] servidor (PHP total no pipeline)', [
            'method' => $request->method(),
            'path' => $request->path(),
            'server_ms' => $ms,
        ]);

        return $response;
    }
}
