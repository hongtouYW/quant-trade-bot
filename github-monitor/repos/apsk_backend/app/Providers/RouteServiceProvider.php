<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Configure rate limiting (modern approach)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Define routes (modern approach)
        $this->routes(function () {
            // API Routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Web Routes
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Load user API routes
            Route::prefix('api/user')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/user/api.php'));

            // Load member API routes
            Route::prefix('api/member')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/member/api.php'));

            // Load manager API routes
            Route::prefix('api/manager')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/manager/api.php'));

            // Load shop API routes
            Route::prefix('api/shop')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/shop/api.php'));

            // Load country API routes
            Route::prefix('api/country')
                ->namespace($this->namespace)
                ->group(base_path('routes/country/api.php'));

            // Load state API routes
            Route::prefix('api/state')
                ->namespace($this->namespace)
                ->group(base_path('routes/state/api.php'));

            // Load area API routes
            Route::prefix('api/area')
                ->namespace($this->namespace)
                ->group(base_path('routes/area/api.php'));

            // Load bank API routes
            Route::prefix('api/bank')
                ->namespace($this->namespace)
                ->group(base_path('routes/bank/api.php'));

            // Load application API routes
            Route::prefix('api/application')
                ->namespace($this->namespace)
                ->group(base_path('routes/application/api.php'));

            // Load credit API routes
            Route::prefix('api/credit')
                ->namespace($this->namespace)
                ->group(base_path('routes/credit/api.php'));
                
            // Load genre API routes
            Route::prefix('api/genre')
                ->namespace($this->namespace)
                ->group(base_path('routes/genre/api.php'));
                
            // Load artist API routes
            Route::prefix('api/slider')
                ->namespace($this->namespace)
                ->group(base_path('routes/artist/api.php'));
                
            // Load slider API routes
            Route::prefix('api/slider')
                ->namespace($this->namespace)
                ->group(base_path('routes/slider/api.php'));
                
            // Load vip API routes
            Route::prefix('api/vip')
                ->namespace($this->namespace)
                ->group(base_path('routes/vip/api.php'));

            // Load song API routes
            Route::prefix('api/song')
                ->namespace($this->namespace)
                ->group(base_path('routes/song/api.php'));
                
            // Load feedback API routes
            Route::prefix('api/feedback')
                ->namespace($this->namespace)
                ->group(base_path('routes/feedback/api.php'));
                
            // Load question API routes
            Route::prefix('api/question')
                ->namespace($this->namespace)
                ->group(base_path('routes/question/api.php'));
                
            // Load shopcredit API routes
            Route::prefix('api/shopcredit')
                ->namespace($this->namespace)
                ->group(base_path('routes/shopcredit/api.php'));

            // Load promotion API routes
            Route::prefix('api/promotion')
                ->namespace($this->namespace)
                ->group(base_path('routes/promotion/api.php'));
                
            // Load gamebookmark API routes
            Route::prefix('api/gamebookmark')
                ->namespace($this->namespace)
                ->group(base_path('routes/gamebookmark/api.php'));
                
            // Load gamemember API routes
            Route::prefix('api/gamemember')
                ->namespace($this->namespace)
                ->group(base_path('routes/gamemember/api.php'));
                
            // Load gamemember API routes
            Route::prefix('api/gamepoint')
                ->namespace($this->namespace)
                ->group(base_path('routes/gamemember/api.php'));
                
            // Load gametype API routes
            Route::prefix('api/gametype')
                ->namespace($this->namespace)
                ->group(base_path('routes/gametype/api.php'));
                
            // Load notification API routes
            Route::prefix('api/notification')
                ->namespace($this->namespace)
                ->group(base_path('routes/notification/api.php'));
                
            // Load log API routes
            Route::prefix('api/log')
                ->namespace($this->namespace)
                ->group(base_path('routes/log/api.php'));
                
            // Load payment API routes
            Route::prefix('api/payment')
                ->namespace($this->namespace)
                ->group(base_path('routes/paymentgateway/api.php'));

        });
    }
}
