<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gameplatform;
use App\Models\Gameplatformaccess;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Access;

class GameController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('game_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Game::with('Gameplatform', 'Provider', 'gameType');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('game_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('game_desc', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('icon', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('api', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('gameplatform_id')) {
                $query->where('gameplatform_id', $request->input('gameplatform_id'));
            }
            if ($request->filled('provider_id')) {
                $query->where('provider_id', $request->input('provider_id'));
            }
            if ($request->filled('gametype_id')) {
                $query->where('gametype_id', $request->input('gametype_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $games = $query
                ->orderBy('created_on', 'desc')
                ->orderBy('game_id', 'desc')
                ->paginate(10)
                ->appends($request->all());
            $gameplatforms = Gameplatform::where('status', 1)
                    ->where('delete', 0)
                    ->orderBy('gameplatform_name')
                    ->get();
            $providers = Provider::where('status', 1)
                    ->where('delete', 0)
                    ->orderBy('provider_name')
                    ->get();
            $gametypes = Gametype::where('status', 1)
                    ->where('delete', 0)
                    ->orderBy('type_name')
                    ->get();
            return view('module.game.list', 
                [
                    'games' => $games, 
                    'gameplatforms' => $gameplatforms, 
                    'providers' => $providers, 
                    'gametypes' => $gametypes, 
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching game list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('game_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $tbl_provider = Provider::where('status', 1 )
                                    ->where('delete', 0 )
                                    ->get();
            if (!$tbl_provider) {
                return redirect()->route('dashboard')->with('error', __('provider.no_data_found'));
            }
            $message = "";
            foreach ($tbl_provider as $key => $provider) {
                $response = \Gamehelper::gamelist( $provider );
                if ( !$response['status'] ) {
                    $message .= $provider->provider_name." : ".$response['error']."<br>";
                    continue;
                }
                if ( empty($response['data']) ) {
                    $message .= $provider->provider_name." : ".__('game.no_data_found')."<br>";
                    continue;
                }
                $allgames = $response['data'];
                foreach ($allgames as $key => $game) {
                    $tbl_game = Game::where('gameplatform_id', $game['gameplatform_id'])
                                    ->where('provider_id', $game['provider_id'] )
                                    ->where('gametarget_id', $game['gametarget_id'] )
                                    ->first();
                    if ($tbl_game) {
                        continue;
                    }
                    // $tbl_game = Game::create([
                    //     'gameplatform_id' => $game->gameplatform_id,
                    //     'game_name' => $game->game_name,
                    //     'provider_id' => $game->provider_id,
                    //     'gametarget_id' => $game->gametarget_id,
                    //     'type' => $game->type,
                    //     'status' => 0,
                    //     'delete' => 0,
                    //     'created_on' => now(),
                    //     'updated_on' => now(),
                    // ]);
                    $message .= $provider->provider_name." : ".
                                __('game.game_added_successfully',  ['game_name' => $game['game_name']])."<br>";
                }
            }
            return redirect()->route('admin.game.index')->with('success', $message);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating game: " . $e->getMessage());
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('game_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $game = Game::where('game_id', $id)
                    ->with('Gameplatform', 'Provider', 'gameType')
                    ->first();
        if (!$game) {
            return redirect()->route('admin.game.index')->with('error', __('game.no_data_found'));
        }
        $gametypes = Gametype::where('status', 1)
                ->where('delete', 0)
                ->orderBy('type_name')
                ->get();
        $gameplatform_ids = Gameplatformaccess::where( 'agent_id', $authorizedUser->agent_id )
                ->where('can_use', 1)
                ->where('status', 1)
                ->where('delete', 0)
                ->pluck('gameplatform_id')
                ->toArray();
        $gameplatforms = Gameplatform::where('status', 1)
                ->where('delete', 0)
                ->whereIn('gameplatform_id', $gameplatform_ids)
                ->orderBy('gameplatform_name')
                ->get();
        return view('module.game.edit', compact('game', 'gametypes', 'gameplatforms'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('game_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $game = Game::where('game_id', $id)->first();
        if (!$game) {
            return redirect()->back()->with('error', __('no_data_found'));
        }

        $validator = Validator::make($request->all(), [
            'game_name' => ['required', 'string', 'max:100'],
            'game_desc' => 'nullable|string|max:10000',
            'gameplatform_id' => 'required|integer',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'icon_zh' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'api' => 'nullable|string|max:255',
            'type' => 'required|integer',
            'status' => 'nullable|in:1,0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_gameplatform = Gameplatform::where('status', 1)
                ->where('delete', 0)
                ->where('gameplatform_id', $request->input('gameplatform_id') )
                ->first();
            if ( !$tbl_gameplatform ) {
                return redirect()->back()->with('error', __('game.no_platform_found'));
            }
            $icon = $game->icon;
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ($game->icon && Storage::disk('public')->exists($game->icon)) {
                    Storage::disk('public')->delete($game->icon);
                }
                $sanitizedName = Str::slug($validator->validated()['game_name'], '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $icon = $request->file('icon')->storeAs('assets/img/game', $filename, 'public');
                $icon = $request->file('icon')->storeAs(
                    'assets/img/game/'.$tbl_gameplatform->gameplatform_name.'/en',
                    $filename,
                    'public'
                );
            }
            $icon_zh = $game->icon_zh;
            if ($request->hasFile('icon_zh') && $request->file('icon_zh')->isValid()) {
                if ($game->icon_zh && Storage::disk('public')->exists($game->icon_zh)) {
                    Storage::disk('public')->delete($game->icon_zh);
                }
                $sanitizedName = Str::slug($validator->validated()['game_name'], '_');
                $extension = $request->file('icon_zh')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $icon_zh = $request->file('icon_zh')->storeAs('assets/img/game', $filename, 'public');
                $icon_zh = $request->file('icon_zh')->storeAs(
                    'assets/img/game/'.$tbl_gameplatform->gameplatform_name.'/zh',
                    $filename,
                    'public'
                );
            }
            // $lang = app()->getLocale();
            $updateData = [
                'game_name' => $request->input('game_name'),
                'game_desc' => $request->input('game_desc') ?? null,
                'gameplatform_id' => $request->input('gameplatform_id'),
                'icon' => $icon,
                'icon_zh' => $icon_zh,
                'api' => $request->input('api') ?? null,
                'type' => $request->input('type'),
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            DB::table('tbl_game')->where('game_id', $id)->update($updateData);
            return redirect()->route('admin.game.index')->with('success', __('game.game_updated_successfully',  ['game_name' => $game->game_name]));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating game: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('game_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $game = DB::table('tbl_game')->where('game_id', $id)->first();
        if (!$game) {
            return redirect()->back()->with('error', __('no_data_found'));
        }

        try {
            DB::table('tbl_game')->where('game_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.game.index')->with('success', __('game.game_deleted_successfully', ['game_name' => $game->game_name]));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting game: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
