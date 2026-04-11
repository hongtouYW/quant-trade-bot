<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Check if the user is authenticated
        if (Auth::check()) {
            // Check if the user's role matches any of the allowed roles
            if (Auth::user()->checkUserRole($roles)) {
                return $next($request);
            }
        }

        // Redirect or show an error message for unauthorized access
        return redirect('/dashboard')->withErrors([
            'msg' => '用户无权限，请咨询主管',
        ]);
    }
}
