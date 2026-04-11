<?php

namespace App\Http\Controllers;

use App\Models\VIP;
use App\Models\Member;
use App\Models\Score;
use App\Models\Credit;
use App\Models\Agent;
use App\Models\Agentcredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class VIPController extends Controller
{
    /**
     * list vip. 
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
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
            'type' => 'nullable|string|in:firstbonus,dailybonus,weeklybonus,monthlybonus',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->first();
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
            // Up level 
            $tbl_member = UpVIPLevel( $tbl_member );
            $tbl_vip = VIP::where('status', 1)
                          ->where('delete', 0)
                          ->when(
                              is_null($tbl_member->agent_id),
                              fn($q) => $q->where('agent_id', 0),
                              fn($q) => $q->where('agent_id', $tbl_member->agent_id)
                          )
                          ->get();
            $tbl_score = Score::where('status', 1)
                                ->where('delete', 0)
                                ->where('type', 'deposit')
                                ->where('member_id', $request->input('member_id') )
                                ->first();
            foreach ($tbl_vip as $key => $vip) {
                if ( !$tbl_score ) {
                    $tbl_vip[$key]['score'] = 0.00;
                    continue;
                }
                if ( $tbl_score->amount > $vip->min_amount && $tbl_score->amount < $vip->max_amount ) {
                    $tbl_vip[$key]['score'] = $tbl_score->amount;
                } else {
                    $tbl_vip[$key]['score'] = 0.00;
                }
            }
            $remain = null;
            if ( $request->filled('type') ) {
                if ( $request->input('type') !== 'firstbonus' ) {
                    $remain = getBonusRemainingDays( $tbl_member, $request->input('type') );
                }
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_vip,
                    'remain' => $remain,
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
     * first bonus.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function firstbonus(Request $request)
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
            'vip_id' => 'nullable|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('MyVip')
                                ->lockForUpdate()
                                ->first();
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
            $result = checkFirstBonusEligibility($tbl_member);
            if (!$result['eligible']) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => $result['message'],
                    'error' => $result['message'],
                ], 401);
            }
            $agent_id = $tbl_member->agent_id ?? 0;
            $tbl_agent = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->where('agent_id', $agent_id)
                            ->lockForUpdate()
                            ->first();
            $reason = null;
            if ( $request->filled('vip_id') ) {
                $tbl_vip = VIP::where('status', 1)
                            ->where('delete', 0)
                            ->when(
                                is_null($tbl_member->agent_id),
                                fn($q) => $q->where('agent_id', 0),
                                fn($q) => $q->where('agent_id', $tbl_member->agent_id)
                            )
                            ->where('vip_id', $request->input('vip_id') )
                            ->first();
                if (!$tbl_vip) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('vip.no_data_found'),
                            'error' => __('vip.no_data_found'),
                        ],
                        400
                    );
                }
                if ( $tbl_agent->balance < $tbl_vip->firstbonus ) {
                    $reason = 'agent.insufficient';
                    $status = -1;
                } else {
                    $status = 1;
                }
                $tbl_credit = Credit::create([
                    'member_id' => $tbl_member->member_id,
                    'type' => 'firstbonus',
                    'amount' => $tbl_vip->firstbonus,
                    'before_balance' => $tbl_member->balance,
                    'after_balance' => $tbl_member->balance + $tbl_vip->firstbonus,
                    'submit_on' => now(),
                    'agent_id' => $tbl_member->agent_id,
                    'reason' => $reason,
                    'status' => $status,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                $tbl_agentcredit = Agentcredit::create([
                    'agent_id' => $tbl_agent->agent_id,
                    'member_id' => $tbl_member->member_id,
                    'type' => 'vip.firstbonus',
                    'amount' => $tbl_vip->firstbonus,
                    'before_balance' => $tbl_agent->balance,
                    'after_balance' => $tbl_agent->balance - $tbl_vip->firstbonus,
                    'submit_on' => now(),
                    'reason' => $reason,
                    'status' => $status,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                if ( $status === 1 ) {
                    $tbl_member->increment('balance', $tbl_vip->firstbonus);
                    $tbl_agent->decrement('balance', $tbl_vip->firstbonus);
                }
            } else {
                // 一键领取
                foreach ($result['viplist'] as $key => $firstbonus) {
                    if ( $tbl_agent->balance < $firstbonus->bonus_amount ) {
                        $reason = 'agent.insufficient';
                        $status = -1;
                    } else {
                        $status = 1;
                    }
                    $tbl_credit = Credit::create([
                        'member_id' => $tbl_member->member_id,
                        'type' => 'firstbonus',
                        'amount' => $firstbonus->bonus_amount,
                        'before_balance' => $tbl_member->balance,
                        'after_balance' => $tbl_member->balance + $firstbonus->bonus_amount,
                        'submit_on' => now(),
                        'agent_id' => $tbl_member->agent_id,
                        'reason' => $reason,
                        'status' => $status,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    $tbl_agentcredit = Agentcredit::create([
                        'agent_id' => $tbl_agent->agent_id,
                        'member_id' => $tbl_member->member_id,
                        'type' => 'vip.firstbonus',
                        'amount' => $firstbonus->bonus_amount,
                        'before_balance' => $tbl_agent->balance,
                        'after_balance' => $tbl_agent->balance - $firstbonus->bonus_amount,
                        'submit_on' => now(),
                        'reason' => $reason,
                        'status' => $status,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    if ( $status === 1 ) {
                        $tbl_member->increment('balance', $firstbonus->bonus_amount);
                        $tbl_agent->decrement('balance', $firstbonus->bonus_amount);
                    }
                }
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('vip.firstbonus_gain'),
                    'error' => "",
                    'credit' => $tbl_credit->fresh(),
                    'member' => $tbl_member->fresh(),
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
     * daily bonus.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dailybonus(Request $request)
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('MyVip')
                                ->lockForUpdate()
                                ->first();
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
            $result = checkBonusEligibility($tbl_member, 'dailybonus');
            if (!$result['eligible']) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => $result['message'],
                    'error' => $result['message'],
                ], 400);
            }
            $agent_id = $tbl_member->agent_id ?? 0;
            $tbl_agent = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->where('agent_id', $agent_id)
                            ->lockForUpdate()
                            ->first();
            $reason = null;
            if ( $tbl_agent->balance < $tbl_member->MyVip->dailybonus ) {
                $reason = 'agent.insufficient';
                $status = -1;
            } else {
                $status = 1;
            }
            $tbl_credit = Credit::create([
                'member_id' => $tbl_member->member_id,
                'type' => 'dailybonus',
                'amount' => $tbl_member->MyVip->dailybonus,
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $tbl_member->MyVip->dailybonus,
                'submit_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_agentcredit = Agentcredit::create([
                'agent_id' => $tbl_agent->agent_id,
                'member_id' => $tbl_member->member_id,
                'type' => 'vip.dailybonus',
                'amount' => $tbl_member->MyVip->dailybonus,
                'before_balance' => $tbl_agent->balance,
                'after_balance' => $tbl_agent->balance - $tbl_member->MyVip->dailybonus,
                'submit_on' => now(),
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ( $status === 1 ) {
                $tbl_member->increment('balance', $tbl_member->MyVip->dailybonus);
                $tbl_agent->decrement('balance', $tbl_member->MyVip->dailybonus);
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('vip.dailybonus_gain'),
                    'error' => "",
                    'credit' => $tbl_credit->fresh(),
                    'member' => $tbl_member->fresh(),
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
     * weekly bonus.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function weeklybonus(Request $request)
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('MyVip')
                                ->lockForUpdate()
                                ->first();
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
            $result = checkBonusEligibility($tbl_member, 'weeklybonus');
            if (!$result['eligible']) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => $result['message'],
                    'error' => $result['message'],
                ], 400);
            }
            $agent_id = $tbl_member->agent_id ?? 0;
            $tbl_agent = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->where('agent_id', $agent_id)
                            ->lockForUpdate()
                            ->first();
            $reason = null;
            if ( $tbl_agent->balance < $tbl_member->MyVip->weeklybonus ) {
                $reason = 'agent.insufficient';
                $status = -1;
            } else {
                $status = 1;
            }
            $tbl_credit = Credit::create([
                'member_id' => $tbl_member->member_id,
                'type' => 'weeklybonus',
                'amount' => $tbl_member->MyVip->weeklybonus,
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $tbl_member->MyVip->weeklybonus,
                'submit_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_agentcredit = Agentcredit::create([
                'agent_id' => $tbl_agent->agent_id,
                'member_id' => $tbl_member->member_id,
                'type' => 'vip.weeklybonus',
                'amount' => $tbl_member->MyVip->weeklybonus,
                'before_balance' => $tbl_agent->balance,
                'after_balance' => $tbl_agent->balance - $tbl_member->MyVip->weeklybonus,
                'submit_on' => now(),
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ( $status === 1 ) {
                $tbl_member->increment('balance', $tbl_member->MyVip->weeklybonus);
                $tbl_agent->decrement('balance', $tbl_member->MyVip->weeklybonus);
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('vip.weeklybonus_gain'),
                    'error' => "",
                    'credit' => $tbl_credit->fresh(),
                    'member' => $tbl_member->fresh(),
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
     * monthly bonus.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlybonus(Request $request)
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('MyVip')
                                ->lockForUpdate()
                                ->first();
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
            $result = checkBonusEligibility($tbl_member, 'monthlybonus');
            if (!$result['eligible']) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => $result['message'],
                    'error' => $result['message'],
                ], 400);
            }
            $agent_id = $tbl_member->agent_id ?? 0;
            $tbl_agent = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->where('agent_id', $agent_id)
                            ->lockForUpdate()
                            ->first();
            $reason = null;
            if ( $tbl_agent->balance < $tbl_member->MyVip->monthlybonus ) {
                $reason = 'agent.insufficient';
                $status = -1;
            } else {
                $status = 1;
            }
            $tbl_credit = Credit::create([
                'member_id' => $tbl_member->member_id,
                'type' => 'monthlybonus',
                'amount' => $tbl_member->MyVip->monthlybonus,
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $tbl_member->MyVip->monthlybonus,
                'submit_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_agentcredit = Agentcredit::create([
                'agent_id' => $tbl_agent->agent_id,
                'member_id' => $tbl_member->member_id,
                'type' => 'vip.monthlybonus',
                'amount' => $tbl_member->MyVip->monthlybonus,
                'before_balance' => $tbl_agent->balance,
                'after_balance' => $tbl_agent->balance - $tbl_member->MyVip->monthlybonus,
                'submit_on' => now(),
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ( $status === 1 ) {
                $tbl_member->increment('balance', $tbl_member->MyVip->monthlybonus);
                $tbl_agent->decrement('balance', $tbl_member->MyVip->monthlybonus);
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('vip.monthlybonus_gain'),
                    'error' => "",
                    'credit' => $tbl_credit->fresh(),
                    'member' => $tbl_member->fresh(),
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
     * remain vip target.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function remaintarget(Request $request)
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('MyVip')
                                ->first();
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
            // Up level 
            $tbl_member = UpVIPLevel( $tbl_member );
            $result = getBonusRemainingLevel($tbl_member);
            if (!$result['eligible']) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => $result['message'],
                    'error' => $result['message'],
                ], 400);
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'remain' => $result['remain_amount'],
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
     * vip history. VIP领取记录
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
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
            // 'type' => 'nullable|string|in:firstbonus,dailybonus,weeklybonus,monthlybonus',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('MyVip')
                                ->first();
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
            $tbl_credit = Credit::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('type', [
                                    'firstbonus',
                                    'dailybonus',
                                    'weeklybonus',
                                    'monthlybonus',
                                ])
                                ->where('member_id', $request->input('member_id'))
                                ->orderByDesc('created_on');
            $tbl_credit = queryBetweenDateEloquent($tbl_credit, $request, 'created_on');
            $tbl_credit = $tbl_credit->get();
            $bonus['firstbonus'] = checkFirstBonusEligibility($tbl_member);
            $bonus['dailybonus'] = checkBonusEligibility($tbl_member, 'dailybonus');
            $bonus['weeklybonus'] = checkBonusEligibility($tbl_member, 'weeklybonus');
            $bonus['monthlybonus'] = checkBonusEligibility($tbl_member, 'monthlybonus');
            $history = [];
            if ($bonus['firstbonus']['eligible']) {
                foreach ($bonus['firstbonus']['viplist'] as $key => $firstbonus) {
                    $history[] = [
                        'type' => 'firstbonus',
                        'vip_id' => $firstbonus['vip_id'],
                        'lvl' => $firstbonus['lvl'],
                        'template' => __('vip.history_due',['bonus'=>$firstbonus['bonus_amount']]),
                        'expire_on' => null,
                        'status' => 0,
                    ];
                }
            }
            if ($bonus['dailybonus']['eligible']) {
                $history[] = [
                    'type' => 'dailybonus',
                    'template' => __('vip.history_due',['bonus'=>$bonus['dailybonus']['bonus_amount']]),
                    'expire_on' => $bonus['dailybonus']['expire_on'],
                    'status' => 0,
                ];
            }
            if ($bonus['weeklybonus']['eligible']) {
                $history[] = [
                    'type' => 'weeklybonus',
                    'template' => __('vip.history_due',['bonus'=>$bonus['weeklybonus']['bonus_amount']]),
                    'expire_on' => $bonus['weeklybonus']['expire_on'],
                    'status' => 0,
                ];
            }
            if ($bonus['monthlybonus']['eligible']) {
                $history[] = [
                    'type' => 'monthlybonus',
                    'template' => __('vip.history_due',['bonus'=>$bonus['monthlybonus']['bonus_amount']]),
                    'expire_on' => $bonus['monthlybonus']['expire_on'],
                    'status' => 0,
                ];
            }
            foreach ($tbl_credit as $key => $credit) {
                $history[] = [ 
                    'type' => $credit['type'],
                    'template' => __('vip.history_gain',['bonus'=>$credit['amount']]),
                    'created_on' => $credit['created_on'],
                    'status' => $credit['status'],
                ];
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $history,
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
     * vip allbonus. VIP一键领取
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function allbonus(Request $request)
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('MyVip')
                                ->first();
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
            $tbl_credit = [];
            $bonus = 0.00;
            $bonusList = [
                ['type' => 'firstbonus', 'result' => checkFirstBonusEligibility($tbl_member)],
                ['type' => 'dailybonus',   'result' => checkBonusEligibility($tbl_member, 'dailybonus')],
                ['type' => 'weeklybonus',  'result' => checkBonusEligibility($tbl_member, 'weeklybonus')],
                ['type' => 'monthlybonus', 'result' => checkBonusEligibility($tbl_member, 'monthlybonus')],
            ];
            foreach ($bonusList as $item) {
                $type = $item['type'];
                $check = $item['result'];
                if (!$check['eligible']) {
                    continue;
                }

                // FIRST BONUS → Special multiple-level logic
                if ($type === 'firstbonus') {
                    foreach ($check['viplist'] as $vip) {
                        $amount = $vip['bonus_amount'];
                        $tbl_credit['firstbonus'][] = Credit::create([
                            'member_id'       => $tbl_member->member_id,
                            'type'            => 'firstbonus',
                            'amount'          => $amount,
                            'before_balance'  => $tbl_member->balance,
                            'after_balance'   => $tbl_member->balance + $amount,
                            'submit_on'       => now(),
                            'agent_id'        => $tbl_member->agent_id,
                            'status'          => 1,
                            'delete'          => 0,
                            'created_on'      => now(),
                            'updated_on'      => now(),
                        ]);
                        // Update balance
                        $tbl_member->increment('balance', $amount);
                        $bonus += $amount;
                    }
                    // Skip normal logic and continue next bonus type
                    continue;
                }

                // Correct amount per type
                $amount = match($type) {
                    'dailybonus' => $tbl_member->MyVip->dailybonus,
                    'weeklybonus' => $tbl_member->MyVip->weeklybonus,
                    'monthlybonus' => $tbl_member->MyVip->monthlybonus,
                };
                // Create credit record
                $tbl_credit[$type] = Credit::create([
                    'member_id'       => $tbl_member->member_id,
                    'type'            => $type,
                    'amount'          => $amount,
                    'before_balance'  => $tbl_member->balance,
                    'after_balance'   => $tbl_member->balance + $amount,
                    'submit_on'       => now(),
                    'agent_id'        => $tbl_member->agent_id,
                    'status'          => 1,
                    'delete'          => 0,
                    'created_on'      => now(),
                    'updated_on'      => now(),
                ]);
                // Update balance
                $tbl_member->increment('balance', $amount);
                $bonus += $amount;
            }
            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('vip.bonus_received', ['bonus'=>$bonus]),
                'error' => "",
                'credit' => $tbl_credit,
                'member' => $tbl_member->fresh(),
                'bonus' => $bonusList,
            ], 200);
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
