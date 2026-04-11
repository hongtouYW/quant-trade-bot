<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class AgentController extends Controller
{
    /**
     * agent icon.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function agenticon(Request $request, string $type)
    {
        $validator = Validator::make($request->all(), [
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'nullable|integer',
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
            if ($request->filled('manager_id') || 
                $request->filled('shop_id') || 
                $request->filled('member_id') 
            ) {
                $checkuser = CheckAvailabilityUser( $request->input("{$type}_id"), $type);
                if ( $checkuser ) {
                    return sendEncryptedJsonResponse(
                        $checkuser,
                        401
                    );
                }
                $tbl_table = DB::table("tbl_{$type}")
                            ->where("{$type}_id", $request->input("{$type}_id"))
                            ->first();
                $agent_id = !is_null($tbl_table->agent_id) ? $tbl_table->agent_id : 0;
            } else {
                $agent_id = 0;
            }
            $tbl_agent = Agent::where('status', 1)
                              ->where('delete', 0)
                              ->where('agent_id', $agent_id )
                              ->first();
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
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'icon' => $tbl_agent->icon,
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
     * agent code.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function agentcode(Request $request)
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
            $tbl_agent = Agent::where('agent_id', $tbl_member->agent_id )
                              ->where('status', 1)
                              ->where('delete', 0)
                              ->first();
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
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'agent_code' => $tbl_agent->agent_code,
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
     * QR agent.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function agentqr(Request $request)
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
            $payload = Crypt::encryptString(json_encode([
                'member_id' => $request->input('member_id'),
                'agent_code' => $request->input('agent_code'),
            ]));
            $link = url('/api/agent/qr/scan/' . $payload );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'qr' => $link,
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
     * support link.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function supportlink(Request $request)
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
            $tbl_agent = Agent::where('status', 1)
                              ->where('delete', 0);
            $agent_id = !is_null($tbl_member->agent_id) ? $tbl_member->agent_id : 0;
            $tbl_agent = $tbl_agent->where('agent_id', $agent_id );
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
            $support = null;
            if ( $tbl_agent->isChatAccountCreate ) {
                $support = $tbl_agent->support."?pid=apsky_".$tbl_agent->agent_code."&uid=".$tbl_member->phone;
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'support' => $support,
                    'telegramsupport' => $tbl_agent->telegramsupport,
                    'whatsappsupport' => $tbl_agent->whatsappsupport,
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
