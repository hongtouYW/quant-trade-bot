<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class SidebarComposerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('layouts.partials.sidebar', function ($view) {
            $viewableModules = collect();
            if (Auth::check()) {
                $user = Auth::user();
                $viewableModules = $user->getViewableModules();
            }
            $view->with('allowedModules', $viewableModules);
        });
    }
}
