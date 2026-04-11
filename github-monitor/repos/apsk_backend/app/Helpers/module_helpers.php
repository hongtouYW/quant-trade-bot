<?php

use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

if (!function_exists('GetSectionList')) {
    function GetSectionList()
    {
        try {
            if (!Auth::check()) {
                return collect();
            }
            $user = Auth::user();
            $viewableModules = $user->getViewableModules();
            if ($viewableModules->isEmpty()) {
                return collect();
            }
            $module_ids = $viewableModules->pluck('module_id');
            $tbl_section = DB::table('tbl_module')
                ->whereIn('module_id', $module_ids)
                ->select(
                    'section'
                )
                ->groupBy('section')
                ->get();
            return $tbl_section;
        } catch (\Exception $e) {
            Log::error("GetSectionList - Failed to fetch section list: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetModuleID')) {
    /**
     * Retrieves the module ID for a given module name, using caching for performance.
     *
     * @param string $module_name The name of the module.
     * @return ?int The ID of the module, or null if not found.
     */
    function GetModuleID(string $module_name): ?int
    {
        if (empty($module_name)) {
            return null;
        }
        try {
            $tbl_module = Module::where('module_name', $module_name)->first();
            return $tbl_module ? (int) $tbl_module->module_id : null;
        } catch (\Exception $e) {
            Log::error("GetModuleID - Failed to fetch module ID for {$module_name}: {$e->getMessage()}");
            return null;
        }
    }
}