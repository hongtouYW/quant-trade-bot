<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Models\Promotiontype;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PromotionController extends Controller
{
    /**
     * Search tbl_promotion.
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
            'promotion_type' => 'nullable|in:newmemberreload,cryptoreload,agent,hot,others',
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
            $lang = $request->getPreferredLanguage();
            $tbl_promotion = Promotion::with('Promotiontype')
                                      ->where('status', 1)
                                      ->where('delete', 0)
                                      ->where(function ($q) use ($tbl_member) {
                                            $q->where('agent_id', 0);
                                            if (!is_null($tbl_member->agent_id)) {
                                                $q->orWhere('agent_id', $tbl_member->agent_id);
                                            }
                                      })
                                      ->where('lang', $lang);
            if ($request->filled('promotion_type')) {
                $promotion_type = $request->input('promotion_type');
                $tbl_promotion->whereHas('Promotiontype', function ($sq) use ($promotion_type) {
                    $sq->where('promotion_type', $promotion_type);
                });
            }
            $tbl_promotion = $tbl_promotion->get()->map(function ($promotion) {
                $promotion->promotion_type = optional($promotion->Promotiontype)->promotion_type;
                return $promotion;
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_promotion,
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
