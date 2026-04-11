<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class LocalizationController extends Controller
{
    public function setLang($locale)
    {
        $supportedLocales = config('languages.supported');
        if (in_array($locale, $supportedLocales)) {
            Session::put('locale', $locale);
            App::setLocale($locale);
            // \Log::info('LocalizationController: Set locale to - ' . $locale);
        } else {
            // \Log::warning('LocalizationController: Invalid locale attempted - ' . $locale);
        }
        return redirect()->back();
    }
}
