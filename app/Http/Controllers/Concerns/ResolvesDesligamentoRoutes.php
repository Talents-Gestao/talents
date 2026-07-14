<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

trait ResolvesDesligamentoRoutes
{
    protected function desligamentoRouteName(string $suffix): string
    {
        $prefix = request()->routeIs('admin.desligamento.*') ? 'admin.desligamento' : 'client.desligamento';

        return "{$prefix}.{$suffix}";
    }

    /**
     * @param  mixed  $parameters
     */
    protected function desligamentoRedirect(string $suffix, mixed $parameters = [], ?string $message = null): RedirectResponse
    {
        if ($suffix === 'index' && request()->routeIs('admin.desligamento.*')) {
            $redirect = redirect()->route('admin.survey-templates.index');
        } else {
            $redirect = redirect()->route($this->desligamentoRouteName($suffix), $parameters);
        }

        if ($message !== null) {
            $redirect->with('success', $message);
        }

        return $redirect;
    }
}
