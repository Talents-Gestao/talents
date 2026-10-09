<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

trait ResolvesSuggestionRoutes
{
    protected function suggestionRouteName(string $suffix): string
    {
        $prefix = request()->routeIs('admin.suggestions.*') ? 'admin.suggestions' : 'client.suggestions';

        return "{$prefix}.{$suffix}";
    }

    /**
     * @param  mixed  $parameters
     */
    protected function suggestionRedirect(string $suffix, mixed $parameters = [], ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->route($this->suggestionRouteName($suffix), $parameters);

        if ($message !== null) {
            $redirect->with('success', $message);
        }

        return $redirect;
    }
}
