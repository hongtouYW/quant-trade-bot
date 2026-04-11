<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            Log::channel('api_error')->error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
        });

        $this->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = 500;

                if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                    $status = 404;
                    $message = '资源不存在';
                } elseif ($e instanceof ValidationException) {
                    return response()->json([
                        'error' => true,
                        'message' => $e->validator->errors()->first(),
                    ], 422);
                } else {
                    $message = $e->getMessage() ?: '服务器错误';
                }

                return response()->json([
                    'error' => true,
                    'message' => $message,
                ], $status);
            }

            if (!($e instanceof ValidationException)) {
                if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                    return response()->view('errors.404', [], 404);
                }

                if ($request->hasSession() && url()->previous() && url()->previous() !== url()->current()) {
                    return redirect()->back()->withInput()->withErrors([
                        'msg' => $e->getMessage() ?: '操作失败，请重试',
                    ]);
                }

                return redirect()->route('login')->withErrors([
                    'msg' => $e->getMessage() ?: '操作失败，请重试',
                ]);
            }
        });
    }
}
