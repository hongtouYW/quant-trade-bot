<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('application_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Application::query();
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('application_name', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('platform')) {
                $query->where('platform', $request->input('platform'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $applications = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $applications->getCollection()->transform(function ($application) {
                $application->is_use = $application->status;
                return $application;
            });
            $types = ['manager','shop','member'];
            $platforms = ['ios','android'];
            return view('module.application.list', 
                [
                    'applications' => $applications, 
                    'types' => $types, 
                    'platforms' => $platforms 
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching application list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new application.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('application_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $types = ['member', 'shop', 'manager'];
        $platforms = ['android', 'ios'];
        return view('module.application.create', compact('types', 'platforms'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('application_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'application_name' => 'required|string|max:255|unique:tbl_application,application_name',
            'type' => 'required|string|max:255',
            'platform' => 'required|string|max:255',
            'version' => 'required|string|max:255',
            'latest_version' => 'nullable|string|max:255',
            'minimun_version' => 'nullable|string|max:255',
            'download_url' => 'nullable|url|max:500',
            'changelog' => 'nullable|string|max:10000',
            'force_update' => 'nullable|in:1,0',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_application = Application::create([
                'application_name' => $request->input('application_name'),
                'type' => $request->input('type'),
                'platform' => $request->input('platform'),
                'version' => $request->input('version'),
                'latest_version' => $request->input('latest_version'),
                'minimun_version' => $request->input('minimun_version'),
                'download_url' => $request->input('download_url'),
                'changelog' => $request->input('changelog') ?? null,
                'force_update' => $request->filled('force_update') ? $request->input('force_update'): 0,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ($request->input('status') == 1) {
                Application::where('type', $tbl_application->type)
                    ->where('platform', $tbl_application->platform)
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->where('application_id', '!=', $tbl_application->application_id)
                    ->update([
                        'status' => 0,
                        'updated_on' => now(),
                    ]);
            }
            return redirect()->route('admin.application.index')->with('success', 
                __('application.application_added_successfully',
                ['application_name'=>$tbl_application->application_name])
            );

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding application: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified application.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('application_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $application = DB::table('tbl_application')->where('application_id', $id)->first();
        if (!$application) {
            return redirect()->route('admin.application.index')->with('error', __('messages.nodata'));
        }
        $types = ['member', 'shop', 'manager'];
        $platforms = ['android', 'ios'];
        return view('module.application.edit', compact('application', 'platforms', 'types'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('application_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_application = Application::where('application_id', $id)->first();
        if (!$tbl_application) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:255',
            'platform' => 'required|string|max:255',
            'version' => 'required|string|max:255',
            'latest_version' => 'nullable|string|max:255',
            'minimun_version' => 'nullable|string|max:255',
            'download_url' => 'nullable|url|max:500',
            'changelog' => 'nullable|string|max:10000',
            'force_update' => 'nullable|in:1,0',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_application->update([
                'type' => $request->input('type'),
                'platform' => $request->input('platform'),
                'version' => $request->input('version'),
                'latest_version' => $request->input('latest_version'),
                'minimun_version' => $request->input('minimun_version'),
                'download_url' => $request->input('download_url'),
                'changelog' => $request->input('changelog') ?? null,
                'force_update' => $request->filled('force_update') ? $request->input('force_update'): 0,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ]);
            $tbl_application = $tbl_application->fresh();
            if ($request->input('status') == 1) {
                Application::where('type', $tbl_application->type)
                    ->where('platform', $tbl_application->platform)
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->where('application_id', '!=', $tbl_application->application_id)
                    ->update([
                        'status' => 0,
                        'updated_on' => now(),
                    ]);
            }
            return redirect()->route('admin.application.index')->with('success', 
                __('application.application_updated_successfully', 
                ['application_name'=>$tbl_application->application_name])
            );
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating application: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('application_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_application = Application::where('application_id', $id)->first();
        if (!$tbl_application) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            $tbl_application->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);
            $tbl_application = $tbl_application->fresh();
            return redirect()->route('admin.application.index')->with('success', 
                __('application.application_deleted_successfully', 
                ['application_name'=>$tbl_application->application_name])
            );
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting application: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
