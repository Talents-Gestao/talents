<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Support\Leaves\FeriasCompanyContext;
use Illuminate\Http\RedirectResponse;

trait ResolvesFeriasRoutes
{
    protected function feriasRouteName(string $suffix): string
    {
        $prefix = request()->routeIs('admin.ferias.*') ? 'admin.ferias' : 'client.ferias';

        return "{$prefix}.{$suffix}";
    }

    /**
     * @param  mixed  $parameters  ID, modelo ou array de parâmetros para `route()`.
     */
    protected function feriasRedirect(string $suffix, mixed $parameters = [], ?string $message = null): RedirectResponse
    {
        if (request()->routeIs('admin.ferias.*')) {
            $companyId = request()->session()->get(FeriasCompanyContext::SESSION_KEY);

            $redirect = $companyId
                ? redirect()->route('admin.companies.show', [
                    'company' => $companyId,
                    'tab' => 'ferias',
                ])
                : redirect()->route('admin.companies.index');

            if ($message !== null) {
                $redirect->with('success', $message);
            }

            return $redirect;
        }

        $redirect = redirect()->route($this->feriasRouteName($suffix), $parameters);

        if ($message !== null) {
            $redirect->with('success', $message);
        }

        return $redirect;
    }
}
