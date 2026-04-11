<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Manager;
use App\Models\Managercredit;
use App\Models\Agentcredit;
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

class ManagerController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('manager_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Manager::query()
                            ->with('States','Areas','Agent');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            } else {
                if ($request->filled('agent_id')) {
                    $query->where('agent_id', $request->input('agent_id') );
                }
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('manager_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('manager_login', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('manager_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('full_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('state_code')) {
                $query->where('state_code', $request->input('state_code') );
            }
            if ($request->filled('area_code')) {
                $query->where('area_code', $request->input('area_code') );
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $managers = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
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
            return view(
                'module.manager.list', 
                [
                    'managers' => $managers,
                    'agents' => $agents,
                    'states' => $states,
                    'areas' => $areas,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching manager list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new manager.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('manager_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
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
        return view('module.manager.create', compact('state_name', 'areas'));
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
            return redirect()->route('dashboard')
                ->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('manager_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'manager_login' => 'required|string|max:255|unique:tbl_manager,manager_login',
            'manager_pass' => 'required|string|min:8|max:255',
            'manager_name' => 'required|string|max:255|unique:tbl_manager,manager_name',
            'area_code' => 'required|string|max:10',
            'phone_country' => 'required|string|max:5',
            'phone_number'  => 'nullable|digits_between:7,12',
            'principal' => 'required|numeric|min:0.01',
            'can_view_credential' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::transaction(function () use ($request, $authorizedUser) {
                // Lock agent row during transaction
                $tbl_agent = Agent::where('status', 1)
                    ->where('delete', 0)
                    ->where('agent_id', $authorizedUser->agent_id)
                    ->lockForUpdate()
                    ->first();
                if (!$tbl_agent) {
                    throw new \Exception(__('agent.no_data_found'));
                }
                $principal = (float) $request->input('principal');
                if ($tbl_agent->balance < $principal) {
                    throw new \Exception(__('agent.insufficient'));
                }
                $phone = null;
                if ($request->filled('phone_number')) {
                    $country = ltrim($request->input('phone_country'), '+');
                    $number  = ltrim($request->input('phone_number'), '0');
                    $phone   = $country . $number;
                }
                $tbl_manager = Manager::create([
                    'manager_login' => $request->input('manager_login'),
                    'manager_pass' => Hash::make($request->input('manager_pass')),
                    'manager_name' => $request->input('manager_name'),
                    'full_name' => $request->input('full_name'),
                    'state_code' => $tbl_agent->state_code,
                    'area_code' => $request->input('area_code'),
                    'phone' => $phone,
                    'balance' => $principal,
                    'principal' => $principal,
                    'GAstatus' => 0,
                    'agent_id' => $authorizedUser->agent_id,
                    'can_view_credential' => $request->input('can_view_credential') ?? 0,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                Managercredit::create([
                    'manager_id' => $tbl_manager->manager_id,
                    'user_id' => $authorizedUser->user_id,
                    'type' => "manager.create",
                    'amount' => $principal,
                    'before_balance' => 0.00,
                    'after_balance' => $principal,
                    'submit_on' => now(),
                    'agent_id' => $authorizedUser->agent_id,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                Agentcredit::create([
                    'agent_id' => $tbl_agent->agent_id,
                    'user_id' => $authorizedUser->user_id,
                    'manager_id' => $tbl_manager->manager_id,
                    'amount' => -1 * $principal,
                    'before_balance' => $tbl_agent->balance,
                    'after_balance' => $tbl_agent->balance - $principal,
                    'type' => "agent.managercreate",
                    'submit_on' => now(),
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                $tbl_agent->decrement('balance', $principal, [
                    'updated_on' => now(),
                ]);
                LogCreateAccount($tbl_manager, "manager", $tbl_manager->manager_name, $request);
            });
            return redirect()->route('admin.manager.index')
                ->with('success', __('manager.manager_added_successfully'));
        } catch (\Exception $e) {
            Log::error("Error adding manager: " . $e->getMessage());
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified manager.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('manager_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $manager = DB::table('tbl_manager')->where('manager_id', $id)->first();
        if (!$manager) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }
        $country = '+60';
        $number  = $manager->phone;
        if ($manager->phone) {
            if (preg_match('/^(60|65|86)(\d+)$/', $manager->phone, $matches)) {
                $country = '+' . $matches[1]; // add + only for UI
                $number  = $matches[2];
            }
        }
        $myagent = Agent::where('status', 1)
            ->where('delete', 0)
            ->where('agent_id', $authorizedUser->agent_id)
            ->with('States')
            ->first();
        $state_name = optional($myagent->States)->state_name;
        $areas = Areas::filterByState($manager->state_code);
        return view('module.manager.edit', compact('manager', 'state_name', 'areas', 'country', 'number'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('manager_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'manager_login' => ['required', 'string', 'max:255', Rule::unique('tbl_manager', 'manager_login')->ignore($id, 'manager_id')],
            'manager_name' => ['required', 'string', 'max:255', Rule::unique('tbl_manager', 'manager_name')->ignore($id, 'manager_id')],
            'manager_pass' => 'nullable|string|min:8|max:255', // For optional password change
            'phone_country' => 'nullable|string|max:255',
            'phone_number'  => 'nullable|digits_between:7,12',
            'principal' => 'required|numeric|min:0',
            'can_view_credential' => 'nullable|boolean',
            'status' => 'nullable|in:1,0',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::transaction(function () use (
                $request,
                $authorizedUser,
                $id
            ) {
                $tbl_manager = Manager::where('manager_id', $id)
                    ->lockForUpdate()
                    ->first();
                if (!$tbl_manager) {
                    return redirect()->back()->with('error', __('messages.nodata'));
                }

                $tbl_agent = Agent::where('status', 1)
                    ->where('delete', 0)
                    ->where('agent_id', $authorizedUser->agent_id)
                    ->lockForUpdate()
                    ->first();
                if (!$tbl_agent) {
                    throw new \Exception(__('agent.no_data_found'));
                }

                $phone = null;
                if ($request->filled('phone_number')) {
                    $country = ltrim($request->input('phone_country'), '+');
                    $number  = preg_replace('/^' . $country . '/', '', $request->input('phone_number'));
                    $number  = ltrim($number, '0');
                    $phone   = $country . $number;
                }

                $newPrincipal = (float) $request->input('principal');
                $oldPrincipal = (float) $tbl_manager->principal;
                $clearaccount = $newPrincipal - $oldPrincipal;
                if ($clearaccount > 0 && $tbl_agent->balance < $clearaccount) {
                    throw new \Exception(__('agent.insufficient'));
                }
                if ($tbl_manager->balance < ($oldPrincipal - $newPrincipal)){
                    throw new \Exception(__('manager.insufficient'));
                }
                if ($clearaccount !== 0.0) {
                    // Create Managercredit
                    Managercredit::create([
                        'manager_id' => $tbl_manager->manager_id,
                        'user_id' => $authorizedUser->user_id,
                        'type' => "manager.edit_manager",
                        'amount' => $clearaccount,
                        'before_balance' => $tbl_manager->balance,
                        'after_balance' => $tbl_manager->balance + $clearaccount,
                        'submit_on' => now(),
                        'agent_id' => $authorizedUser->agent_id,
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    // Update Manager balance + principal
                    $tbl_manager->update([
                        'balance' => $tbl_manager->balance + $clearaccount,
                        'principal' => $newPrincipal,
                    ]);

                    // Create Agentcredit
                    Agentcredit::create([
                        'agent_id' => $tbl_agent->agent_id,
                        'user_id' => $authorizedUser->user_id,
                        'manager_id' => $tbl_manager->manager_id,
                        'amount' => -1 * $clearaccount,
                        'before_balance' => $tbl_agent->balance,
                        'after_balance' => $tbl_agent->balance - $clearaccount,
                        'type' => "agent.manageredit",
                        'submit_on' => now(),
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    // Update Agent balance
                    $tbl_agent->decrement('balance', $clearaccount, [
                        'updated_on' => now(),
                    ]);
                }

                //Update Basic Manager Info
                $tbl_manager->update([
                    'manager_login' => $request->input('manager_login'),
                    'manager_name' => $request->input('manager_name'),
                    'full_name' => $request->input('full_name'),
                    'state_code' => $tbl_agent->state_code,
                    'phone' => $phone,
                    'can_view_credential' => $request->input('can_view_credential') ?? 0,
                    'status' => $request->filled('status') ? $request->input('status') : 0,
                    'updated_on' => now(),
                ]);

                if ($request->filled('manager_pass')) {
                    $tbl_manager->update([
                        'manager_pass' => Hash::make($request->input('manager_pass'))
                    ]);
                }

            });
            return redirect()->route('admin.manager.index')
                ->with('success', __('manager.manager_updated_successfully'));
        } catch (\Exception $e) {
            Log::error("Error updating manager: " . $e->getMessage());
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('manager_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $manager = DB::table('tbl_manager')->where('manager_id', $id)->first();
        if (!$manager) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_manager')->where('manager_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.manager.index')
                             ->with(
                                'success', 
                                __(
                                    'manager.manager_deleted_successfully',
                                    [
                                        'manager_name'=>$manager->manager_name
                                    ]
                                )
                            );

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting manager: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Clear the specified user from storage (clear account).
     * Corresponds to your API's 'edit' method logic.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clear($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('manager_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $tbl_manager = Manager::where('manager_id', $id)->first();
        if (!$tbl_manager) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }
        try {
            $tbl_agent = Agent::where('status', 1)
                        ->where('delete', 0)
                        ->where('agent_id', $authorizedUser->agent_id)
                        ->first();
            if (!$tbl_agent) {
                return redirect()->back()->with('error', __('agent.no_data_found'));
            }
            $clearaccount = (float) $tbl_manager->balance - (float) $tbl_manager->principal;
            $tbl_managercredit = Managercredit::create([
                'manager_id' => $tbl_manager->manager_id,
                'user_id' => $authorizedUser->user_id,
                'type' => "clear",
                'amount' => $clearaccount,
                'before_balance' => $tbl_manager->balance,
                'after_balance' => $tbl_manager->principal,
                'submit_on' => now(),
                'agent_id' => $authorizedUser->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_manager->update([
                'balance' => $tbl_manager->principal,
                'updated_on' => now(),
            ]);
            $tbl_agent->increment('balance', $clearaccount, [
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.manager.index')
                            ->with(
                                'success',
                                __(
                                    'manager.manager_clear_successfully',
                                    [
                                        'manager_name'=>$tbl_manager->manager_name
                                    ]
                                )
                            );
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error clear manager: " . $e->getMessage());
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

    public function show(Manager $manager)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }

        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('manager_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $manager->load('shops');

        // Optional: restrict non-masteradmin
        if ($authorizedUser->user_role !== 'masteradmin'
            && $manager->agent_id !== $authorizedUser->agent_id) {
            abort(403);
        }

        $managercredits = Managercredit::where('manager_id', $manager->manager_id)
            ->where('delete', 0)
            ->orderByDesc('created_on')
            ->get();

        return view('module.manager.show', compact('manager', 'managercredits'));
    }
}
