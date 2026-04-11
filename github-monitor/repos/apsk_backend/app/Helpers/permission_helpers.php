<?php

use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\Access;
use App\Models\Module;
use App\Models\User;
use App\Models\Manager;
use App\Models\Shop;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

if (!function_exists('CheckAuthorizedView')) {
    /**
     * Checks if a user has view permission for a specific module.
     *
     * @param string $tokenable_id The ID of the user (user_id).
     * @param int $module_id The ID of the module.
     * @return bool True if authorized to view, false otherwise.
     */
    function CheckAuthorizedView( int $user_id, int $module_id, $can_view = true): bool
    {
        $tbl_user = User::where('user_id', $user_id)->first();
        if (!$tbl_user) {
            return false;
        }
        $access = Access::where('user_role', $tbl_user->user_role)
                        ->where('module_id', $module_id)
                        ->where('can_view',  $can_view)
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
        return (bool) $access;
    }
}

if (!function_exists('CheckAuthorizedEdit')) {
    /**
     * Checks if a user has edit permission for a specific module.
     *
     * @param int $tokenable_id The ID of the user (user_id, manager_id, shop_id).
     * @param int $module_id The ID of the module.
     * @return bool True if authorized to edit, false otherwise.
     */
    function CheckAuthorizedEdit(int $user_id, int $module_id, $can_edit = true): bool
    {
        $tbl_user = User::where('user_id', $user_id)->first();
        if (!$tbl_user) {
            return false;
        }
        $access = Access::where('user_role', $tbl_user->user_role)
                        ->where('module_id', $module_id)
                        ->where('can_edit',  $can_edit)
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
        return (bool) $access;
    }
}

if (!function_exists('CheckAuthorizedDelete')) {
    /**
     * Checks if a user has delete permission for a specific module.
     *
     * @param int $tokenable_id The ID of the user (user_id, manager_id, shop_id).
     * @param int $module_id The ID of the module.
     * @return bool True if authorized to delete, false otherwise.
     */
    function CheckAuthorizedDelete(int $user_id, int $module_id, $can_delete = true): bool
    {
        $tbl_user = User::where('user_id', $user_id)->first();
        if (!$tbl_user) {
            return false;
        }
        $access = Access::where('user_role', $tbl_user->user_role)
                        ->where('module_id', $module_id)
                        ->where('can_delete',  $can_delete)
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
        return (bool) $access;
    }
}

class Permissionhelper
{
    public static function view( 
        $permission_user, 
        $user_type, 
        $permission_target, 
        $target_type, 
    )
    {
        return Permission::where('status', 1)
                         ->where('delete', 0)
                         ->where('permission_user', $permission_user)
                         ->where('user_type', $user_type)
                         ->where('permission_target', $permission_target)
                         ->where('target_type', $target_type)
                         ->with('PermissionUser','PermissionTarget')
                         ->first();
    }

    public static function viewmultiple( 
        $permission_users, 
        $user_type, 
        $permission_target, 
        $target_type, 
    )
    {
        return Permission::where('status', 1)
                         ->where('delete', 0)
                         ->whereIn('permission_user', $permission_users)
                         ->where('user_type', $user_type)
                         ->where('permission_target', $permission_target)
                         ->where('target_type', $target_type)
                         ->with('PermissionUser','PermissionTarget')
                         ->get();
    }

    public static function list( 
        $user_type, 
        $permission_target, 
        $target_type, 
    )
    {
        $tbl_permission = Permission::where('status', 1)
                         ->where('delete', 0)
                         ->where('user_type', $user_type)
                         ->where('permission_target', $permission_target)
                         ->where('target_type', $target_type);
        if ( $user_type === "manager" && $target_type === "shop" ) {
            $shop = Shop::find($permission_target);
            $tbl_permission->whereHas('PermissionUser', function ($q) use ($shop) {
                $q->where('manager_id', '!=', $shop->manager_id);
            });
        }
        $tbl_permission = $tbl_permission->with('PermissionUser','PermissionTarget')
                                         ->get();
        return $tbl_permission;
    }

    public static function listpaginated( 
        $user_type, 
        $permission_target, 
        $target_type, 
        $can_view = 1, 
        $can_edit = 1, 
        $can_delete = 1,
        $page = 1,
        $limit = 20,
    )
    {
        $permissionquery = Permission::where('status', 1)
            ->where('delete', 0)
            ->where('user_type', $user_type)
            ->where('permission_target', $permission_target)
            ->where('target_type', $target_type)
            ->where('can_view', $can_view)
            ->where('can_edit', $can_edit)
            ->where('can_delete', $can_delete);
        if ( $user_type === "manager" && $target_type === "shop" ) {
            $shop = Shop::find($permission_target);
            $permissionquery->whereHas('PermissionUser', function ($q) use ($shop) {
                $q->where('manager_id', '!=', $shop->manager_id);
            });
        }
        $permissionquery = $permissionquery->with('PermissionUser','PermissionTarget')
            ->orderBy('created_on', 'desc');
        return $permissionquery->paginate(
            $limit,
            ['*'],
            'page',
            $page
        );
    }

    public static function add(
        $permission_user, 
        $user_type, 
        $permission_target, 
        $target_type, 
        $can_view = 1, 
        $can_edit = 1, 
        $can_delete = 1,
    )
    {
        return Permission::firstOrCreate(
            [
                'permission_user' => $permission_user, 
                'user_type' => $user_type, 
                'permission_target' => $permission_target, 
                'target_type' => $target_type, 
            ],
            [
                'can_view' => $can_view,
                'can_edit' => $can_edit,
                'can_delete' => $can_delete,
                'status' => 1,
                'created_on' => now(),
                'updated_on' => now(),
            ]
        );
    }
}