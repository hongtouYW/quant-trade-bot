<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{

    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Events\MegaEvent::class => [
            \App\Listeners\MegaListener::class,
        ],
    ];

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
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        // Set this to true if you want Laravel to automatically find
        // listeners for events based on naming conventions and file paths.
        // If true, you might not need to list all events in $listen.
        return false; // Default is often false, but newer Laravel versions might default to true.
    }
}
