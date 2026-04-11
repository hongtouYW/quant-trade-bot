<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Member;
use App\Models\Gamemember;
use App\Models\Gameplatform;
use App\Models\Gamelog;
use App\Models\Provider;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;

class GamememberController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('gamemember_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $tbl_member = Member::query();
            if ($request->filled('agent_id')) {
                $tbl_member->where('agent_id', $request->input('agent_id') );
            }
            $tbl_member = $tbl_member->get();
            $member_ids = $tbl_member->pluck('member_id')->toArray();
            if (empty($member_ids)) {
                $gamemembers = new LengthAwarePaginator(
                    [], 0, 10,
                    $request->input('page', 1),
                    ['path' => $request->url(), 'query' => $request->query()]
                );
                return view('module.gamemember.list', ['gamemembers' => $gamemembers]);
            }
            $query = Gamemember::with('Member','Member.Agent','Shop','Gameplatform','Provider')
                               ->whereIn('member_id', $member_ids);
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('gamemember_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('member_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('shop_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('login', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('loginId', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('name', 'LIKE', '%' . $searchTerm . '%');
                    // 🔗 Member relation
                    $q->orWhereHas('Member', function ($mq) use ($searchTerm) {
                        $mq->where('member_name', 'LIKE', "%{$searchTerm}%");
                    });
                    // 🔗 Shop relation
                    $q->orWhereHas('Shop', function ($mq) use ($searchTerm) {
                        $mq->where('shop_name', 'LIKE', "%{$searchTerm}%");
                    });
                    // // 🔗 Gameplatform relation
                    // $q->orWhereHas('Provider', function ($mq) use ($searchTerm) {
                    //     $mq->where('provider_name', 'LIKE', "%{$searchTerm}%");
                    // });
                    // // 🔗 Provider relation
                    // $q->orWhereHas('Gameplatform', function ($mq) use ($searchTerm) {
                    //     $mq->where('gameplatform_name', 'LIKE', "%{$searchTerm}%");
                    // });
                });
            }

            // Game Platform
            if ($request->filled('gameplatform_id')) {
                $query->where('gameplatform_id', $request->gameplatform_id);
            }
            // Provider
            if ($request->filled('provider_id')) {
                $query->where('provider_id', $request->provider_id);
            }
            // Created datetime range
            if ($request->filled('created_from') || $request->filled('created_to')) {
                $from = $request->filled('created_from')
                    ? \Carbon\Carbon::parse($request->created_from)
                    : \Carbon\Carbon::parse($request->created_to)->startOfDay();

                $to = $request->filled('created_to')
                    ? \Carbon\Carbon::parse($request->created_to)
                    : \Carbon\Carbon::parse($request->created_from)->endOfDay();

                $query->whereBetween('created_on', [$from, $to]);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $gamemembers = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $agents = [];
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->get();
            }
            $gameplatforms = Gameplatform::where('status', 1)->get();
            $providers = Provider::where('status', 1)->get();
            return view(
                'module.gamemember.list', 
                [
                    'gamemembers' => $gamemembers, 
                    'gameplatforms' => $gameplatforms, 
                    'providers' => $providers, 
                    'agents' => $agents
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching gamemember list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error') . ":" . $e->getMessage() );
        }
    }

    /**
     * Show the form for creating a new gamemember.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamemember_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $members = Member::where('status', 1)
              ->where('delete', 0)
              ->orderBy('member_name')
              ->get();
        $gameplatform = Gameplatform::where('status', 1)
              ->where('delete', 0)
              ->orderBy('gameplatform_name')
              ->get();
        $games = Game::where('status', 1)
              ->where('delete', 0)
              ->orderBy('game_name')
              ->get();
        return view('module.gamemember.create', compact('games', 'members'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamemember_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'provider_id' => 'required|integer',
            'game_id' => 'required|integer',
            'login ' => 'required|string|max:255|unique:tbl_gamemember,login',
            'pass' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $tbl_provider = Provider::where( 'provider_id', $request->input('provider_id') )
                ->where( 'status', 1 )
                ->where( 'delete', 0 )
                ->with('Gameplatform')
                ->first();
            if (!$tbl_provider) {
                return redirect()->route('admin.gamemember.index')->with('error', __('provider.no_data_found'));
            }
            $player_name = generatePlayer();
            $response = \Gamehelper::create($tbl_provider, $player_name);
            if (!$response['status']) {
                return redirect()->route('admin.gamemember.index')->with('error', __($response['message']));
            }
            $userId = DB::table('tbl_gamemember')->insertGetId([
                'member_id' => $request->input('member_id'),
                'gameplatform_id' => $tbl_provider->gameplatform_id,
                'provider_id' => $request->input('provider_id'),
                'game_id' => $request->input('game_id'),
                'uid' => $response['uid'],
                'loginId' => $response['loginId'],
                'login' => $response['password'] ? $response['loginId'] : null,
                'pass' => $response['password'] ? encryptPassword( $response['password'] ) : null,
                'name' => $player_name,
                'paymentpin' => $response['paymentpin'],
                'balance' => 0.00,
                'has_balance' => 0,
                'status' => 0,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.gamemember.index')->with('success', __('gamemember.gamemember_added_successfully', ));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding gamemember: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified gamemember.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamemember_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $gamemember = DB::table('tbl_gamemember')->where('gamemember_id', $id)->first();
        if (!$gamemember) {
            return redirect()->route('admin.gamemember.index')->with('error', __('messages.nodata'));
        }
        $games = Game::where('status', 1)
              ->where('delete', 0)
              ->orderBy('game_name')
              ->get();
        $members = Member::where('status', 1)
              ->where('delete', 0)
              ->orderBy('member_name')
              ->get();
        $gameplatform = Gameplatform::where('status', 1)
              ->where('delete', 0)
              ->orderBy('gameplatform_name')
              ->get();
        return view('module.gamemember.edit', compact('gamemember', 'games', 'members'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamemember_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $gamemember = DB::table('tbl_gamemember')->where('gamemember_id', $id)->first();
        if (!$gamemember) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'game_id' => 'required|integer',
            'login ' => 'required|string|max:255|unique:tbl_gamemember,login',
            'pass' => 'required|string|max:255',
            'status' => 'required|in:-1,0,1', //pending,approve,reject
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'member_id' => $request->input('member_id'),
                'game_id' => $request->input('game_id'),
                'login' => $request->input('login'),
                'pass' => encryptPassword( $request->input('pass') ),
                'status' => $request->input('status'),
                'updated_on' => now(),
            ];
            DB::table('tbl_gamemember')->where('gamemember_id', $id)->update($updateData);
            return redirect()->route('admin.gamemember.index')->with('success', __('gamemember.gamemember_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating gamemember: " . $e->getMessage());
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

        $area = DB::table('tbl_gamemember')->where('gamemember_id', $id)->first();
        if (!$area) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_gamemember')->where('gamemember_id', $id)->update([
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

    public function syncgamelog($gamemember_id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamemember_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        try {
            $DaysAgo = Carbon::now()->startOfDay();
            $tbl_player = Gamemember::with('Member')
                                    ->where('delete', 0)
                                    ->where('gamemember_id', $gamemember_id)
                                    ->whereNotNull('lastlogin_on')
                                    ->where('lastlogin_on', '>=', $DaysAgo) //prevent lag old record ignore
                                    ->where(function ($q) {
                                        $q->whereNull('lastsync_on') // case 1: never synced
                                        ->orWhereColumn('lastlogin_on', '>=', 'lastsync_on'); // case 2: login after sync
                                    })
                                    ->first();
            if (!$tbl_player) {
                return response()->json([
                    'status' => true,
                    'message' => __('gamemember.no_data_found'),
                    'error' => __('gamemember.no_data_found'),
                    'code' => 400,
                ], 400);
            }
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $turnover = 0.00;
            $tbl_player->update([
                'lastsync_on' => Carbon::now()->subHour()->endOfHour(),
                'updated_on' => now(),
            ]);
            $tbl_player = $tbl_player->fresh();
            switch ($tbl_player->gameplatform_id) {
                case 1003: //Mega
                    $response = \Mega::syncgamelog( $tbl_player );
                    break;
                case 1004: //Jili
                    $response = \Jili::syncgamelog( $tbl_player );
                    break;
                case 1005: //Onehubx
                    $response = \Onehubx::syncgamelog( $tbl_player );
                    break;
                default:
                    return response()->json([
                        'status' => false,
                        'message' => __('game.no_platform_found'),
                        'error' => __('game.no_platform_found'),
                        'code' => 400,
                    ], 400);
                    break;
            }
            if ( !$response['status'] ) {
                return response()->json([
                    'status' => false,
                    'message' => __('gamelog.no_data_found')." : ".$response['error'],
                    'error' => __('gamelog.no_data_found')." : ".$response['error'],
                    'code' => 500,
                ], 500);
            }
            if ( empty( $response['data'] ) ) {
                return response()->json([
                    'status' => false,
                    'message' => __('gamelog.no_data_found'),
                    'error' => __('gamelog.no_data_found'),
                    'code' => 400,
                ], 400);
            }
            $response['data'] = collect($response['data'])
                ->sortBy('startdt')
                ->values()
                ->all();
            foreach( $response['data'] as $gamelog ) {
                Gamelog::firstOrCreate(
                    [
                        'gamemember_id' => $tbl_player->gamemember_id,
                        'gamelogtarget_id' => $gamelog['gamelogtarget_id'],
                    ],
                    $gamelog
                );
                // vip 改成打码量
                $turnover += $gamelog['betamount'];
            }
            if ( $turnover > 0 ) {
                // VIP Score
                $tbl_score = AddScore( $tbl_player->member, 'deposit', $turnover);
                // Commission
                $salestarget = AddCommission( $tbl_player->member, $turnover );
            }
            return response()->json([
                'status' => true,
                'message' => __('gamelog.sync_complete'),
                'error' => "",
                'data' => $console,
                'code' => 200,
            ], 200);
        } catch (\Exception $e) {
            Log::error('syncgamelog error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }
}
