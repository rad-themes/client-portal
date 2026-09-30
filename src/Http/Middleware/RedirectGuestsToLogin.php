<?php

namespace RadThemes\ClientPortal\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\Response;

class RedirectGuestsToLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! User::current()) {
            return redirect()->guest(route('client-portal.login'));
        }

        return $next($request);
    }
}
