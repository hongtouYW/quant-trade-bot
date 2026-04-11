<?php

use Illuminate\Http\Request;
use App\Models\Credit;
use App\Models\VIP;
use App\Models\Recruit;
use App\Models\Score;
use App\Models\Member;
use App\Models\Performance;
use App\Models\Commissionrank;
use App\Models\Turnover;
use App\Models\Agent;
use App\Models\Agentcredit;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

if (!function_exists('DisplayLevel')) {
    function DisplayLevel($member)
    {
        /* 🔥 Replace vip with MyVip->lvl */
        $member->vip = optional($member->MyVip)->lvl ?? $member->vip;
        // (optional) hide relation if you don't want to expose structure
        unset($member->MyVip);
        return $member;
    }
}

if (!function_exists('AddTurnover')) {
    function AddTurnover($member_id, $betamount )
    {
        $tbl_turnover = Turnover::where('delete', 0)
                                ->where('member_id', $member_id)
                                ->first();
        if (!$tbl_turnover) {
            $tbl_turnover = Turnover::create([
                'member_id'  => $member_id,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
        }
        $tbl_turnover->increment('amount', $betamount, [
            'updated_on' => now(),
        ]);
        return $tbl_turnover->fresh();
    }
}

if (!function_exists('AddScore')) {
    function AddScore( $tbl_member, $type = 'deposit', $amount = 0.00)
    {
        $tbl_score = Score::firstOrCreate(
            ['member_id' => $tbl_member->member_id, 'type' => $type],
            [
                'type' => $type,
                'created_on' => now(),
                'updated_on' => now(),
            ]
        );
        $tbl_score->increment('amount', $amount, [
            'updated_on' => now(),
        ]);
        $tbl_member = UpVIPLevel($tbl_member);
        return $tbl_score;
    }
}

if (!function_exists('UpVIPLevel')) {
    function UpVIPLevel($tbl_member)
    {
        // 1) Get score
        $tbl_score = Score::where('status', 1)
            ->where('delete', 0)
            ->where('member_id', $tbl_member->member_id)
            ->first(['amount']);

        if (!$tbl_score) {
            return $tbl_member;
        }

        $score = (float) $tbl_score->amount;

        // 2) Load VIP list once (much faster)
        $vipList = VIP::where('status', 1)
            ->where('delete', 0)
            ->where('agent_id', $tbl_member->agent_id ?? 0)
            ->orderBy('min_amount')
            ->get(['vip_id', 'min_amount', 'max_amount']);

        if ($vipList->isEmpty()) {
            return $tbl_member;
        }

        // 3) Loop in PHP (zero DB lag)
        $newVip = null;
        foreach ($vipList as $vip) {
            if ($score >= $vip->min_amount && $score <= $vip->max_amount) {
                $newVip = $vip->vip_id;
                break;
            }
        }

        // 4) If no match, assign highest VIP
        if (!$newVip) {
            $last = $vipList->last();
            if ($score >= $last->max_amount) {
                $newVip = $last->vip_id;
            }
        }

        // 5) Update only if changed
        if ($newVip && $newVip !== $tbl_member->vip) {
            $tbl_member->update([
                'vip' => $newVip,
                'updated_on' => now(),
            ]);
        }

        return $tbl_member;
    }
}

if (!function_exists('checkFirstBonusEligibility')) {
    function checkFirstBonusEligibility($tbl_member)
    {
        // Check if member and VIP exist
        if (!$tbl_member || !$tbl_member->MyVip) {
            return [
                'eligible' => false,
                'message' => __('vip.no_data_found')
            ];
        }
        // Check VIP status
        if ($tbl_member->MyVip->status !== 1 || $tbl_member->MyVip->delete === 1) {
            return [
                'eligible' => false,
                'message' => 'VIP ' . __('vip.inactive')
            ];
        }
        $bonusAmount = $tbl_member->MyVip->firstbonus ?? 0;
        if ($bonusAmount <= 0) {
            return [
                'eligible' => false,
                'message' => 'VIP ' . __('vip.firstbonus_no_bonus')
            ];
        }
        $tbl_vip = VIP::where('status', 1)
            ->where('delete', 0)
            ->when(
                is_null($tbl_member->agent_id),
                fn($q) => $q->where('agent_id', 0),
                fn($q) => $q->where('agent_id', $tbl_member->agent_id)
            )
            ->where('lvl', '<=', $tbl_member->MyVip->lvl)
            ->where('firstbonus', '>', 0)
            ->orderBy('lvl', 'asc')
            ->get();
        if ($tbl_vip->count() <= 0) {
            return [
                'eligible' => false,
                'message' => 'VIP ' . __('vip.firstbonus_no_bonus')
            ];
        }
        $lastCredit = Credit::where('member_id', $tbl_member->member_id)
                            ->where('type', 'firstbonus')
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->orderBy('created_on', 'desc')
                            ->count();
        $viplist = [];
        foreach ($tbl_vip as $key => $vip) {
            if ( $vip->lvl <= $lastCredit ) {
                continue;
            }
            $viplist[$key]['vip_id'] = $vip->vip_id;
            $viplist[$key]['lvl'] = $vip->lvl;
            $viplist[$key]['bonus_amount'] = $vip->firstbonus;
        }
        if ( empty($viplist) ) {
            return [
                'eligible' => false,
                'message' => 'VIP ' . __('vip.firstbonus_no_bonus')
            ];
        }
        return [
            'eligible' => true,
            'viplist' => $viplist
        ];
    }
}

if (!function_exists('checkBonusEligibility')) {
    function checkBonusEligibility($tbl_member, $type = 'dailybonus')
    {
        // Check if member and VIP exist
        if (!$tbl_member || !$tbl_member->MyVip) {
            return [
                'eligible' => false,
                'message' => __('vip.no_data_found'),
                'expire_on' => null,
            ];
        }
        // Check VIP status
        if ($tbl_member->MyVip->status !== 1 || $tbl_member->MyVip->delete === 1) {
            return [
                'eligible' => false,
                'message' => 'VIP ' . __('vip.inactive'),
                'expire_on' => null,
            ];
        }
        $bonusAmount = $tbl_member->MyVip->{$type} ?? 0;
        if ($bonusAmount <= 0) {
            return [
                'eligible' => false,
                'message' => 'VIP ' . __('vip.'.$type.'_no_bonus'),
                'expire_on' => null,
            ];
        }
        $lastCredit = Credit::where('member_id', $tbl_member->member_id)
                            ->where('type', $type)
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->orderBy('created_on', 'desc')
                            ->first();
        if (!$lastCredit) {
            return [
                'eligible'     => true,
                'bonus_amount' => $bonusAmount,
                'expire_on'  => Carbon::tomorrow()->startOfDay()->toDateTimeString(),
            ];
        }
        $created = Carbon::parse($lastCredit->created_on);
        switch ($type) {
            case 'dailybonus':
                $nextEligibleDate = $created->copy()->addDay()->startOfDay(); // 1 days after last claim, at 00:00
                break;
            case 'weeklybonus':
                $nextEligibleDate = $created->copy()->addWeek()->startOfDay(); // 7 days after last claim, at 00:00
                break;
            case 'monthlybonus':
                $nextEligibleDate = $created->copy()->addMonth()->startOfDay(); // 30 days after last claim, at 00:00
                break;
            default:
                return [
                    'eligible' => false,
                    'message' => __('vip.invalid_bonus'),
                    'expire_on' => null,
                ];
                break;
        }
        // Compare dates: allow claim any time on the eligible day
        if (Carbon::now()->lt($nextEligibleDate)) {
            return [
                'eligible' => false,
                'message' => __('vip.' . $type . '_notyet'),
                'expire_on' => $nextEligibleDate->toDateTimeString(),
            ];
        }
        return [
            'eligible' => true,
            'bonus_amount' => $bonusAmount,
            'expire_on'  => Carbon::tomorrow()->startOfDay()->toDateTimeString(),
        ];
    }
}

if (!function_exists('getBonusRemainingDays')) {
    function getBonusRemainingDays($tbl_member, $type = 'dailybonus')
    {
        if (!$tbl_member || !$tbl_member->MyVip) {
            return [
                'days' => null,
                'hours' => null,
                'minutes' => null,
            ];
        }
        $lastCredit = Credit::where('member_id', $tbl_member->member_id)
                            ->where('type', $type)
                            ->where('status', 1)
                            ->orderBy('created_on', 'desc')
                            ->first();
        // If no previous claim, eligible immediately
        if (!$lastCredit) {
            return [
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
            ];
        }
        $created = Carbon::parse($lastCredit->created_on);
        switch ($type) {
            case 'dailybonus':
                $nextEligibleDate = $created->copy()->addDay()->startOfDay();
                break;
            case 'weeklybonus':
                $nextEligibleDate = $created->copy()->addWeek()->startOfDay();
                break;
            case 'monthlybonus':
                $nextEligibleDate = $created->copy()->addMonth()->startOfDay();
                break;
            default:
                return [
                    'days' => null,
                    'hours' => null,
                    'minutes' => null,
                ];
        }
        $now = Carbon::now();
        // If eligible already
        if ($now->greaterThanOrEqualTo($nextEligibleDate)) {
            return [
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
            ];
        }
        // Compute remaining time
        $diffInMinutes = $now->diffInMinutes($nextEligibleDate);
        $days = floor($diffInMinutes / 1440); // 1440 = 24 * 60
        $hours = floor(($diffInMinutes % 1440) / 60);
        $minutes = $diffInMinutes % 60;
        return [
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'next_eligible_date' => $nextEligibleDate->toDateTimeString(),
        ];
    }
}

if (!function_exists('getBonusRemainingLevel')) {
    function getBonusRemainingLevel($tbl_member)
    {
        // Check if member and VIP exist
        if (!$tbl_member || !$tbl_member->MyVip) {
            return [
                'eligible' => false,
                'message' => __('vip.no_data_found')
            ];
        }
        // Check VIP status
        if ($tbl_member->MyVip->status !== 1 || $tbl_member->MyVip->delete === 1) {
            return [
                'eligible' => false,
                'message' => 'VIP ' . __('vip.inactive')
            ];
        }
        $tbl_vip = VIP::where('status', 1)
                        ->where('delete', 0)
                        ->when(
                            is_null($tbl_member->agent_id),
                            fn($q) => $q->where('agent_id', 0),
                            fn($q) => $q->where('agent_id', $tbl_member->agent_id)
                        )
                        ->orderBy('lvl', 'desc')
                        ->first();
        if (!$tbl_vip) {
            return [
                'eligible' => false,
                'message' => __('vip.no_data_found')
            ];
        }
        if ( $tbl_member->MyVip->lvl >= $tbl_vip->lvl) {
            return [
                'eligible' => false,
                'message' => __('vip.max_lvl')
            ];
        }
        $tbl_score = Score::where('status', 1)
                          ->where('delete', 0)
                          ->where('member_id', $tbl_member->member_id)->first();
        if (!$tbl_score)
        {
            $tbl_score = Score::create([
                'member_id' => $tbl_member->member_id,
                'type' => 'deposit',
                'amount' => 0.00,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
        }
        $remainAmount = $tbl_member->MyVip->max_amount - $tbl_score->amount;
        return [
            'eligible' => true,
            'remain_amount' => $remainAmount
        ];
    }
}

// 业绩总数
if (!function_exists('TotalSales')) {
    function TotalSales( $tbl_member, $request = null, $level = null, $downline = null)
    {
        // 1️⃣ Determine member IDs
        if (is_null($downline)) {
            // Fetch downline if $downline is null
            $downline = AllDownline($tbl_member, $request, $level);
            $downlineMemberIds = $downline['member_ids'] ?? [];
        } elseif (is_array($downline) && empty($downline)) {
            // If empty array, no downline, only self
            $downlineMemberIds = [];
        } else {
            // If $downline is provided with 'member_ids'
            $downlineMemberIds = $downline['member_ids'] ?? [];
        }
        // Include self
        $memberIds = array_merge([$tbl_member->member_id], $downlineMemberIds);

        $tbl_performance = Performance::when(
                        is_null($tbl_member->agent_id),
                        fn($q) => $q->where('agent_id', 0),
                        fn($q) => $q->where('agent_id', $tbl_member->agent_id)
        )
        ->whereIn('member_id', $memberIds)
        ->where('status', 1)
        ->where('delete', 0);
        if ( !is_null( $request ) ) {
            $tbl_performance = queryBetweenDateEloquent($tbl_performance, $request, 'performance_date');
        }
        return $tbl_performance->sum('sales_amount');
    }
}

// 佣金总数
if (!function_exists('TotalCommission')) {
    function TotalCommission( $tbl_member, $request = null, $level = null, $downline = null)
    {
        // 1️⃣ Determine member IDs
        if (is_null($downline)) {
            // Fetch downline if $downline is null
            $downline = AllDownline($tbl_member, $request, $level);
            $downlineMemberIds = $downline['member_ids'] ?? [];
        } elseif (is_array($downline) && empty($downline)) {
            // If empty array, no downline, only self
            $downlineMemberIds = [];
        } else {
            // If $downline is provided with 'member_ids'
            $downlineMemberIds = $downline['member_ids'] ?? [];
        }
        // Include self
        $memberIds = array_merge([$tbl_member->member_id], $downlineMemberIds);
    
        $tbl_performance = Performance::when(
                        is_null($tbl_member->agent_id),
                        fn($q) => $q->where('agent_id', 0),
                        fn($q) => $q->where('agent_id', $tbl_member->agent_id)
        )
        ->whereIn('member_id', $memberIds)
        ->where('status', 1)
        ->where('delete', 0);
        if ( !is_null( $request ) ) {
            $tbl_performance = queryBetweenDateEloquent($tbl_performance, $request, 'performance_date');
        }
        return $tbl_performance->sum('commission_amount');    
    }
}
// 佣金列表
if (!function_exists('ListCommission')) {
    function ListCommission( $tbl_member, $request = null)
    {
        $tbl_performance = Performance::when(
                        is_null($tbl_member->agent_id),
                        fn($q) => $q->where('agent_id', 0),
                        fn($q) => $q->where('agent_id', $tbl_member->agent_id)
        )
        ->where('member_id', $tbl_member->member_id)
        ->where('status', 1)
        ->where('delete', 0)
        ->with('Mydownline')
        ->orderBy('created_on', 'desc');
        if ( !is_null( $request ) ) {
            $tbl_performance = queryBetweenDateEloquent($tbl_performance, $request, 'created_on');
        }
        return $tbl_performance->get();    
    }
}

// 佣金增加
if (!function_exists('AddCommission')) {
    function AddCommission( $tbl_member, $amount = 0.0000)
    {
        $tbl_recruit = Recruit::where( 'member_id', $tbl_member->member_id)
                              ->with('Myupline')
                              ->first();
        if ( !$tbl_recruit ) {
            return;
        }
        $tbl_upline = $tbl_recruit->Myupline;
        if ( !$tbl_upline ) {
            return;
        }
        $salestarget = [];
        // Commission Each Rank
        $tbl_commissionrank = Commissionrank::where('status', 1)
                                            ->where('delete', 0)
                                            ->orderBy('rank')
                                            ->get();
        foreach ($tbl_commissionrank as $key => $commissionrank) {
            if ( !$tbl_upline ) {
                break;
            }
            $bonuscommission = $amount * $commissionrank->amount;
            if ( $bonuscommission <=  0 ) {
                return;
            }
            $tbl_upline = $tbl_upline->load('Member','Myupline');
            $agent_id = $tbl_member->agent_id ?? 0;
            $tbl_agent = Agent::where('status', 1)
                              ->where('delete', 0)
                              ->where('agent_id', $agent_id)
                              ->lockForUpdate()
                              ->first();
            $reason = null;
            if ( $tbl_agent->balance < $bonuscommission ) {
                $reason = 'agent.insufficient';
                $status = -1;
            } else {
                $status = 1;
            }
            $tbl_credit = Credit::create([
                'member_id' => $tbl_upline->member_id,
                'type' => "commission",
                'amount' => $bonuscommission,
                'before_balance' => $tbl_upline->Member->balance,
                'after_balance' => $tbl_upline->Member->balance + $bonuscommission,
                'submit_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_performance = Performance::create([
                'member_id' => $tbl_upline->member_id,
                'downline' => $tbl_member->member_id,
                'upline' => $tbl_upline->Myupline ? $tbl_upline->Myupline->member_id: null,
                'commissionrank_id' => $commissionrank->commissionrank_id,
                'performance_date' => now(),
                'sales_amount' => $amount,
                'commission_amount' => $bonuscommission,
                'before_balance' => $tbl_upline->Member->balance,
                'after_balance' => $tbl_upline->Member->balance + $bonuscommission,
                'notes' => json_encode(
                    [
                        'template' => 'vip.commission_added_successfully',
                        'data' => [
                            'member_name' => $tbl_upline->Member->member_name,
                            'amount' => $bonuscommission,
                        ],
                    ]
                ),
                'agent_id' => $tbl_member->agent_id,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_agentcredit = Agentcredit::create([
                'agent_id' => $tbl_agent->agent_id,
                'member_id' => $tbl_upline->member_id,
                'type' => "agent.commission",
                'amount' => $bonuscommission,
                'before_balance' => $tbl_agent->balance,
                'after_balance' => $tbl_agent->balance - $bonuscommission,
                'submit_on' => now(),
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ( $status === 1 ) {
                $tbl_upline->Member->increment('balance', $bonuscommission);
                $tbl_agent->decrement('balance', $bonuscommission);
            }
            $salestarget[$key]['credit'] = $tbl_credit;
            $salestarget[$key]['sales'] = $tbl_performance;
            $salestarget[$key]['status'] = true;
            $salestarget[$key]['message'] = [
                'template' => 'vip.commission_added_successfully',
                'data' => [
                    'member_name' => $tbl_upline->Member->member_name,
                    'amount' => $bonuscommission,
                ],
            ];
            $tbl_upline = $tbl_upline->Myupline;
        }
        return $salestarget;
    }
}

// // 团队
if (!function_exists('GetDownline')) {
    function GetDownline(int $member_id, $request = null)
    {
        $tbl_recruit = Recruit::where( 'upline', $member_id)
                        ->with('Member');
        if ( $request ) {
            $tbl_recruit = queryBetweenDateEloquent($tbl_recruit, $request, 'created_on');
        }
        $tbl_recruit = $tbl_recruit->get();
        return $tbl_recruit;
    }
}

if (!function_exists('GetLowestDownline')) {
    function GetLowestDownline()
    {
        $lowestdownline = [];
        $tbl_member = Member::where( 'status', 1 )
                            ->where( 'delete', 0 )
                            ->get();
        foreach ($tbl_member as $key => $member) {
            $tbl_recruit = Recruit::where( 'upline', $member['member_id'])
                        ->exists();
            if ( !$tbl_recruit ) {
                $lowestdownline[] = $member;
            }
        }
        return $lowestdownline;
    }
}

if (!function_exists('AllDownline')) {
    function AllDownline($tbl_member, $request = null, int $level = null, int $currentlevel = 1): array
    {
        if ($level !== null && $currentlevel > $level) {
            return ['tree' => [], 'count' => 0, 'member_ids' => []];
        }
        $downlineTree = [];
        $totalCount = 0;
        $memberIds = [];
        $directDownline = GetDownline($tbl_member->member_id, $request);
        foreach ($directDownline as $recruit) {
            $result = AllDownline($recruit->Member, $request, $level, $currentlevel + 1);
            $recruitData = $recruit->Member->toArray();
            $recruitData['invitecode'] = $recruit->invitecode;
            $recruitData['downline'] = $result['tree'];
            $downlineTree[] = $recruitData;
            $totalCount += 1 + $result['count']; // +1 for this recruit
            $memberIds[] = $recruit->Member->member_id;
            $memberIds = array_merge($memberIds, $result['member_ids']);
        }
        return [
            'tree' => $downlineTree,
            'count' => $totalCount,
            'member_ids' => array_unique($memberIds),
        ];
    }
}

if (!function_exists('AllUpline')) {
    function AllUpline($tbl_member): array
    {
        $uplineTree = [];
        $memberIds = [];
        $member_id = $tbl_member->member_id;
        while ($member_id) {
            $tbl_recruit = Recruit::where('member_id', $member_id)
                                  ->with('Myupline','Myupline.Member')
                                  ->first();
            if ( !$tbl_recruit ) {
                break; // stop if no more parents
            }
            if ( !$tbl_recruit->upline ) {
                break; // stop if no more parents
            }
            $uplineData = $tbl_recruit->Myupline->Member;
            $uplineData['invitecode'] = $tbl_recruit->invitecode ?? null;
            $uplineTree[] = $uplineData;
            $memberIds[] = $tbl_recruit->upline;
            $member_id = $tbl_recruit->upline; // go to next level up
        }
        return [
            'tree' => $uplineTree,
            'count' => count($memberIds),
            'member_ids' => array_unique($memberIds),
        ];
    }
}

if (!function_exists('GetPerformance')) {
    function GetPerformance($member_id, $request )
    {
        $tbl_performance = Performance::where( 'upline', $member_id)
                        ->with('Member');
        if ( $request ) {
            $tbl_performance = queryBetweenDateEloquent($tbl_performance, $request, 'created_on');
        }
        $tbl_performance = $tbl_performance->get();
        return $tbl_performance;
    }
}

if (!function_exists('AllPerformance')) {
    function AllPerformance($tbl_member, $request = null, int $level = null, int $currentlevel = 1): array
    {
        if ($level !== null && $currentlevel > $level) {
            return ['tree' => [], 'count' => 0, 'sales'=> 0.00, 'commission'=> 0.00, ];
        }
        $downlineTree = [];
        $totalCount = 0;
        $totalSales = 0.00;
        $totalComm = 0.00;
        $directDownline = GetPerformance($tbl_member->member_id, $request);
        foreach ($directDownline as $performance) {
            $result = AllPerformance($performance->Member, $request, $level, $currentlevel + 1);
            $performanceData = $performance->Member->toArray();
            $performanceData['downline'] = $result['tree'];
            $downlineTree[] = $performanceData;
            $totalCount += 1 + $result['count']; // +1 for this performance
            $totalSales += $performance->sales_amount + $result['sales'];
            $totalComm  += $performance->commission_amount + $result['commission'];
        }
        return [
            'tree' => $downlineTree,
            'count' => $totalCount,
            'sales'=> $totalSales,
            'commission'=> $totalComm,
        ];
    }
}
