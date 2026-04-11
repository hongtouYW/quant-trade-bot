<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $locale = request()->header('Accept-Language');
        if ($locale) {
            $locale = explode(',', $locale)[0];
            $locale = explode(';', $locale)[0];
            $supportedLocales = config('languages.supported');
            if (in_array($locale, $supportedLocales)) {
                App::setLocale($locale);
            } else {
                App::setLocale(config('app.locale'));
            }
        } else {
            App::setLocale(config('app.locale'));
        }

        Validator::extend('password_validator', function ($attribute, $value, $parameters, $validator) {
            return strlen($value) >= 8 &&
                   strlen($value) <= 20 &&
                   preg_match('/[A-Za-z]/', $value) &&
                   preg_match('/[0-9]/', $value);
        });
        Validator::replacer('password_validator', function ($message, $attribute, $rule, $parameters) {
            return __('messages.password_format', ['attribute' => $attribute]);
        });

        Validator::extend('google', function ($attribute, $value, $parameters, $validator) {
            // Your custom validation logic here
            // Example: Check if the value is exactly "Google" (case-insensitive)
            return strtolower($value) === 'google';
        });

        // Optionally, define a custom error message
        Validator::replacer('google', function ($message, $attribute, $rule, $parameters) {
            return "The {$attribute} must be 'Google'.";
        });

        Blade::if('masteradmin', function () {
            // return Auth::check() && in_array(optional(Auth::user())->user_role, ['masteradmin', 'superadmin']);
            return Auth::check() && optional(Auth::user())->user_role === 'masteradmin';
        });

        if (!app()->isLocal()) {
            URL::forceScheme('https');
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Relation::morphMap([
            'member'  => \App\Models\Member::class,
            'manager' => \App\Models\Manager::class,
            'shop'    => \App\Models\Shop::class,
        ]);

        Paginator::useBootstrapFive(); // if AdminLTE v3.2+

        // ---------------------------
        // Global Select2 Assets & Init for AdminLTE pages
        // ---------------------------
        \View::composer('adminlte::page', function ($view) {
            // Prevent infinite loop
            static $select2AlreadyPushed = false;

            if ($select2AlreadyPushed) {
                return; // already pushed, skip
            }

            $select2AlreadyPushed = true;
            // Push CSS
            $view->getFactory()->startPush('css', <<<HTML
                <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
                <style>
                .select2-container--default .select2-selection--single { height: calc(2.25rem + 2px); padding: .375rem .75rem; border: 1px solid #ced4da; border-radius: .25rem; }
                .select2-container--default .select2-selection__rendered { line-height: 1.8rem; }
                .select2-container--default .select2-selection__arrow { height: calc(2.25rem + 2px); }
                .select2-container--default .select2-selection--multiple .select2-selection__choice {
                    color: #000 !important;
                }
                </style>
                HTML
            );

            // Push JS
            $view->getFactory()->startPush('js', <<<HTML
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
                <script>
                    function initSelect2(scope = document) {
                        $(scope).find('.select2').each(function () {
                            // Only initialize if not already initialized
                            if ($(this).hasClass('select2-hidden-accessible')) return;

                            let parent = $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body);

                            $(this).select2({
                                placeholder: $(this).data('placeholder') || '',
                                allowClear: true,
                                width: '100%',
                                dropdownParent: parent
                            });
                        });
                    }

                    $(document).ready(() => initSelect2());
                    $(document).ajaxComplete((event, xhr, settings) => {
                        initSelect2($('.select2').not('.select2-hidden-accessible'));
                    });
                    document.addEventListener('livewire:load', () => {
                        initSelect2();
                        if (window.Livewire) Livewire.hook('message.processed', (msg, comp) => initSelect2(comp.el));
                    });

                    // ============================
                    // 🔥 GLOBAL BATCH AJAX FUNCTION
                    // ============================
                    window.runBatchAjax = function ({
                        items = [],
                        url = '',
                        method = 'POST',
                        data = {},
                        bar = null,
                        onFinish = null,
                        onError = null,
                        stopOnError = true // ✅ new option
                    }) {
                        let total = items.length;
                        let current = 0;
                        let results = [];
                        let errors = [];
                        function next() {
                            if (current >= total) {
                                if (bar) {
                                    bar.css('width', '100%').text('100%');
                                }
                                if (onFinish) {
                                    onFinish({
                                        results,
                                        errors
                                    });
                                }
                                return;
                            }
                            let item = items[current];
                            $.ajax({
                                url: url,
                                method: method,
                                data: {
                                    _token: $('meta[name="csrf-token"]').attr('content'),
                                    ...data,
                                    item: item
                                },
                                success: function (res) {
                                    if (!res.status) {
                                        let errObj = {
                                            item: item,
                                            error: res.error || 'Unknown error'
                                        };
                                        errors.push(errObj);
                                        if (onError) onError(errObj);
                                        if (stopOnError) return;
                                    } else {
                                        if (res.data) {
                                            if (Array.isArray(res.data)) {
                                                results = results.concat(res.data);
                                            } else {
                                                results.push(res.data);
                                            }
                                        }
                                    }
                                    current++;
                                    if (bar) {
                                        let percent = Math.floor((current / total) * 100);
                                        bar.css('width', percent + '%').text(percent + '%');
                                    }
                                    next();
                                },
                                error: function (xhr) {
                                    let errObj = {
                                        item: item,
                                        error: xhr.responseJSON?.message || 'Request failed',
                                        status: xhr.status
                                    };
                                    errors.push(errObj);
                                    if (onError) onError(errObj);
                                    if (stopOnError) return;
                                    current++;
                                    next();
                                }
                            });
                        }
                        next();
                    };
                </script>
                HTML
            );
        });

    }
}
