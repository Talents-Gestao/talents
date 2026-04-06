<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRole::CompanyAdmin) {
            abort(Response::HTTP_FORBIDDEN, 'Apenas administradores da empresa podem acessar esta area.');
        }

        return $next($request);
    }
}
