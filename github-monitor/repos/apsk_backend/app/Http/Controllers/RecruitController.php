<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Manager;
use App\Models\Shop;
use App\Models\Member;
use App\Models\Recruit;
use App\Models\Invitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RecruitController extends Controller
{

    /**
     * referal qr.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function referralqr(Request $request)
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
            $tbl_recruit = Recruit::where('member_id', $tbl_member->member_id)
                                  ->first();
            if (!$tbl_recruit) {
                $invitecode = Recruit::newcode();
                $tbl_recruit = Recruit::create([
                    'member_id' => $tbl_member->member_id,
                    'title' => "newbie",
                    'invitecode' => $invitecode,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                $tbl_invitation = Invitation::create([
                    'invitecode' => $invitecode,
                    'member_id' => $tbl_member->member_id,
                    'agent_id' => $tbl_member->agent_id,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            } else {
                if ( is_null( $tbl_recruit->invitecode ) ) {
                    $tbl_recruit->update([
                        'invitecode' => Recruit::newcode(),
                        'updated_on' => now(),
                    ]);
                    $tbl_recruit = $tbl_recruit->fresh();
                }
            }
            $params['referralCode'] = $tbl_recruit->invitecode;
            $link = config('app.urldownload') . "user-download?". http_build_query($params);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'qr' => $link,
                    'referralCode' => $tbl_recruit->invitecode,
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
     * referal qr scan.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function referralqrscan(Request $request, $invitecode)
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
        $validator = Validator::make(
            [
                'invitecode' => $invitecode,
            ],
            [
                'invitecode' => 'required|string',
            ]
        );
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
            $tbl_member = Member::where( 'member_id', $member_id )->first();
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
            $tbl_recruit = Recruit::whereRaw('MD5(invitecode) = ?', [ $invitecode ])
                                  ->where('upline', $member_id)
                                  ->first();
            if (!$tbl_recruit) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.referral_exist' , ['member_name' => $tbl_member->member_name ] ),
                        'error' => __('member.referral_exist', ['member_name' => $tbl_member->member_name ] ),
                    ],
                    400
                );
            }
            $tbl_recruit_exist = Recruit::where('upline', $authorizedUser->currentAccessToken()->tokenable_id)
                                  ->where('member_id', $member_id)
                                  ->first();
            if ($tbl_recruit_exist) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.referral_exist' , ['member_name' => $tbl_member->member_name ] ),
                        'error' => __('member.referral_exist', ['member_name' => $tbl_member->member_name ] ),
                    ],
                    400
                );
            }
            $invitecode = Recruit::newcode();
            $tbl_recruit = Recruit::create([
                'member_id' => $authorizedUser->currentAccessToken()->tokenable_id,
                'title' => "newbie",
                'upline' => $member_id,
                'invitecode' => $invitecode,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_invitation = Invitation::create([
                'invitecode' => $invitecode,
                'member_id' => $tbl_member->member_id,
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_recruit,
                    'status' => true,
                    'message' => __('member.referral_added_successfully', ['member_name' => $tbl_member->member_name ] ),
                    'error' => '',
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
     * downline referal list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downlinereferrallist(Request $request)
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
            $tbl_recruit = Recruit::where('member_id', $tbl_member->member_id)
                                  ->first();
            if (!$tbl_recruit) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.referral_exist' , ['member_name' => $tbl_member->member_name ] ),
                        'error' => __('member.referral_exist', ['member_name' => $tbl_member->member_name ] ),
                    ],
                    400
                );
            }
            $tbl_downline = Recruit::where('upline', $tbl_member->member_id)
                                  ->get();
            // if ( !is_null( $tbl_member->agent_id ) ) {
            //     $tbl_agent = Agent::where('agent_id', $tbl_member->agent_id )
            //                     ->where('status', 1)
            //                     ->where('delete', 0)
            //                     ->first();
            //     if (!$tbl_agent) {
            //         return response()->json([
            //             'status' => false,
            //             'message' => __('agent.no_data_found'),
            //             'error' => __('agent.no_data_found'),
            //             'code' => 400,
            //         ], 400);
            //     }
            // }
            // foreach ($tbl_downline as $key => $downline) {    
            //     $params['referralCode'] = $downline->invitecode;
            //     if ( !is_null( $tbl_member->agent_id ) ) {
            //         $params['agentCode'] = md5($tbl_agent->agent_code);
            //         $downline->link = config('app.urldownload') . "user-download?". http_build_query($params);
            //     }
            // }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
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
     * test add commission.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addcommission(Request $request)
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
            'amount' => 'required|numeric|gt:1.00',
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
            $salestarget = AddCommission( $tbl_member, $request->input('amount') );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('vip.commission_added_successfully'),
                    'error' => "",
                    'salestarget' => $salestarget,
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
