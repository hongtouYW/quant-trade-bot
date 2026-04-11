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

class RoleController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('role_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Roles::query();
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('role_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('role_desc', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $query = $query->orderBy('created_on', 'desc');
            $roles = $query->paginate(10)->appends($request->all());
            return view('module.role.list', ['roles' => $roles]);
        } catch (\Exception $e) {
            Log::error("Error fetching role list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new role.
     * @return \Illuminate\View\View
     */
    public function create()
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
            $accesslist[] = [
                'module_id' => $module->module_id,
                'module_name' => $module->module_name,
                'section' => $module->section,
                'user_role' => null,
                'can_view' => false,
                'can_edit' => false,
                'can_delete' => false,
            ];
        }
        return view('module.role.create', compact('accesslist'));
    }

    /**
     * Store a newly created user in storage.
     * Corresponds to your API's 'add' method logic.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('role_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:100|unique:tbl_role,role_name',
            'role_desc' => 'nullable|string|max:10000',
            'access' => 'array',
            'access.*.module_id' => 'required|integer',
            'access.*.can_view' => 'nullable|boolean',
            'access.*.can_edit' => 'nullable|boolean',
            'access.*.can_delete' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // DB::beginTransaction();

            // // 1. Create role
            // DB::table('tbl_role')->insert([
            //     'role_name' => $request->input('role_name'),
            //     'role_desc' => $request->input('role_desc') ?? null,
            //     'status' => 1,
            //     'delete' => 0,
            //     'created_on' => now(),
            //     'updated_on' => now(),
            // ]);

            // $roleName = $request->input('role_name');

            // // 2. Get all active modules
            // $modules = DB::table('tbl_module')
            //     ->where('status', 1)
            //     ->where('delete', 0)
            //     ->get();

            // // 3. Give FULL access for every module
            // foreach ($modules as $module) {
            //     DB::table('tbl_access')->insert([
            //         'user_role'  => $roleName,
            //         'module_id'  => $module->module_id,
            //         'can_view'   => 1,
            //         'can_edit'   => 1,
            //         'can_delete' => 1,
            //         'status'     => 1,
            //         'delete'     => 0,
            //         'created_on' => now(),
            //         'updated_on' => now(),
            //     ]);
            // }

            // DB::commit();
            DB::beginTransaction();
            $tbl_role = Roles::create([
                'role_name' => $request->input('role_name'),
                'role_desc' => $request->input('role_desc') ?? null,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $validatedData = $validator->validated();
            $submittedAccess = $validatedData['access'] ?? [];
            foreach ($submittedAccess as $key => $permissions) {
                $access = Access::where('user_role', $request->input('role_name') )
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
                        'user_role' => $request->input('role_name'),
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
            DB::commit();
            return redirect()
                ->route('admin.role.index')
                ->with('success', __('role.role_added_successfully'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error adding role with permissions: " . $e->getMessage());

            return redirect()
                ->back()
                ->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified role.
     *
     * @param  int  $id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function edit($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('role_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $role = DB::table('tbl_role')->where('role_name', $id)->first();
        if (!$role) {
            return redirect()->route('admin.role.index')->with('error', __('role.no_data_found'));
        }
        $modules = Module::where('status', 1)
                    ->where('delete', 0);
        $modules = $modules->whereNotIn('module_name', config('modules.restrict') ); //Never open for access
        $modules = $modules->orderBy('section');
        $modules = $modules->get();
        $accesslist = [];
        foreach ($modules as $key => $module) {
            $access = Access::where( 'user_role', $role->role_name )
                    ->where( 'module_id', $module->module_id )
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->first();
            $accesslist[] = [
                'module_id' => $module->module_id,
                'module_name' => $module->module_name,
                'section' => $module->section,
                'user_role' => $role->role_name,
                'can_view' => $access ? $access->can_view : false,
                'can_edit' => $access ? $access->can_edit : false,
                'can_delete' => $access ? $access->can_delete : false,
            ];
        }
        return view('module.role.edit', compact('role','accesslist'));
    }

    /**
     * Update the specified user in storage.
     * Corresponds to your API's 'edit' method logic.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('role_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_role = Roles::where('role_name', $id)->first();
        if (!$tbl_role) {
            return redirect()->back()->with('error', __('role.no_data_found'));
        }

        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:100',
            'role_desc' => 'nullable|string|max:10000',
            'status' => 'nullable|in:1,0',
            'access' => 'array',
            'access.*.module_id' => 'required|integer',
            'access.*.can_view' => 'nullable|boolean',
            'access.*.can_edit' => 'nullable|boolean',
            'access.*.can_delete' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();
            $old_role_name = $tbl_role->role_name;
            $tbl_role->update([
                'role_name' => $request->input('role_name'),
                'role_desc' => $request->input('role_desc'),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ]);
            if ( $request->filled('status') ) {
                if ( (int)$request->input('status') === 0 ) {
                    Access::where('user_role', $old_role_name)
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->update([
                            'status' => 0,
                            'updated_on' => now(),
                        ]);
                    DB::commit();
                    return redirect()->route('admin.role.index')->with('success', __('role.role_updated_successfully'));
                }
            }
            $validatedData = $validator->validated();
            $submittedAccess = $validatedData['access'] ?? [];
            foreach ($submittedAccess as $key => $permissions) {
                $access = Access::where('user_role', $request->input('role_name') )
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
                        'user_role' => $request->input('role_name'),
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
            DB::commit();
            return redirect()->route('admin.role.index')->with('success', __('role.role_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error("Error updating role: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Remove the specified user from storage (soft delete).
     * Corresponds to your API's 'delete' method logic.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('role_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_role = Roles::where('role_name', $id)->first();
        if (!$tbl_role) {
            return redirect()->back()->with('error', __('role.no_data_found'));
        }

        try {
            DB::beginTransaction();
            $tbl_role->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);
            $tbl_role = $tbl_role->fresh();
            Access::where('user_role', $tbl_role->role_name)
                  ->where('status', 1)
                  ->where('delete', 0)
                  ->delete();
            DB::commit();
            return redirect()->route('admin.role.index')->with('success', __('role.role_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting role: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
