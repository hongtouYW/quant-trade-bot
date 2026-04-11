<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Roles;
use App\Models\Countries;
use App\Models\States;
use App\Models\Areas;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('admin_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = User::query()
                            ->with('States','Areas','Agent');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
                $query->where('user_id', '!=', 0); // 别显示master admin
            } else {
                if ($request->filled('agent_id')) {
                    $query->where('agent_id', $request->input('agent_id') );
                }
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('user_login', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('user_name', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('state_code', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('area_code', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('state_code')) {
                $query->where('state_code', $request->input('state_code') );
            }
            if ($request->filled('area_code')) {
                $query->where('area_code', $request->input('area_code') );
            }
            if ($request->filled('role_name')) {
                $query->where('user_role', $request->input('role_name'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            if ( $authorizedUser->user_role !== "masteradmin" ) {
                $query->whereNot('user_role', 'masteradmin');
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $users = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $agents = [];
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->get();
            }
            $states = States::where('status', 1)
                            ->where('delete', 0)
                            ->get();
            $areas = Areas::where('status', 1)
                          ->where('delete', 0)
                          ->get();
            $roles = Roles::where('status', 1)
                          ->where('delete', 0);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $roles = $roles->where('role_name', '!=', 'masteradmin');
            }
            $roles = $roles->get();
            return view(
                'module.user.list', 
                [
                    'users' => $users,
                    'agents' => $agents,
                    'states' => $states,
                    'areas' => $areas,
                    'roles' => $roles
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching user list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new user.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('admin_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $roles = Roles::where('status', 1)
            ->where('delete', 0)
            ->whereNotIn('role_name', ['masteradmin', 'superadmin'])
            ->get();
        $agents = Agent::where('status', 1)
            ->where('delete', 0)
            ->orderBy('agent_name')
            ->get();
        $myagent = Agent::where('status', 1)
            ->where('delete', 0)
            ->where('agent_id', $authorizedUser->agent_id)
            ->with('States')
            ->first();
        $state_name = optional($myagent->States)->state_name;
        $areas = Areas::where('status', 1)
            ->where('delete', 0)
            ->where('state_code', $myagent->state_code)
            ->orderBy('area_name')
            ->get();
        return view('module.user.create', compact('roles', 'agents', 'state_name', 'areas'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('admin_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $rules = [
            'user_login' => 'required|string|max:255|unique:tbl_user,user_login',
            'user_pass' => 'required|string|min:8|max:255',
            'user_name' => 'required|string|max:255',
            'user_role' => 'required|string|max:20',
            'area_code' => 'nullable|string|max:10',
        ];

        if ($authorizedUser->user_role === 'masteradmin') {
            $rules['agent_id'] = 'required|integer|exists:tbl_agent,agent_id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            $agentId = $authorizedUser->user_role === 'masteradmin'
                ? $request->input('agent_id')
                : $authorizedUser->agent_id;
            $tbl_user = User::create([
                'user_login' => $request->input('user_login'),
                'user_pass' => Hash::make($request->input('user_pass')),
                'user_name' => $request->input('user_name'),
                'user_role' => $request->input('user_role'),
                'state_code' => $authorizedUser->state_code,
                'area_code' => $request->input('area_code') ?? null,
                'GAstatus' => 0,
                'agent_id' => $agentId,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            LogCreateAccount( $tbl_user, "user", $tbl_user->user_name, $request );
            return redirect()->route('admin.user.index')->with('success', __('user.user_added_successfully',['user_name'=>$tbl_user->user_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding user: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error').":".$e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified user.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('admin_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $user = User::where('user_id', $id)->first();
        if (!$user) {
            return redirect()->route('admin.user.index')->with('error', __('messages.nodata'));
        }
        $roles = Roles::where('status', 1)
            ->where('delete', 0)
            ->whereNotIn('role_name', ['masteradmin', 'superadmin'])
            ->get();
        $agents = DB::table('tbl_agent')
            ->where('status', 1)
            ->where('delete', 0)
            ->orderBy('agent_name')
            ->get();
        $myagent = Agent::where('status', 1)
            ->where('delete', 0)
            ->where('agent_id', $authorizedUser->agent_id)
            ->with('States')
            ->first();
        $state_name = optional($myagent->States)->state_name;
        $areas = Areas::filterByState($user->state_code);
        return view('module.user.edit', compact('user', 'roles', 'agents', 'state_name', 'areas'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('admin_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_user = User::where('user_id', $id)->first();
        if (!$tbl_user) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }
        $userId = (int) $id;
        $rules = [
            'user_login' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tbl_user', 'user_login')->whereNot('user_id', $userId),
            ],
            'user_name' => 'required|string|max:255',
            'user_role' => 'required|string|max:20',
            'status' => 'nullable|in:1,0',
            'user_pass' => 'nullable|string|min:8|max:255',
        ];
        // ONLY masteradmin can change agent
        if ($authorizedUser->user_role === 'masteradmin') {
            $rules['agent_id'] = 'required|integer|exists:tbl_agent,agent_id';
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'user_login' => $request->input('user_login'),
                'user_name' => $request->input('user_name'),
                'user_role' => $request->input('user_role'),
                'agent_id' => $request->input('agent_id') ?? $authorizedUser->agent_id,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];

            // Only update password if provided
            if ($request->filled('user_pass')) {
                $updateData['user_pass'] = Hash::make($request->input('user_pass'));
            }
            $tbl_user->update($updateData);

            return redirect()->route('admin.user.index')->with('success', __('user.user_updated_successfully',['user_name'=>$tbl_user->user_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating user: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('admin_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_user = User::where('user_id', $id)->first();
        if (!$tbl_user) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            $tbl_user->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.user.index')->with('success', __('user.user_deleted_successfully',['user_name'=>$tbl_user->user_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting user: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    public function filterarea($state_code)
    {
        try {
            $areas = Areas::filterByState($state_code);
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'code' => 200,
                'data' => $areas,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Area filter error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }
}
