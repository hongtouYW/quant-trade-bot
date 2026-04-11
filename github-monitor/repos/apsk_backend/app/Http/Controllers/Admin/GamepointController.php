<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gamepoint;
use App\Models\Gamemember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class GamepointController extends Controller
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('gamepoint_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        try {
            $query = Gamepoint::query()
                              ->with('Shop','Gamemember','Gamemember.Member',
                                    'Gamemember.Provider','Gamemember.Gameplatform');
            if ($authorizedUser->user_role !== 'masteradmin') {
                $query->where('agent_id', $authorizedUser->agent_id);
            }
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('gamepoint_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('shop_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('gamemember_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('orderid', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('type', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('ip', 'LIKE', '%' . $searchTerm . '%');
                    // 🔗 Shop relation
                    $q->orWhereHas('Shop', function ($sh) use ($searchTerm) {
                        $sh->where('shop_name', 'LIKE', "%{$searchTerm}%");
                    });
                    // 🔗 Gamemember relation
                    $q->orWhereHas('Gamemember', function ($gm) use ($searchTerm) {
                        $gm->where(function ($sub) use ($searchTerm) {
                            $sub->where('loginId', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('name', 'LIKE', "%{$searchTerm}%");
                        })
                        ->orWhereHas('Member', function ($p) use ($searchTerm) {
                            $p->where('member_name', 'LIKE', "%{$searchTerm}%");
                        })
                        ->orWhereHas('Provider', function ($p) use ($searchTerm) {
                            $p->where('provider_name', 'LIKE', "%{$searchTerm}%");
                        })
                        ->orWhereHas('Gameplatform', function ($gp) use ($searchTerm) {
                            $gp->where('gameplatform_name', 'LIKE', "%{$searchTerm}%");
                        });
                    });
                });
            }
            // Type filter
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            // Agent filter (only for masteradmin)
            if ($request->filled('agent_name')) {
                $query->whereHas('Agent', function ($q) use ($request) {
                    $q->where('agent_name', 'LIKE', '%' . $request->agent_name . '%');
                });
            }
            // Start On datetime filter
            if ($request->filled('start_from') && $request->filled('start_to')) {
                $query->whereBetween('start_on', [
                    Carbon::parse($request->start_from)->startOfSecond(),
                    Carbon::parse($request->start_to)->endOfSecond(),
                ]);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $gamepoints = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            return view('module.gamepoint.list', ['gamepoints' => $gamepoints]);
        } catch (\Exception $e) {
            Log::error("Error fetching gamepoint list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new gamepoint.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamepoint_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $gamemembers = Gamemember::where('status', 1)
              ->where('delete', 0)
              ->orderBy('name')
              ->get();
        return view('module.gamepoint.create', compact('gamemembers'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamepoint_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'gamemember_id' => 'required|integer',
            'type' => 'required|string|max:20|in:bonus,reward,reload,withdraw',
            'ip' => 'required|string|max:20',
            'amount' => 'required|numeric',
            'start_on' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        try {
            $balance = GetGameMemberIDBalance($request->input('gamemember_id')) ?? 0.00;
            $gamemember = DB::table('tbl_gamemember')->where('gamemember_id', $request->input('gamemember_id') )->first();
            $member_id = GetGameMemberID( $gamemember->gamemember_id );
            $amount = (float) $request->input('amount');
            $actualamount = $request->input('type') === "withdraw" ? ReverseDecimal($amount) : $amount;
            if ( $balance + $actualamount < 0 ) {
                return redirect()->back()->withInput()->with('error', __('messages.insufficient') );
            }
            $id = DB::table('tbl_gamepoint')->insertGetId([
                'gamemember_id' => $request->input('gamemember_id'),
                'type' => $request->input('type'),
                'ip' => $request->input('ip'),
                'amount' => $request->input('amount') ?? 0.00,
                'start_on' => $request->filled('start_on') ? Carbon::parse($request->input('start_on'))->startOfDay() : now(),
                'status' => 0, // pending
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return redirect()->route('admin.gamepoint.index')->with('success', __('gamepoint.gamepoint_added_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding gamepoint: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for editing the specified gamepoint.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamepoint_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $gamepoint = DB::table('tbl_gamepoint')->where('gamepoint_id', $id)->first();
        if (!$gamepoint) {
            return redirect()->route('admin.gamepoint.index')->with('error', 'gamepoint not found.');
        }
        return view('module.gamepoint.edit', compact('gamepoint'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('gamepoint_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $gamepoint = DB::table('tbl_gamepoint')->where('gamepoint_id', $id)->first();
        if (!$gamepoint) {
            return redirect()->back()->with('error', 'gamepoint not found.');
        }

        $validator = Validator::make($request->all(), [
            'gamemember_id' => 'required|integer',
            'type' => 'required|string|max:20|in:bonus,reward,reload,withdraw',
            'ip' => 'required|string|max:20',
            'amount' => 'required|numeric',
            'status' => 'required|integer|in:-1,1,0', //fail pending success
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $balance = GetGameMemberIDBalance($request->input('gamemember_id')) ?? 0.00;
            $gamemember = DB::table('tbl_gamemember')->where('gamemember_id', $request->input('gamemember_id') )->first();
            $member_id = GetGameMemberID( $gamemember->gamemember_id );
            $amount = (float) $request->input('amount');
            $actualamount = $request->input('type') === "withdraw" ? ReverseDecimal($amount) : $amount;
            if ( $balance + $actualamount < 0 ) {
                return redirect()->back()->withInput()->with('error', __('messages.insufficient') );
            }
            $updateData = [
                'gamemember_id' => $request->input('gamemember_id'),
                'type' => $request->input('type'),
                'ip' => $request->input('ip'),
                'amount' => $request->input('amount'),
                'before_balance' => $balance,
                'after_balance' => $request->input('status') === 1 ? $balance + $amount : $balance,
                'end_on' => $request->input('status') === 0 ? now() : null,
                'status' => $request->input('status'), //fail pending success
                'updated_on' => now(),
            ];
            DB::table('tbl_gamepoint')->where('gamepoint_id', $id)->update($updateData);
            if ( $request->input('status') === 1 ) {
                CountGameMemberBalance( $gamemember->gamemember_id, $actualamount);
                CountMemberBalance( $member_id, ReverseDecimal($actualamount) );
            }
            return redirect()->route('admin.gamepoint.index')->with('success', __('gamepoint.gamepoint_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating gamepoint: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('gamepoint_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $gamepoint = DB::table('tbl_gamepoint')->where('gamepoint_id', $id)->first();
        if (!$gamepoint) {
            return redirect()->back()->with('error', 'gamepoint not found.');
        }

        try {
            DB::table('tbl_gamepoint')->where('gamepoint_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.gamepoint.index')->with('success', __('gamepoint.gamepoint_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting gamepoint: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }
}
