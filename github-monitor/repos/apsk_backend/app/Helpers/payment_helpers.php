<?php

use App\Models\Paymentgateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Paymenthelper
{
    public static function deposit($request, $tbl_credit, $tbl_member)
    {
        switch ( (int) $request->input('payment_id') ) {
            case 1: //Cash
                $tbl_credit->update([
                    'status' => 1,
                    'updated_on' => now(),
                ]);
                $tbl_member->update([
                    'balance' => (float) $tbl_member->balance + $request->input('amount'),
                    'updated_on' => now(),
                ]);
                return [
                    'status'   => true,
                    'message' => __('messages.deposit_success'),
                    'error' => '',
                    'url'    => null,
                    'code' => 200,
                ];
                break;
            case 3: //FPay
                $response = \Fpay::createorder($request, $tbl_member);
                if (!is_array($response) || !array_key_exists('status', $response)) {
                    $tbl_credit->update([
                        'orderid' => null,
                        'transactionId' => null,
                        'status' => -1,
                        'reason' => 'fpay.createorder_fail',
                        'updated_on' => now(),
                    ]);
                    return [
                        'status'   => false,
                        'response' => $response,
                        'message'  => __('fpay.createorder_fail'),
                        'error'    => __('fpay.createorder_fail'),
                        'url'    => null,
                        'code' => 500,
                    ];
                }
                if (!$response['status']) {
                    $tbl_credit->update([
                        'orderid' => $response['orderid'],
                        'transactionId' => $response['transactionId'],
                        'status' => -1,
                        'reason' => $response['message'],
                        'updated_on' => now(),
                    ]);
                    return [
                        'status'   => false,
                        'response' => $response,
                        'message'  => $response['message'],
                        'error'    => __('messages.unexpected_error'),
                        'url'    => null,
                        'code' => 400,
                    ];
                }
                $tbl_credit->update([
                    'orderid' => $response['orderid'],
                    'transactionId' => $response['transactionId'],
                    'updated_on' => now(),
                ]);
                return [
                    'status'  => true,
                    'message' => __('messages.deposit_success'),
                    'error'   => '',
                    'url'     => $response['p_url'],
                    'code' => 200,
                ];
                break;
            case 4: //SPay
                $response = \Superpay::deposit( 
                    $request->input('amount'), 
                    'MYR',
                    'FPX',
                    $tbl_member->member_name,
                    'abc@abcdef.com',
                    $tbl_member->phone
                );
                if (!is_array($response) || !array_key_exists('code', $response)) {
                    $tbl_credit->update([
                        'orderid' => null,
                        'transactionId' => null,
                        'status' => -1,
                        'reason' => 'messages.unexpected_error',
                        'updated_on' => now(),
                    ]);
                    return [
                        'status'   => false,
                        'response' => $response,
                        'message'  => __('messages.unexpected_error'),
                        'error'    => __('messages.unexpected_error'),
                        'url'    => null,
                        'code' => 500,
                    ];
                }
                if (!$response['code']) {
                    $tbl_credit->update([
                        'orderid' => $response['orderid'],
                        'transactionId' => $response['transactionId'],
                        'status' => -1,
                        'reason' => $response['message'],
                        'updated_on' => now(),
                    ]);
                    return [
                        'status'   => false,
                        'response' => $response,
                        'message'  => __('messages.unexpected_error'),
                        'error'    => __('messages.unexpected_error'),
                        'url'    => null,
                        'code' => 500,
                    ];
                }
                $tbl_credit->update([
                    'orderid' => $response['orderid'],
                    'transactionId' => $response['transactionId'],
                    'updated_on' => now(),
                ]);
                return [
                    'status'  => true,
                    'message' => __('messages.deposit_success'),
                    'error'   => '',
                    'url'     => $response['paymentUrl'],
                    'code' => 200,
                ];
                break;
            case 5: //FPay Duitnow
                $response = \Fpay::createorderduitnow($request, $tbl_member);
                if (!is_array($response) || !array_key_exists('status', $response)) {
                    $tbl_credit->update([
                        'orderid' => null,
                        'transactionId' => null,
                        'status' => -1,
                        'reason' => 'fpay.createorder_fail',
                        'updated_on' => now(),
                    ]);
                    return [
                        'status'   => false,
                        'response' => $response,
                        'message'  => __('fpay.createorder_fail'),
                        'error'    => __('fpay.createorder_fail'),
                        'url'    => null,
                        'code' => 500,
                    ];
                }
                if (!$response['status']) {
                    $tbl_credit->update([
                        'orderid' => $response['orderid'],
                        'transactionId' => $response['transactionId'],
                        'status' => -1,
                        'reason' => $response['message'],
                        'updated_on' => now(),
                    ]);
                    return [
                        'status'   => false,
                        'response' => $response,
                        'message'  => $response['message'],
                        'error'    => __('messages.unexpected_error'),
                        'url'    => null,
                        'code' => 400,
                    ];
                }
                $tbl_credit->update([
                    'orderid' => $response['orderid'],
                    'transactionId' => $response['transactionId'],
                    'updated_on' => now(),
                ]);
                return [
                    'status'  => true,
                    'message' => __('messages.deposit_success'),
                    'error'   => '',
                    'url'     => $response['p_url'],
                    'code' => 200,
                ];
                break;
            default:
                return [
                    'status'   => false,
                    'response' => $response,
                    'message' => __('paymentgateway.no_data_found'),
                    'error' => __('paymentgateway.no_data_found'),
                    'url'    => null,
                    'code' => 400,
                ];
                break;
        }
    }

    public static function  withdraw()
    {

    }

}