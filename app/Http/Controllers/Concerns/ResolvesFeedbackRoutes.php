<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

trait ResolvesFeedbackRoutes
{
    protected function feedbackRouteName(string $suffix): string
    {
        $prefix = request()->routeIs('admin.feedbacks.*') ? 'admin.feedbacks' : 'client.feedbacks';

        return "{$prefix}.{$suffix}";
    }

    /**
     * @param  mixed  $parameters  ID, modelo ou array de parâmetros para `route()`.
     */
    protected function feedbackRedirect(string $suffix, mixed $parameters = [], ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->route($this->feedbackRouteName($suffix), $parameters);

        if ($message !== null) {
            $redirect->with('success', $message);
        }

        return $redirect;
    }
}
