<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccessMasterAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user(); // ✅ web guard

        if (!$user || $user->user_role !== 'masteradmin') {
            abort(403, 'Only master admin can perform this action.');
        }

        return $next($request);
    }
}

