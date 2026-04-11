<?php

namespace App\Http\Middleware;

use App\Http\Helper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = Helper::getIp();

        Log::channel('receive_api')->info($ip . "--" . json_encode($request->all()));

        return $next($request);
    }
}
