<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Questionrelated;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    /**
     * List tbl_question.
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
            'question_type' => 'nullable|in:commonquestion,transactioncontrol,transfer,vip',
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
            $lang = $request->getPreferredLanguage();
            $tbl_question = Question::where('status', 1)
                                    ->where('delete', 0)
                                    ->where(function ($q) use ($tbl_member) {
                                        $q->where('agent_id', 0);
                                        if (!is_null($tbl_member->agent_id)) {
                                            $q->orWhere('agent_id', $tbl_member->agent_id);
                                        }
                                    })
                                    ->where('lang', $lang);
            if ($request->filled('question_type')) {
                $tbl_question = $tbl_question->where('question_type', $request->input('question_type') );
            }
            $tbl_question = $tbl_question->get();
            foreach ($tbl_question as $key => $question) {
                if ( is_null( $question->question_id ) ) {
                    $tbl_question[$key]['children'] = [];
                    continue;
                }
                $tbl_questionrelated = Questionrelated::where('status', 1)
                                                      ->where('delete', 0)
                                                      ->where('question_id', $question->question_id )
                                                      ->get();
                $tbl_question[$key]['children'] = $tbl_questionrelated;
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'data' => $tbl_question,
                    'error' => "",
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
