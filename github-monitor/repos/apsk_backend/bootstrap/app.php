<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\AccessMasterAdmin;
use App\Http\Middleware\AdminIframeAuth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Append to 'web' group
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Register aliases
        $middleware->alias([
            'masteradmin' => AccessMasterAdmin::class,
            'admin.iframe.auth' => AdminIframeAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            // API / AJAX / JSON requests / Middleware
            if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
                return sendEncryptedJsonResponse(
                    [
                        'status'  => false,
                        'message' => __('messages.Unauthorized'),
                        'error'   => $e->getMessage(),
                    ],
                    Response::HTTP_UNAUTHORIZED // 401
                );
            }

            // Iframe requests → force top redirect
            $isIframe = $request->headers->get('Sec-Fetch-Dest') === 'iframe'
                || $request->headers->get('X-Frame-Requested') === 'true';

            if ($isIframe) {
                return response(
                    "<script>window.top.location.href = '".route('login')."'</script>",
                    Response::HTTP_UNAUTHORIZED
                )->header('Content-Type', 'text/html');
            }

            // Normal web requests → redirect to login
            return redirect()->guest(route('login'));
        });
    })
    ->create();
