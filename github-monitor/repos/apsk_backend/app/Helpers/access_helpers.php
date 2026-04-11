<?php

use App\Models\Access;
use Illuminate\Support\Facades\Auth;

if (!function_exists('moduleAccess')) {
    function moduleAccess(string $moduleName)
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        return Access::with('module')
            ->where('user_role', $user->user_role)
            ->whereHas('module', function ($q) use ($moduleName) {
                $q->where('module_name', $moduleName);
            })
            ->first();
    }
}

if (!function_exists('canView')) {
    function canView(string $module)
    {
        return moduleAccess($module)?->can_view ?? false;
    }
}

if (!function_exists('canEdit')) {
    function canEdit(string $module)
    {
        return moduleAccess($module)?->can_edit ?? false;
    }
}

if (!function_exists('canDelete')) {
    function canDelete(string $module)
    {
        return moduleAccess($module)?->can_delete ?? false;
    }
}
