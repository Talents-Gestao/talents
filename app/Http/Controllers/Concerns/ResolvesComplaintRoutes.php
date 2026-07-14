<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

trait ResolvesComplaintRoutes
{
    protected function complaintRouteName(string $suffix): string
    {
        $prefix = request()->routeIs('admin.complaints.*') ? 'admin.complaints' : 'client.complaints';

        return "{$prefix}.{$suffix}";
    }

    /**
     * @param  mixed  $parameters  ID, modelo ou array de parâmetros para `route()`.
     */
    protected function complaintRedirect(string $suffix, mixed $parameters = [], ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->route($this->complaintRouteName($suffix), $parameters);

        if ($message !== null) {
            $redirect->with('success', $message);
        }

        return $redirect;
    }
}
