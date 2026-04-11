<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Credit;
use App\Models\Member;
use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Recruit;
use App\Models\Superpaycallback;
use Carbon\Carbon;

class SuperpayController extends Controller
{
    public function callbackdeposit(Request $request)
    {
        try {
            $response = $request->all();
            $tbl_superpaycallback = Superpaycallback::create([
                'response' => json_encode($response),
                // 'error' => $response['data']['status'] === 'SUCCESS' ? 1 : 0,
                'error' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $datarequest = \Superpay::symDecrypt($response["encryptedData"]);
            $data = json_decode($datarequest, true);
            if ( !$data['data'] ) {
                return [
                    'success'   => 0,
                    'message' => __('superpay.createorder_fail'),
                ];
            }
            $status = $data['data']['status'] === "SUCCESS" ? 'success' : 'failed';
            $message = $status === 'success' ? __('credit.active') : __('credit.inactive');
            $tbl_credit = Credit::where('orderid', $data['data']['order']['id'] )
                                ->where('payment_id', 4)
                                ->where('type', 'deposit' )
                                ->first();
            if ( !$tbl_credit )
            {
                return [
                    'success'   => 0,
                    'message' => __('credit.no_data_found'),
                ];
            }
            $tbl_member = Member::where('member_id', $tbl_credit->member_id )
                                ->first();
            if (!$tbl_member) {
                return [
                    'success'   => 0,
                    'message' => __('member.no_data_found'),
                ];
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return [
                    'status' => false,
                    'message' => __('member.member_id') . " " . __('member.Inactive') ,
                ];
            }
            $amount = (float) $tbl_credit->amount;
            $tbl_credit->update([
                'charge' => $data['data']['transaction']['commissionFee'],
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $amount,
                'status' => $data['data']['status'] === "SUCCESS" ? 1 : -1,
                'reason' => $data['data']['status'] === "SUCCESS" ? null : 'credit.inactive',
                'updated_on' => now(),
            ]);
            // if ( $data['data']['status'] === "SUCCESS" ) {
            $tbl_credit = $tbl_credit->fresh();
            if ( (int) $tbl_credit->status === 1 ) {
                $tbl_member->increment('balance', $amount, [
                    'updated_on' => now(),
                ]);
                // VIP Score
                $tbl_score = AddScore( $tbl_member, 'deposit', $amount );
                // Commission
                $salestarget = AddCommission( $tbl_member, $amount );
            }
            return [
                'success'   => 1,
                'message' => __('superpay.callback_success'),
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::info('API Superpay CallBack Request', $e->getMessage() );
            return [
                'success'   => 0,
                'message'   => $e->getMessage(),
                'errorCode' => 500,
            ];
        }
    }

    public function callbackwithdraw(Request $request)
    {
        try {
            $response = $request->all();
            $tbl_superpaycallback = Superpaycallback::create([
                'response' => json_encode($response),
                'error' => isset($response['error']) ? 1 : 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $datarequest = \Superpay::symDecrypt( $response["encryptedData"] );
            $data = json_decode($datarequest, true);
            $tbl_credit = Credit::where('orderid', $data['data']['order']['id'] )
                                ->where('payment_id', 4)
                                ->where('type', 'withdraw' )
                                ->first();
            if ( !$tbl_credit )
            {
                return [
                    'success'   => 0,
                    'message' => __('credit.no_data_found'),
                ];
            }
            $tbl_member = Member::where('member_id', $tbl_credit->member_id )
                                ->first();
            if (!$tbl_member) {
                return [
                    'success'   => 0,
                    'message' => __('member.no_data_found'),
                ];
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.member_id') . " " . __('member.Inactive') ,
                        'message' => __('member.member_id') . " " . __('member.Inactive') ,
                    ],
                    401
                );
            }
            $amount = (float) $tbl_credit->amount;
            if ( (float) $tbl_member->balance + $amount < 0 ) {
                return [
                    'success'   => 0,
                    'message' => __('messages.insufficient'),
                ];
            }
            $amount = ReverseDecimal( $amount );
            $tbl_credit->update([
                'charge' => $data['data']['transaction']['commissionFee'],
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $amount,
                'status' => $data['data']['status'] === "SUCCESS" ? 1 : -1,
                'updated_on' => now(),
            ]);
            if ( $data['data']['status'] === "SUCCESS" ) {
                $tbl_member->increment('balance', $amount, [
                    'updated_on' => now(),
                ]);
            }
            return [
                'success'   => 1,
                'message' => __('superpay.callback_success'),
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::info('API Superpay CallBack Request', $e->getMessage() );
            return [
                'success'   => 0,
                'message'   => $e->getMessage(),
                'errorCode' => 500,
            ];
        }
    }

    public function callbackdecrypt(Request $request)
    {
        try {
            $response = $request->all();
            $datarequest = \Superpay::symDecrypt( $response["encryptedData"] );
            $data = json_decode($datarequest, true);
            if ( !$data['data'] ) {
                return [
                    'success'   => 0,
                    'message' => __('superpay.createorder_fail'),
                ];
            }
            $tbl_credit = Credit::where('orderid', $data['data']['order']['id'] )->first();
            if ( !$tbl_credit )
            {
                return [
                    'success' => 0,
                    'message' => __('credit.no_data_found'),
                ];
            }
            $tbl_member = Member::where('member_id', $tbl_credit->member_id )
                                ->first();
            if (!$tbl_member) {
                return [
                    'success'   => 0,
                    'message' => __('member.no_data_found'),
                ];
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.member_id') . " " . __('member.Inactive') ,
                        'message' => __('member.member_id') . " " . __('member.Inactive') ,
                    ],
                    401
                );
            }
            $amount = (float) $tbl_credit->amount;
            if ( $tbl_credit->type === "withdraw" )
            {
                if ( (float) $tbl_member->balance + $amount < 0 ) {
                    return [
                        'success'   => 0,
                        'message' => __('messages.insufficient'),
                    ];
                }
                $amount = ReverseDecimal( $amount );
            }
            $tbl_credit->update([
                'charge' => $data['data']['transaction']['commissionFee'],
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $amount,
                'status' => $data['data']['status'] === "SUCCESS" ? 1 : -1,
                'updated_on' => now(),
            ]);
            if ( $data['data']['status'] === "SUCCESS" ) {
                $tbl_member->update([
                    'balance' => (float) $tbl_member->balance + $amount,
                    'updated_on' => now(),
                ]);
                if ( $tbl_credit->type === "deposit" )
                {
                    // VIP Score
                    $tbl_score = AddScore( $tbl_member, 'deposit', $amount );
                }
            }
            return [
                'success' => 1,
                'data' => $data,
                'message' => __('superpay.callback_success'),
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::info('API Superpay CallBack Request', $e->getMessage() );
            return [
                'success'   => 0,
                'message'   => $e->getMessage(),
                'errorCode' => 500,
            ];
        }

    }
}
