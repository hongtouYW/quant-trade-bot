<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Gamelog;
use App\Models\Shop;
use App\Models\Shopcredit;
use App\Models\States;
use App\Models\Areas;
use App\Models\Bank;
use App\Models\Agent;
use App\Models\Credit;
use App\Models\Agentcredit;
use App\Models\VIP;
use App\Models\Performance;
use App\Models\Bankaccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class MemberController extends Controller
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

        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        try {
            $query = Member::query()
                            ->with('Areas','MyVip','Shop','Score','Agent');
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
                    $q->where('member_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('member_login', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('member_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('full_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('wechat', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('whatsapp', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('facebook', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('telegram', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('devicemeta', 'LIKE', "%{$searchTerm}%");
                });
            }
            if ($request->filled('shop_id')) {
                $query->where('shop_id', $request->input('shop_id'));
            }
            if ($request->filled('area_code')) {
                $query->where('area_code', $request->input('area_code'));
            }
            if ($request->filled('vip_id')) {
                $query->where('vip', $request->input('vip_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('delete')) {
                $query->where('delete', $request->input('delete'));
            }
            $query = queryBetweenDateEloquent($query, $request, 'created_on');
            $members = $query->orderBy('created_on', 'desc')->paginate(10)->appends($request->all());
            $agents = [];
            if ($authorizedUser->user_role === 'masteradmin') {
                $agents = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->get();
            }
            $shops = Shop::where('status', 1)
                         ->where('delete', 0);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $shops = $shops->where('agent_id', $authorizedUser->agent_id);
            }
            $shops = $shops->get();
            $areas = Areas::where('status', 1)
                            ->where('delete', 0)
                            ->get();
            $vips = VIP::where('status', 1)
                        ->where('delete', 0);
            if ($authorizedUser->user_role !== 'masteradmin') {
                $vips = $vips->where('agent_id', $authorizedUser->agent_id);
            }
            $vips = $vips->get();         
            return view(
                'module.member.list', 
                [
                    'members' => $members,
                    'shops' => $shops,
                    'areas' => $areas,
                    'vips' => $vips,
                    'agents' => $agents,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error fetching member list: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    /**
     * Show the form for creating a new member.
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $banks = Bank::where('status', 1)
            ->where('delete', 0)
            ->orderBy('bank_name')
            ->get();
        $shops = Shop::where('status', 1)
            ->where('delete', 0);
        if ($authorizedUser->user_role !== 'masteradmin') {
            $shops = $shops->where('agent_id', $authorizedUser->agent_id);
        }
        $shops = $shops->orderBy('shop_name');
        $shops = $shops->get();
        return view('module.member.create', compact('banks', 'shops'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $validator = Validator::make($request->all(), [
            'member_login' => 'required|string|max:255|unique:tbl_member,member_login',
            'member_pass' => 'required|string|min:8|max:255',
            'member_name' => 'required|string|max:255|unique:tbl_member,member_name',
            'full_name' => 'nullable|string|max:255',
            'phone_country' => 'required|string|max:255',
            'phone_number'  => 'required|digits_between:7,12',
            'balance' => 'nullable||numeric',
            'shop_id' => 'nullable|integer',
            'email' => 'nullable|string|max:255',
            'wechat' => 'nullable|string|max:255',
            'whatsapp' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'telegram' => 'nullable|string|max:255',
            'dob' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $phone = null;
        if ($request->filled('phone_number')) {
            $country = ltrim($request->input('phone_country'), '+'); // remove +
            $number = preg_replace(
                '/^' . preg_quote($country, '/') . '/',
                '',
                $request->input('phone_number')
            );
            if ( $request->input('phone_country') !== "+".\Prefix::phonecode() ) {
                $number  = ltrim($request->input('phone_number'), '0');  // remove leading 0
            }
            $phone   = $country . $number;
        }
        try {
            $tbl_agent = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->where('agent_id', $authorizedUser->agent_id)
                            ->first();
            if ( !$tbl_agent ) {
                return redirect()->back()->with('error', __('agent.no_data_found'));
            }
            $tbl_shop = null;
            if ($request->filled('shop_id')) {
                $tbl_shop = Shop::where('status', 1)
                    ->where('delete', 0)
                    ->where('shop_id', $request->input('shop_id') )
                    ->first();
                if ( !$tbl_shop ) {
                    return redirect()->back()->with('error', __('shop.no_data_found'));
                }
            }
            if ($request->filled('balance')) {
                if ($request->filled('shop_id')) {
                    if ( $tbl_shop->balance <= $request->input('balance') ) {
                        return redirect()->back()->with('error', __('shop.insufficient'));
                    }
                } else {
                    if ( $tbl_agent->balance <= $request->input('balance') ) {
                        return redirect()->back()->with('error', __('agent.insufficient'));
                    }
                }
            }
            $tbl_member = Member::create([
                'member_login' => $request->input('member_login'),
                'member_pass' => encryptPassword( $request->input('member_pass') ),
                'member_name' => $request->input('member_name'),
                'full_name' => $request->input('full_name') ?? null,
                'area_code' => $tbl_shop ? optional($tbl_shop->Areas)->area_code : $authorizedUser->area_code,
                'phone' => $phone,
                'balance' => $request->input('balance'),
                'email' => $request->input('email') ?? null,
                'wechat' => $request->input('wechat') ?? null,
                'whatsapp' => $request->input('whatsapp') ?? null,
                'facebook' => $request->input('facebook') ?? null,
                'telegram' => $request->input('telegram') ?? null,
                'dob' => $request->filled('dob') ? Carbon::parse($request->input('dob'))->startOfDay() : null,
                'shop_id' => $request->input('shop_id') ?? null,
                'GAstatus' => 0,
                'agent_id' => $authorizedUser->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ($request->filled('balance')) {
                $tbl_credit = Credit::create([
                    'member_id' => $tbl_member->member_id,
                    'user_id' => $authorizedUser->user_id,
                    'shop_id' => $request->input('shop_id') ?? null,
                    'type' => "deposit",
                    'amount' => $request->input('balance'),
                    'before_balance' => 0.00,
                    'after_balance' => $request->input('balance'),
                    'submit_on' => now(),
                    'agent_id' => $tbl_member->agent_id,
                    'status' => 0,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                if ($request->filled('shop_id')) {
                    $tbl_shopcredit = Shopcredit::create([
                        'manager_id' => null,
                        'shop_id' => $request->input('shop_id'),
                        'type' => "shopcredit.deposit",
                        'amount' => $request->input('balance'),
                        'before_balance' => $tbl_shop->principal,
                        'after_balance' => $tbl_shop->principal - $request->input('balance'),
                        'submit_on' => now(),
                        'agent_id' => $tbl_shop->agent_id,
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    $tbl_shop->decrement('balance', $request->input('balance'), [
                        'updated_on' => now(),
                    ]);
                } else {
                    $tbl_agentcredit = Agentcredit::create([
                        'agent_id' => $authorizedUser->agent_id,
                        'user_id' => $authorizedUser->user_id,
                        'type' => "member.newregister",
                        'amount' => $request->input('balance'),
                        'before_balance' => $tbl_agent->balance,
                        'after_balance' => $tbl_agent->balance - $request->input('balance'),
                        'submit_on' => now(),
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    $tbl_agent->decrement('balance', $request->input('balance'), [
                        'updated_on' => now(),
                    ]);
                }
            }
            if ($request->filled('shop_id')) {
                LogCreateAccount( $tbl_shop, "shop", $tbl_member->member_name, $request );
            } else {
                LogCreateAccount( $authorizedUser, "user", $tbl_member->member_name, $request );
            }
            return redirect()->route('admin.member.index')->with('success', __('member.member_added_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error adding member: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error') );
        }
    }

    /**
     * Show the form for editing the specified member.
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }
        $member = Member::where('member_id', $id)->with('Areas','Areas.States')->first();
        if (!$member) {
            return redirect()->route('admin.member.index')->with('error', __('messages.nodata'));
        }
        $banks = Bank::where('status', 1)
            ->where('delete', 0)
            ->orderBy('bank_name')
            ->get();
        $shops = Shop::where('status', 1)
            ->where('delete', 0);
        if ($authorizedUser->user_role !== 'masteradmin') {
            $shops = $shops->where('agent_id', $authorizedUser->agent_id);
        }
        $shops = $shops->orderBy('shop_name');
        $shops = $shops->get();
        $expro = \Prefix::phonecode();
        $prefixMap = [
            '60'   => '+60',
            '65'   => '+65',
            '86'   => '+86',
            $expro => '+'.$expro, // or '+60' if internal
        ];
        $country = '+60';
        $number  = $member->phone;
        if ($member->phone && preg_match('/^('.$expro.'|60|65|86)(\d+)$/', $member->phone, $matches)) {
            $country = $prefixMap[$matches[1]] ?? '+60';
            $number  = $matches[2];
        }
        return view('module.member.edit', compact('member', 'banks', 'shops', 'country', 'number'));
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
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $tbl_member = Member::where('member_id', $id)->first();
        if (!$tbl_member) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        $validator = Validator::make($request->all(), [
            'member_login' => ['required', 'string', 'max:255', Rule::unique('tbl_member', 'member_login')->ignore($id, 'member_id')],
            'member_name' => 'required|string|max:255',
            'member_pass' => 'nullable|string|min:6|max:255',
            'full_name' => 'nullable|string|max:255',
            'phone_country' => 'required|string|max:255',
            'phone_number'  => 'required|digits_between:7,12',
            'email' => 'nullable|string|max:255',
            'wechat' => 'nullable|string|max:255',
            'whatsapp' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'telegram' => 'nullable|string|max:255',
            'shop_id' => 'nullable|integer',
            'status' => 'nullable|in:1,0',
            'dob' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $phone = null;
            $tbl_shop = null;
            if ($request->filled('phone_number')) {
                $country = ltrim($request->input('phone_country'), '+');
                $number = preg_replace(
                    '/^' . preg_quote($country, '/') . '/',
                    '',
                    $request->input('phone_number')
                );
                if ( $request->input('phone_country') !== "+".\Prefix::phonecode() ) {
                    $number  = ltrim($number, '0');  // remove leading 0
                }
                $phone = $country . $number;
            }
            $tbl_agent = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->where('agent_id', $authorizedUser->agent_id)
                            ->first();
            if ( !$tbl_agent ) {
                return redirect()->back()->with('error', __('agent.no_data_found'));
            }
            if ($request->filled('shop_id')) {
                $tbl_shop = Shop::where('status', 1)
                    ->where('delete', 0)
                    ->where('shop_id', $request->input('shop_id') )
                    ->first();
                if ( !$tbl_shop ) {
                    return redirect()->back()->with('error', __('shop.no_data_found'));
                }
            }
            $type = null;
            $updateData = [
                'member_login' => $request->input('member_login'),
                'member_name' => $request->input('member_name'),
                'full_name' => $request->input('full_name') ?? null,
                'area_code' => $tbl_shop ? optional($tbl_shop->Areas)->area_code : $authorizedUser->area_code,
                'phone' => $phone,
                'email' => $request->input('email') ?? null,
                'wechat' => $request->input('wechat') ?? null,
                'whatsapp' => $request->input('whatsapp') ?? null,
                'facebook' => $request->input('facebook') ?? null,
                'telegram' => $request->input('telegram') ?? null,
                'dob' => $request->filled('dob') ? Carbon::parse($request->input('dob'))->startOfDay() : null,
                'shop_id' => $request->input('shop_id') ?? null,
                'status' => $request->filled('status') ? $request->input('status'): 0,
                'updated_on' => now(),
            ];
            // Only update password if provided
            if ($request->filled('member_pass')) {
                $updateData['member_pass'] = encryptPassword( $request->input('member_pass') );
            }
            $tbl_member->update($updateData);
            $tbl_member = $tbl_member->fresh();
            if ($request->filled('shop_id')) {
                LogCreateAccount( $tbl_shop, "shop", $tbl_member->member_name, $request );
            } else {
                LogCreateAccount( $authorizedUser, "user", $tbl_member->member_name, $request );
            }
            return redirect()->route('admin.member.index')->with('success', __('member.member_updated_successfully'));
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating member: " . $e->getMessage());
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
        if (!CheckAuthorizedDelete($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        $member = DB::table('tbl_member')->where('member_id', $id)->first();
        if (!$member) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }

        try {
            DB::table('tbl_member')->where('member_id', $id)->update([
                'status' => 0, // Set status to inactive
                'delete' => 1, // Mark as deleted
                'updated_on' => now(),
            ]);

            return redirect()->route('admin.member.index')->with('success', __('member.member_deleted_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error deleting member: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }
    }

    public function filterarea($state_code)
    {
        try {
            $areas = Areas::filterByState($state_code);
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'code' => 200,
                'data' => $areas,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Area filter error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Reveal the specified member password.
     * Corresponds to your API's 'post' method logic.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function revealpassword($id)
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_member = Member::where('member_id', $id)->first();
        if (!$tbl_member) {
            return response()->json([
                'status' => true,
                'message' => __('member.no_data_found'),
                'error' => __('member.no_data_found'),
                'code' => 400,
            ], 400);
        }
        try {
            return response()->json([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'code' => 200,
                'password' => decryptPassword($tbl_member->member_pass),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Reveal member password error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    public function show(Request $request, Member $member)
    {
        $authorizedUser = Auth::user();

        if (!$authorizedUser) {
            return redirect()->route('dashboard')
                ->with('error', __('messages.Unauthorized'));
        }

        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response(__('messages.Unauthorized'), 403);
        }

        if ($authorizedUser->user_role !== 'masteradmin'
            && $member->agent_id !== $authorizedUser->agent_id) {
            abort(403);
        }

        //Bank Account
        $bankaccounts = Bankaccount::where('member_id', $member->member_id)
                                   ->where('delete', 0)
                                   ->with('Bank')
                                   ->orderBy('fastpay', 'desc')
                                   ->get();

        //Main Wallet Transactions
        $credits = Credit::where('member_id', $member->member_id)
            ->where('delete', 0)
            ->get()
            ->map(function ($item) {
                return (object)[
                    'created_on'     => $item->created_on,
                    'type'           => $item->type,
                    'before_balance' => $item->before_balance,
                    'amount'         => $item->amount,
                    'after_balance'  => $item->after_balance,
                    'source'         => 'main',
                ];
            });

        // Game Wallet Transactions
        $gameMembers = GameMember::with('provider')
            ->where('member_id', $member->member_id)
            ->get()
            ->keyBy('gamemember_id');

        $gamepoints = GamePoint::whereIn('gamemember_id', $gameMembers->keys())
            ->where('delete', 0)
            ->where('status', 1)
            ->get()
            ->map(function ($item) use ($gameMembers) {

                $type = 'game_' . $item->type;

                $before = $item->before_balance;
                $after  = $item->after_balance;

                if (in_array($item->type, ['reload', 'withdraw'])) {
                    $before = $item->after_balance;
                    $after  = $item->before_balance;
                }

                $providerName = optional($gameMembers[$item->gamemember_id]->provider)->provider_name;

                return (object)[
                    'created_on'     => $item->created_on,
                    'type'           => $type,
                    'provider'       => $providerName,
                    'before_balance' => $before,
                    'amount'         => $item->amount,
                    'after_balance'  => $after,
                    'source'         => 'game',
                ];
            });

        // // Performance Transactions
        // $performances = Performance::where('member_id', $member->member_id)
        //     ->with('Mydownline','Myupline','Commissionrank','Agent')
        //     ->where('delete', 0)
        //     ->get()
        //     ->map(function ($item) {
        //         $downlineName = optional($item->Mydownline)->member_name;
        //         return (object)[
        //             'created_on'     => $item->created_on,
        //             'type'           => 'commission',
        //             'downline'       => $downlineName,
        //             'before_balance' => $item->before_balance,
        //             'amount'         => $item->sales_amount,
        //             'after_balance'  => $item->after_balance,
        //             'source'         => 'performance',
        //         ];
        //     });

        // Merged List
        $merged = $credits
            ->merge($gamepoints)
            ->sortByDesc('created_on')
            ->values();

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $merged->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $membercredits = new LengthAwarePaginator(
            $currentItems,
            $merged->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
        return view(
            'module.member.show', 
            compact(
                'member', 
                'membercredits',
                'bankaccounts',
            )
        );
    }

    /**
     * Sync the specified gamemember.
     * Corresponds to your API's 'post' method logic.
     *
     * @param  int  $member_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function syncgamemember(Request $request, $member_id)
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('gamemember_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_member = Member::where('member_id', $member_id)->first();
        if (!$tbl_member) {
            return response()->json([
                'status' => true,
                'message' => __('member.no_data_found'),
                'error' => __('member.no_data_found'),
                'code' => 400,
            ], 400);
        }
        try {
            $DaysAgo = Carbon::now()->startOfDay();
            $allplayers = Gamemember::where('delete', 0)
                ->where('member_id', $member_id)
                ->whereNotNull('lastlogin_on')
                ->where('lastlogin_on', '>=', $DaysAgo)
                ->where(function ($q) {
                    $q->whereNull('lastsync_on')
                    ->orWhereColumn('lastlogin_on', '>=', 'lastsync_on');
                })
                ->get();
            return response()->json([
                'status' => true,
                'message' => __('gamelog.sync_complete'),
                'error' => "",
                'data' => $allplayers,
                'code' => 200,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Sync member gamelog error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Sync the specified gamememmber gamelog.
     * Corresponds to your API's 'post' method logic.
     *
     * @param  int  $member_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function syncgamelog(Request $request, $member_id)
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('gamelog_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_member = Member::where('member_id', $member_id)->first();
        if (!$tbl_member) {
            return response()->json([
                'status' => true,
                'message' => __('member.no_data_found'),
                'error' => __('member.no_data_found'),
                'code' => 400,
            ], 400);
        }
        try {
            $tbl_player = $request->input('item');
            if (!$tbl_player) {
                return response()->json([
                    'status' => false,
                    'message' => __('gamemember.no_data_found'),
                    'error' => __('gamemember.no_data_found'),
                    'code' => 400,
                ], 400);
            }
            switch ($tbl_player['gameplatform_id'] ?? null) {
                case 1003:
                    $response = \Mega::syncgamelog($tbl_player);
                break;
                case 1004:
                    $response = \Jili::syncgamelog($tbl_player);
                    break;
                case 1005:
                    $response = \Onehubx::syncgamelog($tbl_player);
                    break;
                default:
                    return response()->json([
                        'status' => false,
                        'message' => __('gameplatform.no_data_found'),
                        'error' => __('gameplatform.no_data_found'),
                        'code' => 400,
                    ], 400);
                    break;
            }
            return response()->json(
                $response, 
                200
            );
        } catch (\Exception $e) {
            Log::error('Sync member gamelog error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Insert the specified member gamelog.
     * Corresponds to your API's 'post' method logic.
     *
     * @param  int  $member_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function insertgamelog(Request $request, $member_id)
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_member = Member::where('member_id', $member_id)->first();
        if (!$tbl_member) {
            return response()->json([
                'status' => true,
                'message' => __('member.no_data_found'),
                'error' => __('member.no_data_found'),
                'code' => 400,
            ], 400);
        }
        try {
            $tbl_gamelog = $request->input('item');
            if (!$tbl_gamelog) {
                return response()->json([
                    'status' => true,
                    'message' => __('gamelog.no_data_found'),
                    'error' => __('gamelog.no_data_found'),
                    'code' => 400,
                ], 400);
            }
            $turnover = 0.0000;
            Gamelog::firstOrCreate(
                [
                    'gamemember_id' => $gamelog['gamemember_id'],
                    'gamelogtarget_id' => $gamelog['gamelogtarget_id'],
                ],
                $gamelog
            );
            $turnover += $gamelog['betamount'];
            return response()->json([
                'status' => true,
                'message' => __('gamelog.sync_complete'),
                'error' => "",
                'data' => $turnover,
                'code' => 200,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Insert member gamelog error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Calculate the specified member turnover.
     * Corresponds to your API's 'post' method logic.
     *
     * @param  int  $member_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function turnover(Request $request, $member_id)
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
        if (!CheckAuthorizedView($authorizedUser->user_id, GetModuleID('member_management'))) {
            return response()->json([
                'status' => true,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
                'code' => 403,
            ], 403);
        }
        $tbl_member = Member::where('member_id', $member_id)->first();
        if (!$tbl_member) {
            return response()->json([
                'status' => true,
                'message' => __('member.no_data_found'),
                'error' => __('member.no_data_found'),
                'code' => 400,
            ], 400);
        }
        try{
            $turnover = $request->input('turnover');
            if ( $turnover > 0 ) {
                // VIP Score
                $tbl_score = AddScore( $tbl_member, 'deposit', $turnover);
                // Commission
                $salestarget = AddCommission( $tbl_member, $turnover );
            }
            return response()->json([
                'status' => true,
                'message' => __('gamelog.sync_complete'),
                'error' => "",
                'code' => 200,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Calculate member turnover error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.api_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }
}
