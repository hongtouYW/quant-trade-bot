<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Access;
use App\Models\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CountryController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('country_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = DB::table('tbl_countries');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('country_code', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('country_name', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDate($query, $request, 'created_on');
            $countries = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.country.list', ['countries' => $countries]);
        } catch (\Exception $e) {
            Log::error("Error fetching country list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new country.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('country_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        return view('module.country.create', []);
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('country_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'country_code' => 'required|string|max:3|unique:tbl_countries,country_code',
            'country_name' => 'required|string|max:100|unique:tbl_countries,country_name',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $userId = DB::table('tbl_countries')->insertGetId([
                'country_code' => $request->input('country_code'),
                'country_name' => $request->input('country_name'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.country.index')->with('success', __('country.country_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding country: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified country.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('country_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $country = DB::table('tbl_countries')->where('country_code', $id)->first();
        if (!$country) {
            return redirect()->route('admin.country.index')->with('error', __('messages.nodata'));
        }
        return view('module.country.edit', compact('country'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('country_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $country = DB::table('tbl_countries')->where('country_code', $id)->first();
        if (!$country) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'country_code' => ['required', 'string', 'max:3', Rule::unique('tbl_countries', 'country_code')->ignore($id, 'country_code')],
            'country_name' => ['required', 'string', 'max:100', Rule::unique('tbl_countries', 'country_name')->ignore($id, 'country_name')],
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'country_code' => $request->input('country_code'),
                'country_name' => $request->input('country_name'),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_countries')->where('country_code', $id)->update($updateData);
            return redirect()->route('admin.country.index')->with('success', __('country.country_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating country: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('country_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $country = DB::table('tbl_countries')->where('country_code', $id)->first();
        if (!$country) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_countries')->where('country_code', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.country.index')->with('success', __('country.country_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting country: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
