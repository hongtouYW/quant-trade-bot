<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AdminLteServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->app['events']->listen(BuildingMenu::class, function (BuildingMenu $event) {

            // Fetch all active modules
            $modules = DB::table('tbl_module as m')
                ->select('m.*')
                ->join(
                    DB::raw('
            (
                SELECT section, MAX(section_seq) AS section_order
                FROM tbl_module
                WHERE status = 1 AND `delete` = 0
                GROUP BY section
            ) s
            '),
                    'm.section',
                    '=',
                    's.section'
                )
                ->where('m.status', 1)
                ->where('m.delete', 0)
                ->orderBy('s.section_order')   // order by MAX(section_seq)
                ->orderBy('m.section')         // fallback
                ->orderBy('m.module_seq')      // module order
                ->orderBy('m.module_name')     // fallback
                ->get()
                ->groupBy('section');

            foreach ($modules as $section => $items) {

                $submenu = [];

                foreach ($items as $module) {

                    // Skip if user does not have permission
                    if (!canView($module->module_name)) {
                        continue;
                    }

                    $route = 'admin.' . $module->controller . '.index';

                    // Skip if route does not exist
                    if (!Route::has($route)) {
                        continue;
                    }
                    // $href = $module->has_tab === 1 ? route($route, ['tab' => $module->tab_main]) : route($route);
                    $href = $module->has_tab === 1
                        ? "javascript:window.top.location.href='".route($route, ['tab' => $module->tab_main])."';"
                        : "javascript:window.top.location.href='".route($route)."';";
                    $submenu[] = [
                        'key'   => 'module_' . $module->module_id,
                        'text'  => __('module.' . $module->module_name),
                        'icon'  => 'fas fa-circle', // optional icon
                        'route' => $route,
                        'href'  => $href,
                    ];
                }

                // Only add section if it has at least one module
                if (!empty($submenu)) {
                    $event->menu->add([
                        'key'     => 'section_' . Str::slug($section),
                        'text'    => __('section.' . $section), // translate section
                        'icon'    => 'fas fa-folder',           // section icon
                        'submenu' => $submenu,
                    ]);
                }
            }

        });
    }
}
