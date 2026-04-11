<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\Roles;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AccessController extends Controller
{
    public function __construct()
    {
    }

    public function edit($user_role)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('role_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $modules = Module::where('status', 1)
                    ->where('delete', 0);
        $modules = $modules->whereNotIn('module_name', config('modules.restrict') ); //Never open for access
        $modules = $modules->orderBy('section');
        $modules = $modules->get();
        $accesslist = [];
        foreach ($modules as $key => $module) {
            $access = Access::where( 'user_role', $user_role )
                    ->where( 'module_id', $module->module_id )
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->first();
            $accesslist[] = [
                'module_id' => $module->module_id,
                'module_name' => $module->module_name,
                'section' => $module->section,
                'user_role' => $user_role,
                'can_view' => $access ? $access->can_view : false,
                'can_edit' => $access ? $access->can_edit : false,
                'can_delete' => $access ? $access->can_delete : false,
            ];
        }
        return view('module.access.edit', compact('user_role','accesslist'));
    }

    public function update(Request $request, $user_role)
    {
        $validatedData = $request->validate([
            'access' => 'array',
            'access.*.module_id' => 'required|integer',
            'access.*.can_view' => 'nullable|boolean',
            'access.*.can_edit' => 'nullable|boolean',
            'access.*.can_delete' => 'nullable|boolean',
        ]);
        $submittedAccess = $validatedData['access'] ?? [];
        foreach ($submittedAccess as $key => $permissions) {
            $access = Access::where('user_role', $user_role)
                            ->where('module_id', $permissions['module_id'] )
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            if ($access) {
                $access->can_view = isset( $permissions['can_view'] ) ?? 0;
                $access->can_edit = isset( $permissions['can_edit'] ) ?? 0;
                $access->can_delete = isset( $permissions['can_delete'] ) ?? 0;
                $access->updated_on = now();
                $access->save();
            } else {
                Access::create([
                    'user_role' => $user_role,
                    'module_id' => $permissions['module_id'],
                    'can_view' => isset( $permissions['can_view'] ) ?? 0,
                    'can_edit' => isset( $permissions['can_edit'] ) ?? 0,
                    'can_delete' => isset( $permissions['can_delete'] ) ?? 0,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }
        }
        return redirect()->route('admin.role.index')->with('success', __('role.role_updated_successfully'));
    }


}
