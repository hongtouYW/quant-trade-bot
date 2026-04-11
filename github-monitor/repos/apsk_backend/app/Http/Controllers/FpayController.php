<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Member;
use App\Models\Credit;
use App\Models\Bankaccount;
use App\Models\Bank;
use App\Models\Recruit;
use App\Models\Commissionrank;
use App\Models\Fpaycallback;
use Carbon\Carbon;

class FpayController extends Controller
{
    public function callback(Request $request)
    {
        try {
            $jsonpayload = preg_replace('/^json=/', '', $request->getContent() );
            // $jsonpayload = json_encode($request->all());
            $tbl_fpaycallback = Fpaycallback::create([
                // 'response' => json_encode($request->all()),
                'response' => $jsonpayload,
                'error' => $request->has('error') ? 1 : 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $datarequest = json_decode( $jsonpayload );
            if ( !$datarequest->order_status ) {
                return [
                    'success'   => 0,
                    'message' => __('fpay.createorder_fail'),
                ];
            }
            if (!in_array($datarequest->type, ['deposit', 'withdrawal'])) {
                return [
                    'success'   => 0,
                    'message' => __('credit.no_data_found'),
                ];
            }
            $type = $datarequest->type === 'deposit' ? 'deposit' : 'withdraw';
            $tbl_credit = Credit::where('orderid', $datarequest->order_id )
                ->whereIn('payment_id', [3, 5])
                ->where('type', $type )
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
            if ( $tbl_credit->type === "withdraw" ) {
                if ( (float) $tbl_member->balance + $amount < 0 ) {
                    return [
                        'success'   => 0,
                        'message' => __('messages.insufficient'),
                    ];
                }
                $amount = ReverseDecimal( $amount );
            }
            $tbl_credit->update([
                'charge' => $datarequest->charge,
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $amount,
                'status' => $datarequest->order_status === "completed" ? 1 : -1,
                'updated_on' => now(),
            ]);
            // if ( $datarequest->order_status === "completed" ) {
            $tbl_credit = $tbl_credit->fresh();
            if ( (int) $tbl_credit->status === 1 ) {
                $tbl_member->increment('balance', $amount, [
                    'updated_on' => now(),
                ]);
                if ( $tbl_credit->type === "deposit" )
                {
                    // VIP Score
                    $tbl_score = AddScore( $tbl_member, 'deposit', $amount );
                    // Commission
                    $salestarget = AddCommission( $tbl_member, $amount );
                }
            }
            return [
                'success'   => 1,
                'message' => __('fpay.callback_success'),
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::info('API FPay CallBack Request', $e->getMessage() );
            return [
                'success'   => 0,
                'message' => $e->getMessage(),
            ];
        }
    }
}
