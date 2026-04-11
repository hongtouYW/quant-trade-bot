<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Manager;
use App\Models\Shop;
use App\Models\Member;
use App\Models\Recruit;
use App\Models\Recruittutorial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RecruittutorialController extends Controller
{
    /**
     * referal tutorial.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function tutorial(Request $request, string $type)
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
            $tbl_table = DB::table('tbl_'.$type)
                            ->where($type.'_id', $request->input($type.'_id') )
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            $lang = $request->getPreferredLanguage();
            $tbl_recruittutorial = Recruittutorial::where('status', 1)
                                                ->where('delete', 0)
                                                // ->where(function ($q) use ($tbl_table) {
                                                //     $q->where('agent_id', 0);
                                                //     if (!is_null($tbl_table->agent_id)) {
                                                //         $q->orWhere('agent_id', $tbl_table->agent_id);
                                                //     }
                                                // })
                                                ->where('lang', $lang);
            if ( !is_null($tbl_table->agent_id) ) {
                $tbl_recruittutorial = $tbl_recruittutorial->where('agent_id', $tbl_table->agent_id);
            } else {
                $tbl_recruittutorial = $tbl_recruittutorial->where('agent_id', 0);
            }
            $tbl_recruittutorial = $tbl_recruittutorial->first();
            if ( !$tbl_recruittutorial ) {
                $tbl_recruittutorial = Recruittutorial::where('status', 1)
                                                    ->where('delete', 0)
                                                    ->where('agent_id', 0)
                                                    ->where('lang', $lang)
                                                    ->first();
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_recruittutorial,
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
