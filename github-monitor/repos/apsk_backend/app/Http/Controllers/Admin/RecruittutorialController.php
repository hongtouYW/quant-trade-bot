<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recruittutorial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecruittutorialController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('recruittutorial_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Recruittutorial::query();
            if ($request->filled('agent_name')) {
                $agentName = $request->input('agent_name');

                $query->whereHas('Agent', function ($q) use ($agentName) {
                    $q->where('agent_name', 'LIKE', "%{$agentName}%");
                });
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('desc', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('slogan', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('language')) {
                $query->where('lang', 'LIKE', $request->input('language'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $recruittutorials = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.recruittutorial.list', ['recruittutorials' => $recruittutorials]);
        } catch (\Exception $e) {
            Log::error("Error fetching recruittutorial list: " . $e->getMessage());
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('recruittutorial_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $langs = config('languages.supported');
        return view('module.recruittutorial.create', compact('langs'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('recruittutorial_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:10000',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'slogan' => 'nullable|string|max:50',
            'desc' => 'nullable|string|max:10000',
            'language' => 'required|string',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            $picture = null;
            if ($request->hasFile('picture') && $request->file('picture')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['title'], '_');
                $extension = $request->file('picture')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $picture = $request->file('picture')->storeAs(
                    'assets/img/recruit/tutorial/'.$request->input('language'),
                    $filename,
                    'public'
                );
            }
            $userId = DB::table('tbl_recruittutorial')->insertGetId([
                'title' => $request->input('title'),
                'picture' => $picture,
                'desc' => $request->input('desc') ?? null,
                'slogan' => $request->input('slogan') ?? null,
                'lang' => $request->input('language'),
                'agent_id' => $authorizedUser->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.recruittutorial.index')->with('success', __('recruittutorial.recruittutorial_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding recruittutorial: " . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to add recruittutorial: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified recruittutorial.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('recruittutorial_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $recruittutorial = DB::table('tbl_recruittutorial')->where('recruittutorial_id', $id)->first();
        if (!$recruittutorial) {
            return redirect()->route('admin.recruittutorial.index')->with('error', __('messages.nodata'));
        }
        $langs = config('languages.supported');
        return view('module.recruittutorial.edit', compact('recruittutorial', 'langs'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('recruittutorial_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $recruittutorial = DB::table('tbl_recruittutorial')->where('recruittutorial_id', $id)->first();
        if (!$recruittutorial) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:10000',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'slogan' => 'nullable|string|max:50',
            'desc' => 'nullable|string|max:10000',
            'language' => 'required|string',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $picture = $recruittutorial->picture;
            if ($request->hasFile('picture') && $request->file('picture')->isValid()) {
                if ( $recruittutorial->picture ) {
                    if ($recruittutorial->picture && Storage::disk('public')->exists($recruittutorial->picture)) {
                        Storage::disk('public')->delete($recruittutorial->picture);
                    }
                }
                $sanitizedName = Str::slug($validator->validated()['title'], '_');
                $extension = $request->file('picture')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $picture = $request->file('picture')->storeAs(
                    'assets/img/recruit/tutorial/'.$request->input('language'),
                    $filename,
                    'public'
                );
            }
            $updateData = [
                'title' => $request->input('title'),
                'picture' => $picture,
                'desc' => $request->input('desc') ?? null,
                'slogan' => $request->input('slogan') ?? null,
                'lang' => $request->input('language'),
                'agent_id' => $authorizedUser->agent_id,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_recruittutorial')->where('recruittutorial_id', $id)->update($updateData);
            return redirect()->route('admin.recruittutorial.index')->with('success', __('recruittutorial.recruittutorial_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating recruittutorial: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('recruittutorial_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $recruittutorial = DB::table('tbl_recruittutorial')->where('recruittutorial_id', $id)->first();
        if (!$recruittutorial) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_recruittutorial')->where('recruittutorial_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.recruittutorial.index')->with('success', __('recruittutorial.recruittutorial_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting recruittutorial: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
