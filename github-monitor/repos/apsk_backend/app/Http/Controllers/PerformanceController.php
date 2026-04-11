<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Credit;
use App\Models\VIP;
use App\Models\Recruit;
use App\Models\Score;
use App\Models\Member;
use App\Models\Performance;
use App\Models\Commissionrank;
use App\Models\Invitation;
use App\Models\Invitationhistory;
use App\Models\Gamemember;
use App\Models\Gamelog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PerformanceController extends Controller
{
    /**
     * my upline. 推广赚钱 直属上线
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myupline(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            // $allupline = AllUpline($tbl_member);
            $tbl_recruit = Recruit::where('status', 1)
                                  ->where('delete', 0)
                                  ->where('member_id', $request->input('member_id') )
                                  ->first();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data'    => $tbl_recruit ? $tbl_recruit->upline : null,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * downline list. 推广赚钱 我的数据
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mydata(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'startdate' => 'nullable|date',
            'enddate' => 'nullable|date|after_or_equal:startdate',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            //// 直属数据
            $tbl_recruit = Recruit::where('status', 1)
                                 ->where('delete', 0)
                                 ->where('upline', $request->input('member_id') );
            $tbl_recruit = queryBetweenDateEloquent($tbl_recruit, $request, 'created_on');
            $downlineids = $tbl_recruit->pluck('member_id')->toArray();
            // 新增直属
            $data['now']['recruit']['new']['totalcount'] = $tbl_recruit->count();
            $firstbonus = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->where('type', 'firstbonus')
                                ->where('member_id', $request->input('member_id') );
            $firstbonus = queryBetweenDateEloquent($firstbonus, $request, 'updated_on');
            // 首充金额
            $data['now']['vip']['firstbonus']['totalamount'] = $firstbonus->sum('amount');
            // 首充人数：按 member_id 统计
            $data['now']['vip']['firstbonus']['totalcount'] = $firstbonus
                ->select('member_id')
                ->distinct()
                ->count('member_id');
            $downlinedeposit = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->where('type', 'deposit')
                                ->whereIn('member_id', $downlineids);
            $downlinedeposit = queryBetweenDateEloquent($downlinedeposit, $request, 'created_on');
            // 充值金额
            $data['now']['deposit']['totalamount'] = $downlinedeposit->sum('amount');
            // 充值人次
            $data['now']['deposit']['totalcount'] = $downlinedeposit->count();
            $downlinewithdraw = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->where('type', 'withdraw')
                                ->whereIn('member_id', $downlineids);
            $downlinewithdraw = queryBetweenDateEloquent($downlinewithdraw, $request, 'created_on');
            // 提现金额
            $data['now']['withdraw']['totalamount'] = $downlinewithdraw->sum('amount');
            // 提现次数
            $data['now']['withdraw']['totalcount'] = $downlinewithdraw->count();
            // 领取奖励
            $vipreward = ['firstbonus','dailybonus','weeklybonus','monthlybonus'];
            $downlinereward = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('type', $vipreward)
                                ->whereIn('member_id', $downlineids);
            $data['now']['vip']['reward'] = $downlinereward->count();
            $tbl_performance = Performance::where('status', 1)
                                          ->where('delete', 0);
            // 直属业绩
            $data['now']['vip']['sales']['totalamount'] = TotalSales( $tbl_member, $request);
            // 总佣金
            $data['now']['vip']['commission']['totalamount'] = TotalCommission( $tbl_member, $request);
            // 有效投注
            $tbl_gamemember = Gamemember::where('status', 1)
                                ->where('delete', 0)
                                ->where('delete', 0)
                                ->whereIn('member_id', $downlineids)
                                ->get();
            $downlineplayers = $tbl_gamemember->pluck('gamemember_id')->toArray();
            $tbl_gamelog = Gamelog::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('gamemember_id', $downlineplayers);
            $tbl_gamelog = queryBetweenDateEloquent($tbl_gamelog, $request, 'created_on');
            $betamount = $tbl_gamelog->sum('betamount');
            $winloss = $tbl_gamelog->sum('winloss');
            $data['downline']['game']['totalbetamount'] = number_format((float)$betamount, 2, '.', '');
            // 直属输赢
            $data['downline']['game']['winloss'] = number_format((float)($winloss - $betamount), 2, '.', '');

            //// 总直属数据
            $tbl_recruit = Recruit::where('status', 1)
                                 ->where('delete', 0)
                                 ->where('upline', $request->input('member_id') );
            $downlineids = $tbl_recruit->pluck('member_id')->toArray();
            $selftotalsales = TotalSales( $tbl_member, null, 1, []);
            $selftotalcommission = TotalCommission( $tbl_member, null, 1, []);
            // 团队人数
            $data['team']['recruit']['main']['totalcount'] = $tbl_recruit->count();
            // 直属人数
            $alldownline = AllDownline($tbl_member, null, 2 );
            $data['team']['recruit']['downline']['totalcount'] = $alldownline['count'];
            // 总业绩
            $maintotalsales = TotalSales( $tbl_member, null, null, $alldownline);
            $data['team']['recruit']['main']['totalsales'] = $maintotalsales;
            // 总佣金
            $maintotalcommission = TotalCommission( $tbl_member, null, null, $alldownline);
            $data['team']['recruit']['main']['totalcommission'] = $maintotalcommission;
            // 直属业绩
            $downlinetotalsales = TotalSales( $tbl_member, null, 2);
            $data['team']['recruit']['downline']['totalsales'] = $downlinetotalsales;
            // 直属佣金
            $downlinetotalcommission = TotalCommission( $tbl_member, null, 2);
            $data['team']['recruit']['downline']['totalcommission'] = $downlinetotalcommission;
            // 累计佣金
            $data['team']['recruit']['accumulate']['totalcommission'] = $downlinetotalcommission - $selftotalcommission;
            // 已领取
            $data['team']['recruit']['main']['reward'] = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('type', $vipreward)
                                ->whereIn('member_id', $downlineids)
                                ->sum('amount');
            // 累计直属充值
            $data['team']['recruit']['downline']['totaldeposit']  = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->where('type', 'deposit')
                                ->whereIn('member_id', $alldownline['member_ids'])
                                ->sum('amount');
            // 累计直属提现
            $data['team']['recruit']['downline']['totalwithdraw']  = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->where('type', 'withdraw')
                                ->whereIn('member_id', $alldownline['member_ids'])
                                ->sum('amount');
            // 累计直属领取
            $data['team']['recruit']['downline']['reward'] = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('type', $vipreward)
                                ->whereIn('member_id', $alldownline['member_ids'])
                                ->sum('amount');
            $tbl_gamemember = Gamemember::where('status', 1)
                                ->where('delete', 0)
                                ->where('delete', 0)
                                ->whereIn('member_id', $alldownline['member_ids'])
                                ->get();
            $downlineplayers = $tbl_gamemember->pluck('gamemember_id')->toArray();
            $tbl_gamelog = Gamelog::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('gamemember_id', $downlineplayers);
            $betamount = $tbl_gamelog->sum('betamount');
            $winloss = $tbl_gamelog->sum('winloss');
            // 累计直属有效投注
            $data['team']['recruit']['downline']['betamount'] = number_format((float)$betamount, 2, '.', '');
            // 累计直属输赢
            $data['team']['recruit']['downline']['winloss'] = number_format((float)($winloss), 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $data,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * downline list. 推广赚钱 下线信息
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downlinelist(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'startdate' => 'nullable|date',
            'enddate' => 'nullable|date|after_or_equal:startdate',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_invitationhistory = Invitationhistory::where('status', 1)
                                                      ->where('delete', 0)
                                                      ->where('upline', $request->input('member_id') )
                                                      ->when(
                                                          is_null($tbl_member->agent_id),
                                                          fn($q) => $q->whereNull('agent_id'),
                                                          fn($q) => $q->where('agent_id', $tbl_member->agent_id)
                                                      )
                                                      ->with('Member');
            $tbl_invitationhistory = queryBetweenDateEloquent($tbl_invitationhistory, $request, 'registered_on');
            $tbl_invitationhistory = $tbl_invitationhistory->get();
            $alldownline = [];
            foreach ($tbl_invitationhistory as $key => $invitationhistory) {
                $alldownline[$key]['member_id'] = $invitationhistory->member_id;
                $alldownline[$key]['member_login'] = $invitationhistory->Member->member_login;
                $alldownline[$key]['member_name'] = $invitationhistory->Member->member_name;
                $alldownline[$key]['registered_on'] = $invitationhistory->registered_on;
                $alldownline[$key]['invitecode'] = $invitationhistory->invitecode;
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $alldownline,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * total commission list. 推广赚钱 我的业绩
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function totalcommissionlist(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'startdate' => 'nullable|date',
            'enddate' => 'nullable|date|after_or_equal:startdate',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $start = Carbon::parse($request->startdate ?? now()->startOfMonth());
            $end   = Carbon::parse($request->enddate   ?? now());
            // $tbl_performance = Performance::where('member_id', $request->member_id)
            //     ->where('status', 1)
            //     ->where('delete', 0)
            //     ->whereBetween('created_on', [$start, $end])
            //     ->selectRaw('DATE(created_on) as date, COUNT(member_id) as total_people, SUM(commission_amount) as total_commission')
            //     ->groupBy('date')
            //     ->orderBy('date', 'desc')
            //     ->get()
            //     ->keyBy('date');
            // $results = [];
            // $current = $end->copy();
            // while ($current->gte($start)) {
            //     $dateString = $current->toDateString();
            //     $total_commission = $tbl_performance[$dateString]->total_commission ?? 0;
            //     $total_people = $tbl_performance[$dateString]->total_people ?? 0;
            //     if ( $total_commission === 0 && $total_people === 0 ) {
            //         continue;
            //     }
            //     $results[] = [
            //         'date' => $dateString,
            //         'total_commission' => $total_commission,
            //         'total_people'     => $total_people,
            //     ];
            //     $current->subDay();
            // }
            $tbl_performance = Performance::where('member_id', $request->member_id)
                ->where('status', 1)
                ->where('delete', 0)
                ->whereBetween('created_on', [$start, $end])
                ->selectRaw('DATE(created_on) as date, COUNT(member_id) as total_people, SUM(commission_amount) as total_commission')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            $results = $tbl_performance->map(function($item) {
                return [
                    'date' => $item->date,
                    'total_commission' => $item->total_commission,
                    'total_people' => $item->total_people,
                ];
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $results,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * commission list. 推广赚钱 我的佣金
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function commissionlist(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'startdate' => 'nullable|date',
            'enddate' => 'nullable|date|after_or_equal:startdate',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_performance = ListCommission( $tbl_member, $request);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_performance,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * invite list. 推广赚钱 我的邀请码
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myinvitelist(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( !is_null( $tbl_member->agent_id ) ) {
                $tbl_agent = Agent::where('agent_id', $tbl_member->agent_id )
                                ->where('status', 1)
                                ->where('delete', 0)
                                ->first();
                if (!$tbl_agent) {
                    return response()->json([
                        'status' => false,
                        'message' => __('agent.no_data_found'),
                        'error' => __('agent.no_data_found'),
                        'code' => 400,
                    ], 400);
                }
                $params['agentCode'] = md5($tbl_agent->agent_code);
            }
            $tbl_invitation = Invitation::where( 'status', 1 )
                                        ->where( 'delete', 0 )
                                        ->where( 'member_id', $request->input('member_id') )
                                        ->get();
            $invitelist = [];
            foreach ($tbl_invitation as $key => $invitation) {
                $params['referralCode'] = $invitation->invitecode;
                $invitelist[$key]['invitation_id'] = $invitation->invitation_id;
                $invitelist[$key]['invitecode_name'] = $invitation->invitecode_name;
                $invitelist[$key]['referralCode'] = $invitation->invitecode;
                $invitelist[$key]['qr'] = config('app.urldownload') . "user-download?". http_build_query($params);
                $tbl_recruit = Recruit::where( 'status', 1 )
                                      ->where( 'delete', 0 )
                                      ->where( 'member_id', $request->input('member_id') )
                                      ->where( 'invitecode', $invitation->invitecode )
                                      ->first();
                $invitelist[$key]['default'] = $tbl_recruit ? 1 : 0;
                $invitelist[$key]['created_on'] = $invitation->created_on;
            }
            $invitelist = collect($invitelist)->sortBy([
                ['default', 'desc'],
                ['created_on', 'desc'],
            ])->values()->all();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $invitelist,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * new invite code. 推广赚钱 成新邀二维码
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function newinvitecode(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'default' => 'required|integer|in:0,1',
            'invitecode' => 'nullable|string|max:10',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( $request->filled('invitecode') ) {
                $tbl_invitation = Invitation::where( 'status', 1 )
                                            ->where( 'delete', 0 )
                                            ->where( 'invitecode', $request->input('invitecode') )
                                            ->first();
                if ( $tbl_invitation ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('performance.duplicateinvitecode'),
                            'error' => $e->getMessage(),
                        ],
                        401
                    );
                }
                $invitecode = $request->input('invitecode');
            } else {
                $invitecode = Recruit::newcode();
            }
            $tbl_invitation = Invitation::create([
                'invitecode' => $invitecode,
                'member_id' => $request->input('member_id'),
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ( $request->input('default') === 1 ) {
                Recruit::where('status', 1)
                        ->where('delete', 0)
                        ->where('member_id', $request->input('member_id'))
                        ->update([
                            'invitecode' => $invitecode,
                            'updated_on' => now(),
                        ]);
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('performance.invitecode_addsuccess'),
                    'error' => "",
                    'data' => $tbl_invitation,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * edit invite code. 推广赚钱 编辑邀二维码
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function editinvitecodedefault(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'invitation_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_invitation = Invitation::where( 'status', 1 )
                                        ->where( 'delete', 0 )
                                        ->where( 'invitation_id', $request->input('invitation_id') )
                                        ->first();
            if ( !$tbl_invitation ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('performance.invitecode_no_data'),
                        'error' => $e->getMessage(),
                    ],
                    401
                );
            }
            $tbl_recruit = Recruit::where('status', 1)
                                  ->where('delete', 0)
                                  ->where('member_id', $tbl_invitation->member_id)
                                  ->first();
            if ( !$tbl_recruit ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.referral_code_invalid'),
                        'error' => __('member.referral_code_invalid'),
                    ],
                    401
                );
            }
            $tbl_recruit->update([
                'invitecode' => $tbl_invitation->invitecode,
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('performance.invitecode_editsuccess'),
                    'error' => "",
                    'data' => $tbl_recruit->fresh(),
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * edit invite code. 推广赚钱 编辑邀二维码名字
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function editinvitecodename(Request $request) 
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'invitation_id' => 'required|integer',
            'invitecode_name' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_invitation = Invitation::where( 'status', 1 )
                                        ->where( 'delete', 0 )
                                        ->where( 'invitation_id', $request->input('invitation_id') )
                                        ->first();
            if ( !$tbl_invitation ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('performance.invitecode_no_data'),
                        'error' => $e->getMessage(),
                    ],
                    401
                );
            }
            $tbl_invitation->update([
                'invitecode_name' => $request->input('invitecode_name') ?? null,
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('performance.invitecode_editsuccess'),
                    'error' => "",
                    'data' => $tbl_invitation->fresh(),
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * register referral. 推广赚钱 直属开户
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerreferral(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'phone' => 'required|string|max:255',
            'password' => 'required|string|min:6|max:255',
            'member_login' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( $request->filled('member_login') ) {
                $login = DB::table('tbl_member')
                    ->where('member_login', $request->input('member_login'))
                    ->first();
                if ($login) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.loginduplicate'),
                            'error' => __('messages.loginduplicate'),
                        ],
                        422
                    );
                }
                $member_login = $request->input('member_login');
            } else {
                $phone = DB::table('tbl_member')
                    ->where('phone', $request->input('phone'))
                    ->first();
                if ($phone) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.phoneduplicate'),
                            'error' => __('messages.phoneduplicate'),
                        ],
                        422
                    );
                }
                $member_login = $request->input('phone');
            }
            $agent_id = null;
            $tbl_agent = null;
            if ( !is_null($tbl_member->agent_id) ) {
                $tbl_agent = Agent::where('status', 1)
                                  ->where('delete', 0);
                $tbl_agent = $tbl_agent->where('agent_id', $tbl_member->agent_id );
                $tbl_agent = $tbl_agent->first();
                if (!$tbl_agent) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('agent.no_data_found'),
                            'error' => __('agent.no_data_found'),
                        ],
                        400
                    );
                }
                $agent_id = $tbl_agent->agent_id;
            }
            $invitecode = Recruit::newcode();
            $tbl_downline = Member::create([
                'member_login' => $member_login,
                'member_pass' => encryptPassword( $request->input('password') ),
                'member_name' => $member_login,
                'phone' => $request->input('phone'),
                'whatsapp' => $request->input('phone'),
                'area_code' => null,
                'agent_id' => $agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_uplinerecruit = Recruit::where('status', 1)
                                        ->where('delete', 0)
                                        ->where('member_id', $tbl_member->member_id)
                                        ->first();
            $tbl_downlinerecruit = Recruit::create([
                'member_id' => $tbl_downline->member_id,
                'title' => "newbie",
                'upline' => $tbl_member->member_id,
                'invitecode' => $invitecode,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_invitation = Invitation::create([
                'invitecode' => $invitecode,
                'member_id' => $tbl_downline->member_id,
                'agent_id' => $agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_invitationhistory = Invitationhistory::create([
                'invitecode' => $tbl_uplinerecruit->invitecode,
                'member_id' => $tbl_downline->member_id,
                'upline' => $tbl_member->member_id,
                'agent_id' => $agent_id,
                'registered_on' => now(),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            LogCreateAccount( $tbl_downline, "member", $tbl_downline->member_name, $request );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('member.member_added_successfully'),
                    'error' => "",
                    'data' => $tbl_downline,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * profile. 推广赚钱 我的邀请统计
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_recruit = Recruit::where('status', 1)
                                 ->where('delete', 0)
                                 ->where('member_id', $request->input('member_id') )
                                 ->first();
            if (!$tbl_recruit) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ( !is_null( $tbl_member->agent_id ) ) {
                $tbl_agent = Agent::where('agent_id', $tbl_member->agent_id )
                                ->where('status', 1)
                                ->where('delete', 0)
                                ->first();
                if (!$tbl_agent) {
                    return response()->json([
                        'status' => false,
                        'message' => __('agent.no_data_found'),
                        'error' => __('agent.no_data_found'),
                        'code' => 400,
                    ], 400);
                }
                $params['agentCode'] = md5($tbl_agent->agent_code);
            }
            $params['referralCode'] = $tbl_recruit->invitecode;
            $alldownline = AllDownline($tbl_member);
            $profile['invitecode'] = $tbl_recruit->invitecode; // 邀请码
            $profile['qr'] = config('app.urldownload') . "user-download?". http_build_query($params);
            $profile['totaldownline'] = $alldownline['count']; // 团队人数
            $tbl_credit = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('type', ['deposit','withdraw'])
                                ->where('member_id', $request->input('member_id') );
            $profile['totalcredit'] = $tbl_credit
                ->select('member_id')
                ->distinct()
                ->count('member_id'); // 交易人数
            $tbl_downline = Recruit::where('status', 1)
                                 ->where('delete', 0)
                                 ->where('upline', $request->input('member_id') );
            $downlineids = $alldownline['member_ids']; // 团队会员ID's
            $tbl_performance = Performance::where('status', 1)
                                          ->where('delete', 0)
                                          ->whereIn('member_id', $downlineids);
            $myperformance = Performance::where('status', 1)
                                          ->where('delete', 0)
                                          ->where('member_id', $request->input('member_id') );
            $profile['totalcommission'] = $tbl_performance->sum('commission_amount') + 
                                            $myperformance->sum('commission_amount'); // 我的返利
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $profile,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * register referral. 推广赚钱 好友列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function friendlist(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_recruit = Recruit::where('status', 1)
                                  ->where('delete', 0)
                                  ->where('upline', $request->input('member_id') )
                                  ->get();
            // $downlineids = $tbl_recruit->pluck('member_id')->toArray();
            $recruitist = [];
            foreach ($tbl_recruit as $key => $recruit) {
                $downlineids = [];
                $recruitist[$key]['member_id'] = $recruit->member_id;
                $recruitist[$key]['referralCode'] = $recruit->invitecode;
                // 返利时间
                $latest_credit = Credit::where('status', 1)
                        ->where('delete', 0)
                        ->where('type', 'commission')
                        ->where('member_id', $recruit->member_id)
                        ->orderBy('created_on', 'desc') // Order by latest date
                        ->first(); // Get only the latest record
                $recruitist[$key]['created_on'] = $latest_credit ? $latest_credit->created_on : null;
                //被邀请人数
                $tbl_downline = Recruit::where('status', 1)
                                    ->where('delete', 0)
                                    ->where('upline', $recruit->member_id )
                                    ->get();
                $downlineids = $tbl_downline->pluck('member_id')->toArray();
                $downlineids[] = $recruit->member_id;
                $recruitist[$key]['invitecount'] = $tbl_downline->count();
                $downlinecommission = Credit::where('status', 1)
                                    ->where('delete', 0)
                                    ->where('type', 'commission')
                                    ->whereIn('member_id', $downlineids );
                $tbl_credit = Credit::where('status', 1)
                                    ->where('delete', 0)
                                    ->where('type', 'commission')
                                    ->whereIn('member_id', $downlineids );
                $recruitist[$key]['creditcount'] = $downlinecommission->selectRaw(
                            'member_id, COUNT(*) as creditcount, SUM(amount) as creditamount'
                        )
                        ->groupBy('member_id')
                        ->get()
                        ->keyBy('member_id')
                        ->count(); //已交易人数
                $recruitist[$key]['creditamount'] = $tbl_credit->sum('amount'); //交易总数额
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $recruitist,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * register referral. 推广赚钱 我的返利
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mycommission(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $recruitist = [];
            $tbl_recruit = Recruit::where('status', 1)
                                  ->where('delete', 0)
                                  ->where('upline', $request->input('member_id') )
                                  ->get();
            $downlineids = $tbl_recruit->pluck('member_id')->toArray();
            // $downlinecommission = Credit::where('status', 1)
            //                     ->where('delete', 0)
            //                     ->where('type', 'commission')
            //                     ->whereIn('member_id', $downlineids);
            $downlinecommission = Performance::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('member_id', $downlineids);
            $tbl_downlinecommission = $downlinecommission->get();
            foreach ($tbl_downlinecommission as $key => $commission) {
                $recruitist[$key]['created_on'] = $commission->created_on; //返利时间
                $recruitist[$key]['member_id'] = $commission->member_id; //被邀请人ID
                $recruitist[$key]['amount'] = $commission->commission_amount; //返利数量
            }
            $recruitist = collect($recruitist)->sortBy([
                ['member_id', 'asc'],
                ['created_on', 'desc'],
            ])->values()->all();
            // $tbl_performance = Performance::where('status', 1)
            //                               ->where('delete', 0)
            //                               ->where('member_id', $request->input('member_id') )
            //                               ->get();
            // foreach ($tbl_performance as $key => $performance) {
            //     // $tbl_recruit = Recruit::where('status', 1)
            //     //                     ->where('delete', 0)
            //     //                     ->where('member_id', $performance->downline )
            //     //                     ->first();
            //     $recruitist[$key]['created_on'] = $performance->created_on; //返利时间
            //     $recruitist[$key]['member_id'] = $performance->downline; //被邀请人ID
            //     $recruitist[$key]['amount'] = $performance->commission_amount; //返利数量
            // }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $recruitist,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }
}
