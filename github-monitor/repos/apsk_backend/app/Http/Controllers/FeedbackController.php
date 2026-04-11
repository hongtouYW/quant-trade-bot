<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Feedbacktype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{

    /**
     * Search tbl_feedbacktype.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function feedbacktypelist(Request $request, string $type = "member")
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
            'member_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'manager_id' => 'nullable|integer',
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
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_feedbacktype = Feedbacktype::where('status', 1)
                                            ->where('delete', 0)
                                            ->where('type', $type )
                                            ->get();
            $tbl_feedbacktype = $tbl_feedbacktype->map(function ($feedbacktype) {
                $feedbacktype->title = __("feedback.".$feedbacktype->title);
                $feedbacktype->feedback_type = __($feedbacktype->feedback_type);
                $feedbacktype->feedback_desc = __($feedbacktype->feedback_desc);
                return $feedbacktype;
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_feedbacktype,
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
     * Search tbl_feedback.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request, string $type = "member")
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
            'user_id' => 'required|integer',
            'feedbacktype_id' => 'nullable|integer',
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
            $tbl_table = DB::table('tbl_'.$type)
                            ->where($type.'_id', $request->input('user_id') )
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            if (!$tbl_table) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __($type.'.no_data_found'),
                        'error' => __($type.'.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_table->status !== 1 || $tbl_table->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( in_array( $type, ['member','shop'] ) ) {
                if ($tbl_table->alarm === 1 ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.profileinactive'),
                            'error' => __('messages.profileinactive'),
                        ],
                        401
                    );
                }
            }
            $tbl_feedback = Feedback::where($type.'_id', $request->input('user_id') )
                                    ->where('status', 1)
                                    ->where('delete', 0)
                                    ->with('Feedbacktype');
            if ($request->filled('feedbacktype_id')) {
                $tbl_feedback = $tbl_feedback->where('feedbacktype_id',  $request->input('feedbacktype_id') );
            }
            $tbl_feedback = $tbl_feedback->orderBy('created_on', 'desc');
            $tbl_feedback = $tbl_feedback->get();
            $tbl_feedback = $tbl_feedback->map(function ($feedback) {
                $feedback->title = $feedback->Feedbacktype ? __($feedback->Feedbacktype->feedback_type) : "";
                return $feedback;
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_feedback,
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
