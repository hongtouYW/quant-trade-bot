<?php

namespace App\Http\Controllers\Admin;

use App\Models\Commissionrank;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CommissionrankController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('commissionrank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Commissionrank::query();
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('rank', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $commissionranks = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.commissionrank.list', ['commissionranks' => $commissionranks]);
        } catch (\Exception $e) {
            Log::error("Error fetching commissionrank list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new commissionrank.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('commissionrank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $commissionranks = Commissionrank::where('status', 1)
              ->where('delete', 0)
              ->get();
        return view('module.commissionrank.create', compact('commissionranks'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('commissionrank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'rank' => 'required|integer',
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_commissionrank = Commissionrank::create([
                'rank' => $request->input('rank'),
                'amount' => $request->input('amount'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.commissionrank.index')->with('success', __('commissionrank.commissionrank_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding commissionrank: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified commissionrank.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('commissionrank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $commissionrank = Commissionrank::where('commissionrank_id', $id)->first();
        if (!$commissionrank) {
            return redirect()->back()->with('error', __('commissionrank.no_data_found'));
        }
        return view('module.commissionrank.edit', compact('commissionrank'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('commissionrank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_commissionrank = Commissionrank::where('commissionrank_id', $id)->first();
        if (!$tbl_commissionrank) {
            return redirect()->back()->with('error', __('commissionrank.no_data_found'));
        }

        $validator = Validator::make($request->all(), [
            'rank' => 'required|integer',
            'amount' => 'required|numeric',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_commissionrank->update([
                'rank' => $request->input('rank'),
                'amount' => $request->input('amount'),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.commissionrank.index')->with('success', __('commissionrank.commissionrank_updated_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating manager: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('commissionrank_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_commissionrank = Commissionrank::where('commissionrank_id', $id)->first();
        if (!$tbl_commissionrank) {
            return redirect()->back()->with('error', __('commissionrank.no_data_found'));
        }

        try {
            $tbl_commissionrank->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.commissionrank.index')->with('success', __('commissionrank.commissionrank_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting commissionrank: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
