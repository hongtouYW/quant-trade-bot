<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminIframeAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {

            // API / AJAX / JSON Middleware
            if ($request->expectsJson()) {
                return sendEncryptedJsonResponse(
                    [
                        'status'  => false,
                        'message' => __('messages.Unauthorized'),
                        'error'   => __('messages.Unauthorized'),
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // ✅ REAL iframe detection (AdminLTE)
            $isIframe = $request->headers->get('Sec-Fetch-Dest') === 'iframe';
            if ($isIframe) {
                return response()->json([
                    'expired' => true,
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
