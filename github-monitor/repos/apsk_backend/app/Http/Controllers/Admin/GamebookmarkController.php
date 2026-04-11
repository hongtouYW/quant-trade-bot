<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gamebookmark;
use App\Models\Game;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GamebookmarkController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('gamebookmark_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = DB::table('tbl_gamebookmark');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('gamebookmark_name', 'LIKE', '%' . $searchTerm . '%');
                });
                $member_id = GetMemberID($searchTerm);
                if ( $member_id ) {
                    $query->orWhere('member_id', $member_id);
                }
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDate($query, $request, 'created_on');
            $gamebookmarks = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.gamebookmark.list', ['gamebookmarks' => $gamebookmarks]);
        } catch (\Exception $e) {
            Log::error("Error fetching gamebookmark list: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to retrieve gamebookmark list: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new game.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamebookmark_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $games = Game::where('status', 1)
              ->where('delete', 0)
              ->orderBy('game_name')
              ->get();
        $members = Member::where('status', 1)
              ->where('delete', 0)
              ->orderBy('member_name')
              ->get();
        return view('module.gamebookmark.create', compact('games','members'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamebookmark_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'gamebookmark_name' => 'required|string|max:255',
            'game_id' => 'required|integer',
            'member_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $userId = DB::table('tbl_gamebookmark')->insertGetId([
                'gamebookmark_name' => $request->input('gamebookmark_name'),
                'game_id' => $request->input('game_id'),
                'member_id' => $request->input('member_id'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.gamebookmark.index')->with('success', __('gamebookmark.gamebookmark_added_successfully', ['gamename' => $request->input('game_name')]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding gamebookmark: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified game.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamebookmark_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $game = DB::table('tbl_gamebookmark')->where('gamebookmark_id', $id)->first();
        if (!$game) {
            return redirect()->route('admin.gamebookmark.index')->with('error', __('messages.nodata'));
        }
        $games = Game::where('status', 1)
              ->where('delete', 0)
              ->orderBy('game_name')
              ->get();
        $members = Member::where('status', 1)
              ->where('delete', 0)
              ->orderBy('member_name')
              ->get();
        return view('module.gamebookmark.edit', compact('games', 'members'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamebookmark_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $game = DB::table('tbl_gamebookmark')->where('game_name', $id)->first();
        if (!$game) {
            return redirect()->back()->with('error', __('messages.api_error'));
        }

        $validator = Validator::make($request->all(), [
            'gamebookmark_name' => 'nullable|string|max:255',
            'game_id' => 'required|integer',
            'member_id' => 'required|integer',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'gamebookmark_name' => $request->input('gamebookmark_name'),
                'game_id' => $request->input('game_id'),
                'member_id' => $request->input('member_id'),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_gamebookmark')->where('gamebookmark_id', $id)->update($updateData);
            return redirect()->route('admin.gamebookmark.index')->with('success', __('gamebookmark.gamebookmark_updated_successfully', ['gamename' => $request->input('game_name')]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating gamebookmark: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('gamebookmark_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $game = DB::table('tbl_gamebookmark')->where('gamebookmark_id', $id)->first();
        if (!$game) {
            return redirect()->back()->with('error', 'gamebookmark not found.');
        }

        try {
            DB::table('tbl_gamebookmark')->where('gamebookmark_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.gamebookmark.index')->with('success', __('gamebookmark.gamebookmark_deleted_successfully', ['gamename' => $game->game_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting gamebookmark: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
