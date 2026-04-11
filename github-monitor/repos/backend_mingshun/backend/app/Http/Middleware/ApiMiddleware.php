<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $privateKey = config('app.scrap_private_key');
        $publicKey = config('app.scrap_public_key');
        
        $timestamp = $request->get('timestamp');
        $requestPublicKey = $request->get('public_key');
        $hash = $request->get('hash');

        if (!isset($privateKey) || !isset($timestamp) || !isset($publicKey) || !isset($hash) || !isset($requestPublicKey)) {
            return response()->json([
                'message' => '验证参数不能为空',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Reject requests with timestamps older than 5 minutes to prevent replay attacks
        if (abs(time() - (int)$timestamp) > 300) {
            return response()->json([
                'message' => '请求已过期',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($publicKey !== $requestPublicKey) {
            return response()->json([
                'message' => '验证错误',
            ], Response::HTTP_UNAUTHORIZED);
        }
        $md5Hash = md5($publicKey . $privateKey . $timestamp);

        if (!hash_equals($md5Hash, $hash)) {
            return response()->json([
                'message' => '验证错误',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
