<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
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

class GameplatformController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('gameplatform_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            if ($authorizedUser->user_role === 'masteradmin') {
                $query = Gameplatform::query();
                if ($request->filled('search')) {
                    $searchTerm = $request->input('search');
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('gameplatform_id', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('gameplatform_name', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('api', 'LIKE', '%' . $searchTerm . '%');
                    });
                }
                if ($request->filled('status')) {
                    $query->where('status', $request->input('status'));
                }
                if ($request->filled('delete')) {
                    $query->where('delete', $request->input('delete'));
                }
                $query = queryBetweenDateEloquent($query, $request, 'created_on');
                $gameplatforms = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
                return view('module.gameplatform.list', ['gameplatforms' => $gameplatforms]);
            } else {
                $agent = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->where('agent_id', $authorizedUser->agent_id)
                                ->first();
                if ( !$agent ) {
                    return redirect()->back()->with('error', __('agent.no_data_found'));
                }
                $gameplatforms = Gameplatform::where('status', 1)
                    ->where('delete', 0)
                    ->get();
                foreach ($gameplatforms as $gameplatform) {
                    $gameplatformaccess = Gameplatformaccess::where( 'agent_id', $authorizedUser->agent_id )
                            ->where( 'gameplatform_id', $gameplatform->gameplatform_id )
                            ->where('delete', 0)
                            ->first();
                    $gameplatform->commission = $gameplatformaccess ? $gameplatformaccess->commission : 0.00;
                    $gameplatform->can_use = $gameplatformaccess ? $gameplatformaccess->can_use : 0;
                    $gameplatform->status = $gameplatformaccess ? $gameplatformaccess->status : 0;
                }
                return view('module.gameplatformaccess.edit', compact('agent', 'gameplatforms'));
            }
        } catch (\Exception $e) {
            Log::error("Error fetching gameplatform list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error') . ":" . $e->getMessage() );
        }
    }

    /**
     * Show the form for creating a new gameplatform.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gameplatform_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        return view('module.gameplatform.create', []);
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gameplatform_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'gameplatform_name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'api' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $iconPath = null;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                $sanitizedName = Str::slug($validator->validated()['gameplatform_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/gameplatform', $filename, 'public');
            }
            $tbl_gameplatform = Gameplatform::create([
                'gameplatform_name' => $request->input('gameplatform_name'),
                'icon' => $iconPath,
                'api' => $request->input('api') ?? null,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_agent = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->first();
            foreach ($tbl_agent as $key => $agent) {
                $tbl_gameplatformaccess = Gameplatformaccess::create([
                    'gameplatform_id' => $tbl_gameplatform->gameplatform_id,
                    'agent_id'        => $agent->agent_id,
                    'commission'      => $tbl_gameplatform->commission,
                    'can_use'         => 1,
                    'status'          => 1,
                    'delete'          => 0,
                    'created_on'      => now(),
                    'updated_on'      => now(),
                ]);
            }
            return redirect()->route('admin.gameplatform.index')->with('success', __('gameplatform.gameplatform_added_successfully',['gameplatform_name' => $request->input('gameplatform_name')]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding gameplatform: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified gameplatform.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gameplatform_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $gameplatform = Gameplatform::where('gameplatform_id', $id)->first();
        if (!$gameplatform) {
            return redirect()->route('admin.gameplatform.index')->with('error', __('messages.nodata'));
        }
        return view('module.gameplatform.edit', compact('gameplatform'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gameplatform_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_gameplatform = Gameplatform::where('gameplatform_id', $id)->first();
        if (!$tbl_gameplatform) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'gameplatform_name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'api' => 'nullable|string|max:255',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $iconPath = $tbl_gameplatform->icon;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ($tbl_gameplatform->icon && Storage::disk('public')->exists($tbl_gameplatform->icon)) {
                    Storage::disk('public')->delete($tbl_gameplatform->icon);
                }
                $sanitizedName = Str::slug($validator->validated()['gameplatform_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $iconPath = $request->file('icon')->storeAs('assets/img/gameplatform', $filename, 'public');
            }
            $tbl_gameplatform->update([
                'gameplatform_name' => $request->input('gameplatform_name'),
                'icon' => $iconPath,
                'api' => $request->input('api') ?? null,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ]);
            $tbl_gameplatform = $tbl_gameplatform->fresh();
            $tbl_gameplatformaccess = Gameplatformaccess::where('delete', 0 )
                ->where('gameplatform_id', $id )
                ->update([
                    'status'     => $request->filled('status') ? $request->input('status'): 0,
                    'updated_on' => now(),
                ]);
            return redirect()->route('admin.gameplatform.index')->with('success', __('gameplatform.gameplatform_updated_successfully', ['gameplatform_name' => $tbl_gameplatform->gameplatform_name]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating gameplatform: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('gameplatform_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_gameplatform = Gameplatform::where('gameplatform_id', $id)->first();
        if (!$tbl_gameplatform) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            $tbl_gameplatform->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);
            $tbl_gameplatform = $tbl_gameplatform->fresh();
            $tbl_gameplatformaccess = Gameplatformaccess::where('gameplatform_id', $id )
                ->update([
                    'status' => 0, // Set status to inactive
                    'delete' => 1, // Mark as deleted
                    'updated_on' => now(),
                ]);

            return redirect()->route('admin.gameplatform.index')->with('success', __('gameplatform.gameplatform_deleted_successfully', ['gameplatform_name' => $gameplatform->gameplatform_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting gameplatform: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
