<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\IPBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class IPBlockController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('ipblock_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = IPBlock::with('Agent');
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
                    $q->where('ip', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('reason', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $ipblocks = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $agents = [];
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->get();
            }
            return view('module.ipblock.list', ['ipblocks' => $ipblocks, 'agents' => $agents ]);
        } catch (\Exception $e) {
            Log::error("Error fetching ipblock list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new ipblock.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('ipblock_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $agents = Agent::where('status', 1)
                        ->where('delete', 0)
                        ->get();
        return view('module.ipblock.create', compact('agents'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('ipblock_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'ip' => 'required|string|max:255',
            'reason' => 'required|string|max:10000',
            'agent_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $agent_id = $authorizedUser->user_role === 'masteradmin' ? 
                        $request->input('agent_id') :
                        $authorizedUser->agent_id;
            $tbl_ipblock = IPBlock::create([
                'ip' => $request->input('ip'),
                'reason' => $request->input('reason'),
                'agent_id' => $agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.ipblock.index')
                ->with('success', __('ipblock.ipblock_added_successfully'));
        } catch (\Exception $e) {
            Log::error("Error adding ipblock: " . $e->getMessage());
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified ipblock.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('ipblock_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $ipblock = IPBlock::where('ipblock_id', $id)->first();
        if (!$ipblock) {
            return redirect()->route('admin.ipblock.index')->with('error', __('ipblock.no_data_found'));
        }
        $agents = Agent::where('status', 1)
                        ->where('delete', 0)
                        ->get();
        return view('module.ipblock.edit', compact('ipblock','agents'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('ipblock_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_ipblock = IPBlock::where('ipblock_id', $id)->first();
        if (!$tbl_ipblock) {
            return redirect()->back()->with('error', __('ipblock.no_data_found'));
        }

        $validator = Validator::make($request->all(), [
            'ip' => 'required|string|max:255',
            'reason' => 'required|string|max:10000',
            'agent_id' => 'nullable|integer',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $agent_id = $authorizedUser->user_role === 'masteradmin' ? 
                        $request->input('agent_id') :
                        $authorizedUser->agent_id;
            $tbl_ipblock->update([
                'ip' => $request->input('ip'),
                'reason' => $request->input('reason'),
                'agent_id' => $agent_id,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.ipblock.index')->with('success', __('ipblock.ipblock_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating ipblock: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('ipblock_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_ipblock = IPBlock::where('ipblock_id', $id)->first();
        if (!$tbl_ipblock) {
            return redirect()->back()->with('error', __('ipblock.no_data_found'));
        }

        try {
            $tbl_ipblock->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.ipblock.index')->with('success', __('ipblock.ipblock_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting ipblock: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
