<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\User;
use App\Models\Shop;
use App\Models\Manager;
use App\Models\Member;
use App\Models\Supervisor;
use App\Models\Gamelog;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class DashboardController extends Controller
{

    public function index()
    {
        $user = Auth::user();
        if (!Auth::check()) {
            return redirect('/home/login');
        }

        $today = Carbon::today();

        // ─── Agent scope ───────────────────────────────────────────────
        $tbl_agent = Agent::where('status', 1)->where('delete', 0);
        if ($user->user_role === 'masteradmin') {
            $tbl_agent->where('agent_id', '!=', 0);
        } else {
            $tbl_agent->where('agent_id', $user->agent_id)->where('agent_id', '!=', 0);
        }

        $totalagentbalance   = $tbl_agent->sum('balance');
        $totalagentprincipal = $tbl_agent->sum('principal');
        $totalagentincome    = $tbl_agent->sum(DB::raw('principal - balance'));

        $agentIds = (clone $tbl_agent)->pluck('agent_id');

        // ─── Counts ─────────────────────────────────────────────────────
        $totalManagers = Manager::whereIn('agent_id', $agentIds)->where('status', 1)->where('delete', 0)->count();
        $totalShops    = Shop::whereIn('agent_id', $agentIds)->where('status', 1)->where('delete', 0)->count();
        $totalMembers  = Member::whereIn('agent_id', $agentIds)->where('status', 1)->where('delete', 0)->count();

        // ─── Manager credit ─────────────────────────────────────────────
        $managerQuery          = Manager::whereIn('agent_id', $agentIds)->where('status', 1)->where('delete', 0);
        $totalManagerPrincipal = $managerQuery->sum('principal');
        $totalManagerBalance   = $managerQuery->sum('balance');
        $totalManagerUsed      = $totalManagerPrincipal - $totalManagerBalance;
        $managerUsagePercent   = $totalManagerPrincipal > 0 ? ($totalManagerUsed / $totalManagerPrincipal) * 100 : 0;

        // ─── Shop credit ────────────────────────────────────────────────
        $shopQuery          = Shop::whereIn('agent_id', $agentIds)->where('status', 1)->where('delete', 0);
        $totalShopPrincipal = $shopQuery->sum('principal');
        $totalShopBalance   = $shopQuery->sum('balance');
        $totalShopUsed      = $totalShopPrincipal - $totalShopBalance;
        $shopUsagePercent   = $totalShopPrincipal > 0 ? ($totalShopUsed / $totalShopPrincipal) * 100 : 0;

        // ─── Member credit ──────────────────────────────────────────────
        $memberQuery       = Member::whereIn('agent_id', $agentIds)->where('status', 1)->where('delete', 0);
        $memberIds         = (clone $memberQuery)->pluck('member_id');
        $totalMemberBalance = $memberQuery->sum('balance');

        $totalMemberDeposit = DB::table('tbl_credit')
            ->whereIn('member_id', $memberIds)->where('status', 1)->where('type', 'deposit')->sum('amount');
        $totalMemberWithdraw = DB::table('tbl_credit')
            ->whereIn('member_id', $memberIds)->where('status', 1)->where('type', 'withdraw')->sum('amount');
        $memberWithdrawPercent = $totalMemberDeposit > 0 ? ($totalMemberWithdraw / $totalMemberDeposit) * 100 : 0;

        // ─── Today stats ─────────────────────────────────────────────────
        $todayDeposit = DB::table('tbl_credit')
            ->whereIn('member_id', $memberIds)->where('status', 1)
            ->where('type', 'deposit')->whereDate('created_on', $today)->sum('amount');

        $todayWithdraw = DB::table('tbl_credit')
            ->whereIn('member_id', $memberIds)->where('status', 1)
            ->where('type', 'withdraw')->whereDate('created_on', $today)->sum('amount');

        $todayRegister = Member::whereIn('agent_id', $agentIds)
            ->whereDate('created_on', $today)->count();

        $todayLogin = Member::whereIn('agent_id', $agentIds)
            ->whereDate('lastlogin_on', $today)->count();

        // ─── Most played games (masteradmin only or all) ─────────────────
        $topGames = Gamelog::select('game_id', DB::raw('COUNT(*) as bet_count'), DB::raw('SUM(betamount) as total_bet'))
            ->whereIn('agent_id', $agentIds)
            ->whereNotNull('game_id')
            ->groupBy('game_id')
            ->orderByDesc('bet_count')
            ->limit(5)
            ->with('Game')
            ->get();

        // ─── MasterAdmin: Supervisor breakdown ───────────────────────────
        $supervisorList = [];
        if ($user->user_role === 'masteradmin') {
            // Adjust model/table name to match your actual Supervisor model
            $supervisors = Agent::where('status', 1)->where('delete', 0)->where('agent_id', '!=', 0)->get();

            foreach ($supervisors as $supervisor) {
                $svAgentIds = [$supervisor->agent_id];

                $svManagerCount = Manager::whereIn('agent_id', $svAgentIds)->where('status', 1)->where('delete', 0)->count();
                $svShopCount    = Shop::whereIn('agent_id', $svAgentIds)->where('status', 1)->where('delete', 0)->count();
                $svMemberCount  = Member::whereIn('agent_id', $svAgentIds)->where('status', 1)->where('delete', 0)->count();

                $svMemberIds = Member::whereIn('agent_id', $svAgentIds)->where('status', 1)->where('delete', 0)->pluck('member_id');
                $svDeposit   = DB::table('tbl_credit')->whereIn('member_id', $svMemberIds)->where('type', 'deposit')->where('status', 1)->sum('amount');
                $svWithdraw  = DB::table('tbl_credit')->whereIn('member_id', $svMemberIds)->where('type', 'withdraw')->where('status', 1)->sum('amount');
                $svProfit    = $supervisor->principal - $supervisor->balance;

                $supervisorList[] = [
                    'agent'         => $supervisor,
                    'manager_count' => $svManagerCount,
                    'shop_count'    => $svShopCount,
                    'member_count'  => $svMemberCount,
                    'principal'     => $supervisor->principal,
                    'balance'       => $supervisor->balance,
                    'profit'        => $svProfit,
                    'deposit'       => $svDeposit,
                    'withdraw'      => $svWithdraw,
                ];
            }
        }

        // ─── SuperAdmin: Per-manager breakdown ───────────────────────────
        $managerList = [];
        if ($user->user_role !== 'masteradmin') {
            $managers = Manager::whereIn('agent_id', $agentIds)->where('status', 1)->where('delete', 0)->get();

            foreach ($managers as $manager) {
                $mgShops = Shop::where('manager_id', $manager->manager_id)->where('status', 1)->where('delete', 0)->get();
                $shopDetails = [];

                foreach ($mgShops as $shop) {
                    $shopMemberIds = Member::where('shop_id', $shop->shop_id)->where('status', 1)->where('delete', 0)->pluck('member_id');
                    $shopDeposit   = DB::table('tbl_credit')->whereIn('member_id', $shopMemberIds)->where('type', 'deposit')->where('status', 1)->sum('amount');
                    $shopWithdraw  = DB::table('tbl_credit')->whereIn('member_id', $shopMemberIds)->where('type', 'withdraw')->where('status', 1)->sum('amount');
                    $shopProfit    = $shop->principal - $shop->balance;

                    $shopDetails[] = [
                        'shop'          => $shop,
                        'member_count'  => $shopMemberIds->count(),
                        'deposit'       => $shopDeposit,
                        'withdraw'      => $shopWithdraw,
                        'profit'        => $shopProfit,
                        'principal'     => $shop->principal,
                        'balance'       => $shop->balance,
                    ];
                }

                $managerList[] = [
                    'manager'    => $manager,
                    'shop_count' => $mgShops->count(),
                    'shops'      => $shopDetails,
                    'principal'  => $manager->principal,
                    'balance'    => $manager->balance,
                    'profit'     => $manager->principal - $manager->balance,
                ];
            }
        }

        return view('dashboard', compact(
            'user',
            'totalagentbalance',
            'totalagentprincipal',
            'totalagentincome',
            'totalShops',
            'totalManagers',
            'totalMembers',
            'totalManagerPrincipal',
            'totalManagerBalance',
            'totalManagerUsed',
            'managerUsagePercent',
            'totalShopPrincipal',
            'totalShopBalance',
            'totalShopUsed',
            'shopUsagePercent',
            'totalMemberBalance',
            'totalMemberDeposit',
            'totalMemberWithdraw',
            'memberWithdrawPercent',
            // new
            'todayDeposit',
            'todayWithdraw',
            'todayRegister',
            'todayLogin',
            'topGames',
            'supervisorList',
            'managerList',
        ));
    }

    public function iframe()
    {
        if (Auth::check()) {
            return view('iframe');
        }
        return redirect('/login');
    }
}
