<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Noticepublic;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NoticepublicController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('noticepublic_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Noticepublic::query()
                            ->with('Agent');
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
                    $q->where('title', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('desc', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            // Notice Type
            if ($request->filled('recipient_type')) {
                if ( $request->input('recipient_type') !== 'all' ) {
                    $query->where('recipient_type', $request->input('recipient_type'));
                }
            }
            // Language
            if ($request->filled('language')) {
                $query->where('lang', $request->input('language'));
            }
            // Start On datetime range
            if ($request->filled('start_on_from') || $request->filled('start_on_to')) {
                $from = $request->filled('start_on_from')
                    ? Carbon::parse($request->start_on_from)
                    : Carbon::parse($request->start_on_to)->startOfDay();

                $to = $request->filled('start_on_to')
                    ? Carbon::parse($request->start_on_to)
                    : Carbon::parse($request->start_on_from)->endOfDay();

                $query->whereBetween('start_on', [$from, $to]);
            }
            // End On datetime range
            if ($request->filled('end_on_from') || $request->filled('end_on_to')) {
                $from = $request->filled('end_on_from')
                    ? Carbon::parse($request->end_on_from)
                    : Carbon::parse($request->end_on_to)->startOfDay();

                $to = $request->filled('end_on_to')
                    ? Carbon::parse($request->end_on_to)
                    : Carbon::parse($request->end_on_from)->endOfDay();

                $query->whereBetween('end_on', [$from, $to]);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $noticepublics = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $agents = [];
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->get();
            }
            $langs = config('languages.supported');
            $recipient_types = ['all','manager','shop','member'];
            return view(
                'module.noticepublic.list', 
                [
                    'noticepublics' => $noticepublics,
                    'langs' => $langs,
                    'agents' => $agents,
                    'recipient_types' => $recipient_types,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching noticepublic list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new performance.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('noticepublic_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $langs = config('languages.supported');
        $recipient_types = ['all','manager','shop','member'];
        $agents = [];
        if ($authorizedUser->user_role === 'masteradmin') {
            $agents = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->get();
        }
        return view('module.noticepublic.create', compact('langs','recipient_types','agents'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('noticepublic_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'recipient_type' => 'required|string|max:50|in:all,member,manager,shop', 
            'title' => 'required|string|max:50',
            'desc' => 'nullable|string|max:10000',
            'language' => 'required|string',
            'start_on' => 'nullable|date',
            'end_on' => 'nullable|date|after:start_on',
            'agent_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $agent_id = $authorizedUser->user_role === 'masteradmin' ? 
                        $request->input('agent_id') :
                        $authorizedUser->agent_id;
            $tbl_noticepublic = Noticepublic::create([
                'recipient_type' => $request->input('recipient_type'),
                'title' => $request->input('title'),
                'desc' => $request->input('desc') ?? null,
                'lang' => $request->input('language'),
                'start_on' => $request->filled('start_on') ? 
                    Carbon::parse($request->input('start_on'))->startOfDay() : 
                    now()->startOfDay(),
                'end_on' => $request->filled('end_on') ? 
                    Carbon::parse($request->input('end_on'))->endOfDay() : 
                    now()->endOfDay(),
                'agent_id' => $agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.noticepublic.index')->with(
                'success', 
                __(
                    'noticepublic.noticepublic_added_successfully',
                    [
                        'title'=>$request->input('title'),
                    ],
                )
            );

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding noticepublic: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified noticepublic.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('noticepublic_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $noticepublic = DB::table('tbl_noticepublic')->where('notice_id', $id)->first();
        if (!$noticepublic) {
            return redirect()->route('admin.noticepublic.index')->with('error', __('noticepublic.no_data_found'));
        }
        $langs = config('languages.supported');
        $recipient_types = ['all','manager','shop','member'];
        $agents = [];
        if ($authorizedUser->user_role === 'masteradmin') {
            $agents = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->get();
        }
        return view('module.noticepublic.edit', compact('noticepublic', 'langs', 'recipient_types', 'agents'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('noticepublic_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_noticepublic = Noticepublic::where('notice_id', $id)->first();
        if (!$tbl_noticepublic) {
            return redirect()->back()->with('error', __('noticepublic.no_data_found'));
        }

        $validator = Validator::make($request->all(), [
            'recipient_type' => 'required|string|max:50|in:all,member,manager,shop',
            'title' => 'required|string|max:50',
            'desc' => 'nullable|string|max:10000',
            'language' => 'required|string',
            'start_on' => 'nullable|date',
            'end_on' => 'nullable|date|after:start_on',
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
            $tbl_noticepublic->update([
                'recipient_type' => $request->input('recipient_type'),
                'title' => $request->input('title'),
                'desc' => $request->input('desc') ?? null,
                'lang' => $request->input('language'),
                'start_on' => $request->filled('start_on') ? 
                    Carbon::parse($request->input('start_on'))->startOfDay() : 
                    now()->startOfDay(),
                'end_on' => $request->filled('end_on') ? 
                    Carbon::parse($request->input('end_on'))->endOfDay() : 
                    now()->endOfDay(),
                'agent_id' => $agent_id,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.noticepublic.index')->with(
                'success', 
                __(
                    'noticepublic.noticepublic_updated_successfully',
                    [
                        'title'=>$request->input('title'),
                    ],
                )
            );
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating noticepublic: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('noticepublic_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_noticepublic = Noticepublic::where('notice_id', $id)->first();
        if (!$tbl_noticepublic) {
            return redirect()->back()->with('error', __('noticepublic.no_data_found'));
        }

        try {
            $tbl_noticepublic->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.noticepublic.index')->with(
                'success', 
                __(
                    'noticepublic.noticepublic_deleted_successfully',
                    [
                        'title'=>$noticepublic->title
                    ]
                )
            );

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting noticepublic: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
