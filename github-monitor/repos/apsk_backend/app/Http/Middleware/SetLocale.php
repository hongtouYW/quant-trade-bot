<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;

class SetLocale
{
/**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    // public function handle(Request $request, Closure $next)
    // {
    //     $currentLocale = Session::has('locale') ? Session::get('locale') : config('app.locale');
    //     App::setLocale($currentLocale);
    //     return $next($request);
    // }
    public function handle(Request $request, Closure $next)
    {
        $supportedLocales = config('languages.supported');
        $defaultLocale = config('app.locale');

        $locale = null;

        // 1. Check for locale in request parameter (e.g., /api/areas/list?lang=zh)
        if ($request->has('lang') && in_array($request->input('lang'), $supportedLocales)) {
            $locale = $request->input('lang');
            Session::put('locale', $locale);
        }
        // 2. Check for locale in session
        elseif (Session::has('locale') && in_array(Session::get('locale'), $supportedLocales)) {
            $locale = Session::get('locale');
        }
        // 3. Check for locale in Accept-Language header
        elseif ($request->hasHeader('Accept-Language')) {
            $headerLocale = substr($request->header('Accept-Language'), 0, 2); // Get first two characters (e.g., 'en', 'zh')
            if (in_array($headerLocale, $supportedLocales)) {
                $locale = $headerLocale;
            }
        }
        // 4. Fallback to application default if no specific locale found
        if (!$locale) {
            $locale = $defaultLocale;
        }
        App::setLocale($locale);
        return $next($request);
    }



}
