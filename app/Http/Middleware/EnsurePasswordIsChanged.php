<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wer mit einem vorläufigen Passwort angemeldet ist, muss zuerst ein eigenes
 * festlegen, bevor irgendetwas anderes geht.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password
            && ! $request->routeIs('password.change', 'password.change.update', 'logout')) {
            return redirect()->guest(route('password.change'));
        }

        return $next($request);
    }
}
