<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Official;
use App\Models\Officialdomain;
use App\Models\Officialhome;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OfficialController extends Controller
{
    /**
     * official list.
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
            $officiallist = [];
            $tbl_official = Official::where('status', 1)
                                    ->where('delete', 0)
                                    ->where('type', 'agent')
                                    ->where(function ($q) use ($tbl_member) {
                                        $q->where('agent_id', 0);
                                        if (!is_null($tbl_member->agent_id)) {
                                            $q->orWhere('agent_id', $tbl_member->agent_id);
                                        }
                                    })
                                    ->first();
            if ( !$tbl_official ) {
                $tbl_official = Official::where('status', 1)
                                        ->where('delete', 0)
                                        ->where('type', 'master')
                                        ->first();
            }
            if ( !$tbl_official ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('official.no_data_found'),
                        'error' => __('official.no_data_found'),
                    ],
                    401
                );
            }
            $officiallist['email'] = $tbl_official->url;

            $tbl_officialdomain = Officialdomain::where('status', 1)
                                        ->where('delete', 0)
                                        ->where('official_id', $tbl_official->official_id)
                                        ->where(function ($q) use ($tbl_member) {
                                            $q->where('agent_id', 0);
                                            if (!is_null($tbl_member->agent_id)) {
                                                $q->orWhere('agent_id', $tbl_member->agent_id);
                                            }
                                        })
                                        ->get();
            $officialdomainurls = $tbl_officialdomain->pluck('url')->toArray();
            $tbl_officialhome = Officialhome::where('status', 1)
                                        ->where('delete', 0)
                                        ->where('official_id', $tbl_official->official_id)
                                        ->where(function ($q) use ($tbl_member) {
                                            $q->where('agent_id', 0);
                                            if (!is_null($tbl_member->agent_id)) {
                                                $q->orWhere('agent_id', $tbl_member->agent_id);
                                            }
                                        })
                                        ->get();
            $officialhomeurls = $tbl_officialhome->pluck('url')->toArray();
            $officiallist['email'] = $tbl_official->url;
            $officiallist['domains'] = $officialdomainurls;
            $officiallist['homes'] = $officialhomeurls;
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $officiallist,
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
