<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\VIP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VIPController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('vip_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = VIP::query();
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('vip_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('vip_desc', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('lvl', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('type', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('reward', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $vips = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.vip.list', ['vips' => $vips]);
        } catch (\Exception $e) {
            Log::error("Error fetching vip list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new vip.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('vip_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        return view('module.vip.create', []);
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('vip_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'vip_name' => 'required|string|max:100',
            'vip_desc' => 'nullable|string|max:10000',
            'lvl' => 'required|integer',
            'type' => 'nullable|string|max:255',
            'reward' => 'nullable|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'firstbonus' => 'nullable|numeric|gte:0.00',
            'dailybonus' => 'nullable|numeric|gte:0.00',
            'weeklybonus' => 'nullable|numeric|gte:0.00',
            'monthlybonus' => 'nullable|numeric|gte:0.00',
            'min_amount' => 'nullable|numeric|gte:0.00|required_with:max_amount',
            'max_amount' => 'nullable|numeric|gt:min_amount|required_with:min_amount',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $iconPath = null;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['vip_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/vip', $filename, 'public');
            }
            $userId = DB::table('tbl_vip')->insertGetId([
                'vip_name' => $request->input('vip_name'),
                'vip_desc' => $request->input('vip_desc') ?? null,
                'lvl' => $request->input('lvl'),
                'type' => $request->input('type'),
                'reward' => $request->input('reward'),
                'icon' => $iconPath,
                'firstbonus' => $request->filled('firstbonus') ? $request->input('firstbonus'): 0.00,
                'dailybonus' => $request->filled('dailybonus') ? $request->input('dailybonus'): 0.00,
                'weeklybonus' => $request->filled('weeklybonus') ? $request->input('weeklybonus'): 0.00,
                'monthlybonus' => $request->filled('monthlybonus') ? $request->input('monthlybonus'): 0.00,
                'min_amount' => $request->filled('min_amount') ? $request->input('min_amount'): 0.00,
                'max_amount' => $request->filled('max_amount') ? $request->input('max_amount'): 0.00,
                'agent_id' => $authorizedUser->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.vip.index')->with('success', __('vip.vip_added_successfully', ['vipname' => $request->input('vip_name')]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding vip: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified vip.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('vip_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $vip = DB::table('tbl_vip')
            ->leftJoin('tbl_agent', 'tbl_agent.agent_id', '=', 'tbl_vip.agent_id')
            ->select(
                'tbl_vip.*',
                'tbl_agent.agent_name'
            )
            ->where('tbl_vip.vip_id', $id)
            ->first();

        if (!$vip) {
            return redirect()->route('admin.vip.index')->with('error', __('messages.nodata'));
        }

        return view('module.vip.edit', compact('vip'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('vip_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $vip = DB::table('tbl_vip')->where('vip_id', $id)->first();
        if (!$vip) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'vip_name' => 'required|string|max:100',
            'vip_desc' => 'nullable|string|max:10000',
            'lvl' => 'required|integer',
            'type' => 'nullable|string|max:255',
            'reward' => 'nullable|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'firstbonus' => 'nullable|numeric|gte:0.00',
            'dailybonus' => 'nullable|numeric|gte:0.00',
            'weeklybonus' => 'nullable|numeric|gte:0.00',
            'monthlybonus' => 'nullable|numeric|gte:0.00',
            'min_amount' => 'nullable|numeric|gte:0.00|required_with:max_amount',
            'max_amount' => 'nullable|numeric|gt:min_amount|required_with:min_amount',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $iconPath = $vip->icon;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ($vip->icon && Storage::disk('public')->exists($vip->icon)) {
                    Storage::disk('public')->delete($vip->icon);
                }
                $sanitizedName = Str::slug($validator->validated()['vip_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/vip', $filename, 'public');
            }
            $updateData = [
                'vip_name' => $request->input('vip_name'),
                'vip_desc' => $request->input('vip_desc') ?? null,
                'lvl' => $request->input('lvl'),
                'type' => $request->input('type'),
                'reward' => $request->input('reward'),
                'icon' => $iconPath,
                'firstbonus' => $request->filled('firstbonus') ? $request->input('firstbonus'): 0.00,
                'dailybonus' => $request->filled('dailybonus') ? $request->input('dailybonus'): 0.00,
                'weeklybonus' => $request->filled('weeklybonus') ? $request->input('weeklybonus'): 0.00,
                'monthlybonus' => $request->filled('monthlybonus') ? $request->input('monthlybonus'): 0.00,
                'min_amount' => $request->filled('min_amount') ? $request->input('min_amount'): 0.00,
                'max_amount' => $request->filled('max_amount') ? $request->input('max_amount'): 0.00,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_vip')->where('vip_id', $id)->update($updateData);
            return redirect()->route('admin.vip.index')->with('success', __('vip.vip_updated_successfully', ['vipname' => $request->input('vip_name')]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating vip: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('vip_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $vip = DB::table('tbl_vip')->where('vip_id', $id)->first();
        if (!$vip) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_vip')->where('vip_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.vip.index')->with('success', __('vip.vip_deleted_successfully', ['vipname' => $vip->vip_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting vip: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
