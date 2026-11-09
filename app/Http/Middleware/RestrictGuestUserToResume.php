<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictGuestUserToResume
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('tamu')) {
            return $next($request);
        }

        if ($request->routeIs('resume.*', 'resume2.*')) {
            return $next($request);
        }

        return redirect()
            ->route('resume.index')
            ->with('error', 'Akun tamu hanya dapat mengakses menu Resume.');
    }
}
