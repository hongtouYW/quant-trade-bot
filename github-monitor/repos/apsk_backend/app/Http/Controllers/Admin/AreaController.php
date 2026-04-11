<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\Countries;
use App\Models\States;
use App\Models\Areas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AreaController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('area_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Areas::query()
                            ->with('Countries','States','Agent');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('area_code', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('country_code', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('state_code', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('area_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('area_type', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('postal_code', 'LIKE', '%' . $searchTerm . '%');
                    // 🔗 Countries relation
                    $q->orWhereHas('Countries', function ($sq) use ($searchTerm) {
                        $sq->where('country_name', 'LIKE', "%{$searchTerm}%");
                    });
                    // 🔗 States relation
                    $q->orWhereHas('States', function ($sq) use ($searchTerm) {
                        $sq->where('state_name', 'LIKE', "%{$searchTerm}%");
                    });
                });
            }
            if ($request->filled('country_name')) {
                $countryName = $request->input('country_name');

                $query->whereHas('Countries', function ($q) use ($countryName) {
                    $q->where('country_name', 'LIKE', "%{$countryName}%");
                });
            }
            if ($request->filled('agent_name')) {
                $agentName = $request->input('agent_name');

                $query->whereHas('Agent', function ($q) use ($agentName) {
                    $q->where('agent_name', 'LIKE', "%{$agentName}%");
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $areas = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.area.list', ['areas' => $areas]);
        } catch (\Exception $e) {
            Log::error("Error fetching area list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new area.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('area_management'))) {
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
        return view('module.area.create', compact('countries', 'states'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('area_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'area_code' => 'required|string|max:255|unique:tbl_areas,area_code',
            'country_code' => 'required|string|max:3',
            'state_code' => 'required|string|max:10',
            'area_name' => 'required|string|max:100',
            'area_type' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $userId = DB::table('tbl_areas')->insertGetId([
                'area_code' => $request->input('area_code'),
                'country_code' => $request->input('country_code'),
                'state_code' => $request->input('state_code'),
                'area_name' => $request->input('area_name'),
                'area_type' => $request->input('area_type'),
                'postal_code' => $request->input('postal_code') ?? null,
                'agent_id' => $authorizedUser->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.area.index')->with('success', __('area.area_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding area: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified area.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('area_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $area = DB::table('tbl_areas')->where('area_code', $id)->first();
        if (!$area) {
            return redirect()->route('admin.area.index')->with('error', __('messages.nodata'));
        }
        $countries = Countries::where('status', 1)
              ->where('delete', 0)
              ->orderBy('country_name')
              ->get();
        $states = States::where('status', 1)
              ->where('delete', 0)
              ->orderBy('state_name')
              ->get();
        return view('module.area.edit', compact('area', 'countries', 'states'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('area_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $area = DB::table('tbl_areas')->where('area_code', $id)->first();
        if (!$area) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'area_code' => ['required', 'string', 'max:10', Rule::unique('tbl_areas', 'area_code')->ignore($id, 'area_code')],
            'country_code' => 'required|string|max:3',
            'state_code' => 'required|string|max:10',
            'area_name' => 'required|string|max:100',
            'area_type' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'area_code' => $request->input('area_code'),
                'country_code' => $request->input('country_code'),
                'state_code' => $request->input('state_code'),
                'area_name' => $request->input('area_name'),
                'area_type' => $request->input('area_type'),
                'postal_code' => $request->input('postal_code') ?? null,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_areas')->where('area_code', $id)->update($updateData);
            return redirect()->route('admin.area.index')->with('success', __('area.area_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating area: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('area_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $area = DB::table('tbl_areas')->where('area_code', $id)->first();
        if (!$area) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_areas')->where('area_code', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.area.index')->with('success', __('area.area_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting area: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
