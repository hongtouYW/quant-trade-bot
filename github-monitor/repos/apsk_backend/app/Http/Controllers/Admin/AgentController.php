<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Agent;
use App\Models\Countries;
use App\Models\States;
use App\Models\VIP;
use App\Models\Agentcredit;
use App\Models\Manager;
use App\Models\Gameplatform;
use App\Models\Gameplatformaccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class AgentController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Agent::with('Countries','States');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('agent_code', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('agent_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('support', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('telegramsupport', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('whatsappsupport', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('country_code')) {
                $query->where('country_code', $request->input('country_code') );
            }
            if ($request->filled('state_code')) {
                $query->where('state_code', $request->input('state_code') );
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $agents = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $countries = Countries::where('status', 1)
                                  ->where('delete', 0)
                                  ->get();
            $states = States::where('status', 1)
                            ->where('delete', 0)
                            ->get();
            return view(
                'module.agent.list', 
                [
                    'agents' => $agents,
                    'countries' => $countries,
                    'states' => $states,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching agent list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new agent.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $countries = Countries::where('status', 1)
              ->where('delete', 0)
              ->orderBy('country_name')
              ->get();
        $states = States::where('status', 1)
              ->where('delete', 0)
              ->orderBy('state_name')
              ->get();
        return view('module.agent.create', compact('countries','states') );
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $tbl_master = Agent::where('agent_id', $authorizedUser->agent_id)->first();
        if (!$tbl_master) {
            return redirect()->back()->with('error', __('agent.no_master_found'));
        }
        $validator = Validator::make($request->all(), [
            'agent_name' => 'required|string|max:50',
            'country_code' => 'required|string|max:3',
            'state_code' => 'required|string|max:10',
            'support' => 'nullable|string|max:255',
            'telegramsupport' => 'nullable|string|max:255',
            'whatsappsupport' => 'nullable|string|max:255',
            'principal' => 'required|numeric',
            'isChatAccountCreate' => 'nullable|in:1,0',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',

            // Superadmin user
            'user_login' => 'required|string|max:50|unique:tbl_user,user_login',
            'user_name'  => 'required|string|max:50',
            'user_pass'  => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            if ( $tbl_master->balance < $request->input('principal') ) {
                return redirect()->back()->with('error', __('agent.masterinsufficient'));
            }
            $clearaccount = $request->input('principal');
            $iconPath = null;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['agent_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/agent', $filename, 'public');
            }

            $tbl_agent = Agent::create([
                'agent_code' => Agent::newcode(),
                'agent_name' => $request->input('agent_name'),
                'country_code' => $request->input('country_code'),
                'state_code' => $request->input('state_code'),
                'support' => $request->input('support') ?? null,
                'telegramsupport' => $request->input('telegramsupport') ?? null,
                'whatsappsupport' => $request->input('whatsappsupport') ?? null,
                'balance' => $request->input('principal'),
                'principal' => $request->input('principal'),
                'isChatAccountCreate' => $request->filled('isChatAccountCreate') ? $request->input('status'): 0,
                'icon' => $iconPath,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);

            // Create superadmin user
            $tbl_user = User::create([
                'agent_id'   => $tbl_agent->agent_id,
                'user_login' => $request->input('user_login'),
                'user_name'  => $request->input('user_name'),
                'user_pass'  => Hash::make($request->input('user_pass')),
                'user_role'  => 'superadmin',
                'state_code'  => $request->input('state_code'),
                'status'     => 1,
                'delete'     => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);

            $tbl_agentcredit = Agentcredit::create([
                'agent_id' => $tbl_agent->agent_id,
                'user_id' => $authorizedUser->user_id,
                'type' => "agent.newregister",
                'amount' => $request->input('principal'),
                'before_balance' => 0.00,
                'after_balance' => $request->input('principal'),
                'submit_on' => now(),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);

            $tbl_gameplatform = Gameplatform::where('status', 1)
                ->where('delete', 0)
                ->get();
            $gameplatformaccess = $tbl_gameplatform->map(function ($gameplatform) use ($tbl_agent) {
                return [
                    'gameplatform_id' => $gameplatform->gameplatform_id,
                    'agent_id'        => $tbl_agent->agent_id,
                    'commission'      => $gameplatform->commission,
                    'can_use'         => 1,
                    'status'          => 1,
                    'delete'          => 0,
                    'created_on'      => now(),
                    'updated_on'      => now(),
                ];
            })->toArray();
            Gameplatformaccess::upsert(
                $gameplatformaccess,
                ['gameplatform_id', 'agent_id'],
                ['commission', 'can_use', 'status', 'delete', 'updated_on']
            );

            //Create VIP levels (VIP0 → VIP10)
            $vipRows = [];
            $min = 0;
            $step = 10000;
            for ($lvl = 0; $lvl <= 10; $lvl++) {
                $max = ($lvl === 10) ? 10000000 : $min + $step;
                $vipRows[] = [
                    'vip_name'     => 'VIP' . $lvl,
                    'vip_desc'     => 'VIP ' . $lvl,
                    'lvl'          => $lvl,
                    'type'         => 'vip',
                    'reward'       => 'none',
                    'icon'         => null,
                    'firstbonus'   => (float) $lvl,
                    'dailybonus'   => 0.00,
                    'weeklybonus'  => (float) $lvl,
                    'monthlybonus' => (float) $lvl,
                    'min_amount'   => $min,
                    'max_amount'   => $max,
                    'agent_id'     => $tbl_agent->agent_id,
                    'status'       => 1,
                    'delete'       => 0,
                    'created_on'   => now(),
                    'updated_on'   => now(),
                ];
                $min = $max;
            }
            DB::table('tbl_vip')->insert($vipRows);
            DB::commit();

            $tbl_master->decrement('balance', $clearaccount, [
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.agent.index')->with('success', __('agent.agent_added_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding agent: " . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to add agent: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified agent.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $agent = Agent::where('agent_id', $id)->first();
        if (!$agent) {
            return redirect()->route('admin.agent.index')->with('error', __('messages.nodata'));
        }
        $countries = Countries::where('status', 1)
              ->where('delete', 0)
              ->orderBy('country_name')
              ->get();
        $states = States::where('status', 1)
              ->where('delete', 0)
              ->orderBy('state_name')
              ->get();
        return view('module.agent.edit', compact('agent','countries','states'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $tbl_master = Agent::where('agent_id', $authorizedUser->agent_id)->first();
        if (!$tbl_master) {
            return redirect()->back()->with('error', __('agent.no_master_found'));
        }
        $tbl_agent = Agent::where('agent_id', $id)->first();
        if (!$tbl_agent) {
            return redirect()->back()->with('error', __('agent.no_data_found'));
        }
        $validator = Validator::make($request->all(), [
            'agent_name' => 'required|string|max:50',
            'support' => 'nullable|string|max:255',
            'telegramsupport' => 'nullable|string|max:255',
            'whatsappsupport' => 'nullable|string|max:255',
            'principal' => 'required|numeric',
            'isChatAccountCreate' => 'nullable|in:1,0',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'nullable|in:1,0',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            $newPrincipal = (float) $request->input('principal');
            $oldPrincipal = (float) $tbl_agent->principal;
            $diff = $newPrincipal - $oldPrincipal;
            if ($diff > 0 && $tbl_master->balance < $diff) {
                return redirect()->back()->with('error', __('agent.masterinsufficient'));
            }
            if ($diff != 0) {
                $beforeBalance = $tbl_agent->balance;
                if ($diff > 0) {
                    $tbl_master->decrement('balance', $diff, [
                        'updated_on' => now(),
                    ]);

                    $tbl_agent->increment('balance', $diff, [
                        'updated_on' => now(),
                    ]);

                    $type = "agent.addprincipal";
                    $amount = $diff;

                } else {
                    $refund = abs($diff);

                    if ($tbl_agent->balance < $refund) {
                        return redirect()->back()->with('error', __('agent.insufficientbalance'));
                    }

                    $tbl_master->increment('balance', $refund, [
                        'updated_on' => now(),
                    ]);

                    $tbl_agent->decrement('balance', $refund, [
                        'updated_on' => now(),
                    ]);

                    $type = "agent.deductprincipal";
                    $amount = $refund;
                }
                Agentcredit::create([
                    'agent_id' => $tbl_agent->agent_id,
                    'user_id' => $authorizedUser->user_id,
                    'type' => $type,
                    'amount' => $amount,
                    'before_balance' => $beforeBalance,
                    'after_balance' => $tbl_agent->balance,
                    'submit_on' => now(),
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }

            $iconPath = $tbl_agent->icon;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ($tbl_agent->icon && Storage::disk('public')->exists($tbl_agent->icon)) {
                    Storage::disk('public')->delete($tbl_agent->icon);
                }
                $sanitizedName = Str::slug($validator->validated()['agent_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/agent', $filename, 'public');
            }
            $tbl_agent->update([
                'agent_name' => $request->input('agent_name'),
                'support' => $request->input('support') ?? null,
                'telegramsupport' => $request->input('telegramsupport') ?? null,
                'whatsappsupport' => $request->input('whatsappsupport') ?? null,
                'principal' => $newPrincipal,
                'isChatAccountCreate' => $request->filled('isChatAccountCreate') ? $request->input('status'): 0,
                'icon' => $iconPath,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.agent.index')->with('success', __('agent.agent_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating agent: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $tbl_agent = Agent::where('agent_id', $id)->first();
        if (!$tbl_agent) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }
        try {
            $tbl_agent->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.agent.index')->with('success', __('agent.agent_deleted_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting agent: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $tbl_master = Agent::where('agent_id', $authorizedUser->agent_id)->first();
        if (!$tbl_master) {
            return redirect()->back()->with('error', __('agent.no_master_found'));
        }
        $tbl_agent = Agent::where('agent_id', $id)->first();
        if (!$tbl_agent) {
            return redirect()->back()->with('error', __('agent.no_data_found'));
        }
        try {
            $clearaccount = (float) $tbl_agent->principal - (float) $tbl_agent->balance;
            $tbl_agentcredit = Agentcredit::create([
                'agent_id' => $tbl_agent->agent_id,
                'user_id' => $authorizedUser->user_id,
                'type' => "agent.addprincipal",
                'amount' => abs($clearaccount),
                'before_balance' => $tbl_agent->balance,
                'after_balance' => $tbl_agent->principal,
                'submit_on' => now(),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_agent->update([
                'balance' => $tbl_agent->principal,
                'updated_on' => now(),
            ]);
            $tbl_master->decrement('balance', $clearaccount, [
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.agent.index')
                            ->with(
                                'success',
                                __(
                                    'agent.agent_clear_successfully',
                                    [
                                        'agent_name'=>$tbl_agent->agent_name
                                    ]
                                )
                            );
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error clear agent: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    public function filterstate($country_code)
    {
        try {
            $states = States::filterByCountry($country_code);
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'code' => 200,
                'data' => $states,
            ], 200);
        } catch (\Exception $e) {
            Log::error('State filter error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Donload app with tbl_agent.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadlink($id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_agent = Agent::where('agent_id', $id)->first();
        if (!$tbl_agent) {
            return response()->json([
                'status' => true,
                'message' => __('agent.no_data_found'),
                'error' => __('agent.no_data_found'),
                'code' => 400,
            ], 400);
        }
        try {
            if ( is_null($tbl_agent->agent_code) ) {
                $tbl_agent->update([
                    'agent_code' => Agent::newcode(),
                    'updated_on' => now(),
                ]);
                $tbl_agent = $tbl_agent->fresh();
            }
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'download' => config('app.urldownload')."agent-download?agentCode=".md5($tbl_agent->agent_code),
                'code' => 200,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Agent downloadlink error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    public function show($agentId)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }

        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('agent_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        try {
            $agent = Agent::where('agent_id', $agentId)
                ->where('delete', 0)
                ->first();

            if (!$agent) {
                return redirect()->route('admin.agent.index')->with('error', __('messages.nodata'));
            }

            $managers = Manager::where('agent_id', $agent->agent_id)
                ->where('status', 1)
                ->where('delete', 0)
                ->orderBy('created_on', 'desc')
                ->paginate(10);

            if (request()->ajax()) {
                return view('module.agent.partials._managers', compact('managers'));
            }

            $agentcredits = Agentcredit::where('agent_id', $agent->agent_id)
                ->where('delete', 0)
                ->orderByDesc('created_on')
                ->get(); // or paginate(10)

            return view('module.agent.show', compact('agent', 'managers', 'agentcredits'));
        } catch (\Throwable $e) {
            Log::error('Agent show error', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

}
