<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Member;
use App\Models\Credit;
use App\Models\Bankaccount;
use App\Models\Bank;
use App\Models\Fpaycallback;
use Carbon\Carbon;

class Fpay
{
    // private static string $username = "APSKtest";
    // private static string $apikey = "6Blpyzvneg0Uo5ASiL3y";
    // private static string $secretkey = "4tPJ27TrkYoyaQ4";
    // private static string $url = "https://sandboxapi.fpay.asia/";
    private static ?string $username = null;
    private static ?string $apikey = null;
    private static ?string $secretkey = null;
    private static ?string $url = null;

    // Initialize static properties
    private static function init(): void
    {
        self::$username  = config('services.fpay.username');
        self::$apikey    = config('services.fpay.apikey');
        self::$secretkey = config('services.fpay.secretkey');
        self::$url       = config('services.fpay.apiurl');
    }

    private static function initduitnow(): void
    {
        self::$username  = config('services.fpayduitnow.username');
        self::$apikey    = config('services.fpayduitnow.apikey');
        self::$secretkey = config('services.fpayduitnow.secretkey');
        self::$url       = config('services.fpayduitnow.apiurl');
    }

    private static function curl($url, $params, $token = null)
    {
        try {
            $headers = [
                "Content-Type: multipart/form-data"
            ];
            if ($token) {
                $headers[] = "Authorization: Bearer {$token}";
            }
            $curl = curl_init( $url );
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 400);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($curl);
            if ($response === false) {
                return [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => curl_error($curl),
                ];
            }
            curl_close($curl);
            $response = safe_json_decode($response);
            return $response;
        } catch (\Exception $e) {
            return [
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function banklist(Request $request)
    {
        if (!isset(self::$username)) self::init();
        try {
            $send['username'] = self::$username;
            $send['currency'] = $request->filled('currency') ? $request->input('currency') : "MYR";
            $response = self::curl( self::$url."wallet/withdraw_bank_list", $send);
            return $response;
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function currency(Request $request)
    {
        if (!isset(self::$username)) self::init();
        try {
            $send['username'] = self::$username;
            $response = self::curl( self::$url."merchant/currency", $send);
            return $response;
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function authkey()
    {
        if (!isset(self::$username)) self::init();
        try {
            $send['username'] = self::$username;
            $send['api_key'] = self::$apikey;
            $response = self::curl( self::$url."merchant/auth", $send);
            return $response;
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function createorder(Request $request, $tbl_member)
    {
        if (!isset(self::$username)) self::init();
        $tbl_bankaccount = Bankaccount::where('member_id', $request->input('member_id'))
                                      ->where('status', 1)
                                      ->where('delete', 0)
                                      ->first();
        $auth = self::authkey();
        if (!isset($auth['auth']) || empty($auth['auth'])) {
            return [
                'status' => false,
                'message' => __('fpay.auth_fail'),
                'error' => __('fpay.auth_fail'),
            ];
        }
        $send['username'] = $request->input('member_id');
        $send['auth'] = $auth['auth'];
        $send['api_key'] = self::$apikey;
        $send['amount'] = $request->input('amount');
        $send['currency'] = "MYR";
        $send['orderid'] = $auth['order_id'];
        $send['redirect_url'] = config('app.urlapi') . 'payment/redirect';
        $send['customer_bank_holder_name'] = $tbl_bankaccount->bank_full_name ?? null;
        $response = self::curl( self::$url."merchant/generate_orders", $send);
        $response['orderid'] = $auth['order_id'];
        $response['transactionId'] = $auth['order_id'];
        return $response;
    }

    public static function createorderduitnow(Request $request, $tbl_member)
    {
        if (!isset(self::$username)) self::initduitnow();
        $tbl_bankaccount = Bankaccount::where('member_id', $request->input('member_id'))
            ->where('status', 1)
            ->where('delete', 0)
            ->first();
        $auth = self::authkey();
        if (!isset($auth['auth']) || empty($auth['auth'])) {
            return [
                'status' => false,
                'message' => __('fpay.auth_fail'),
                'error' => __('fpay.auth_fail'),
            ];
        }
        $send['username'] = $request->input('member_id');
        $send['auth'] = $auth['auth'];
        $send['api_key'] = self::$apikey;
        $send['amount'] = $request->input('amount');
        $send['currency'] = "MYR";
        $send['orderid'] = $auth['order_id'];
        $send['redirect_url'] = config('app.urlapi') . 'payment/redirect';
        $send['customer_bank_holder_name'] = $tbl_bankaccount->bank_full_name ?? null;
        $response = self::curl( self::$url."merchant/generate_orders", $send);
        $response['orderid'] = $auth['order_id'];
        $response['transactionId'] = $auth['order_id'];
        return $response;
    }

    public static function withdraworder(Request $request, $tbl_member)
    {
        if (!isset(self::$username)) self::init();
        $tbl_bankaccount = Bankaccount::where('bankaccount_id', $request->input('bankaccount_id'))
                                      ->where('status', 1)
                                      ->where('delete', 0)
                                      ->with('Bank')
                                      ->first();
        if (!$tbl_bankaccount) {
            return [
                'status' => false,
                'message' => __('bank.no_account_found'),
                'error' => __('bank.no_account_found'),
            ];
        }
        if (!$tbl_bankaccount->bank?->fpaybank_id) {
            return [
                'status' => false,
                'message' => __('fpay.bank_no_found'),
                'error' => __('fpay.bank_no_found'),
            ];
        }
        if ( (float) $tbl_member->balance - (float) $request->input('amount') < 0 ) {
            return [
                'status' => false,
                'message' => __('messages.insufficient'),
                'error' => __('messages.insufficient'),
            ];
        }
        $auth = self::authkey();
        if (!isset($auth['auth']) || empty($auth['auth'])) {
            return [
                'status' => false,
                'message' => __('fpay.auth_fail'),
                'error' => __('fpay.auth_fail'),
            ];
        }
        $send['auth'] = $auth['auth'];
        $send['amount'] = $request->input('amount');
        $send['currency'] = "MYR";
        $send['orderid'] = $auth['order_id'];
        $send['bank_id'] = $tbl_bankaccount->bank?->fpaybank_id;
        $send['holder_name'] = $tbl_bankaccount->bank_full_name;
        $send['account_no'] = $tbl_bankaccount->bank_account;
        $response = self::curl( self::$url."merchant/withdraw_orders", $send);
        $response['orderid'] = $auth['order_id'];
        $response['transactionId'] = $auth['order_id'];
        return $response;
    }

    public static function detail($tbl_credit)
    {
        if (!isset(self::$username)) self::init();
        $send['username'] = self::$username;
        $send['id'] = $tbl_credit->orderid;
        $response = self::curl( self::$url."merchant/check_status", $send);
        if (!$response['status']) {
            $tbl_credit->update([
                'status' => -1,
                'reason' => 'messages.unexpected_error',
                'updated_on' => now(),
            ]);
            $response['status'] = false;
            $response['message'] = __('messages.unexpected_error');
            $response['error'] = __('messages.unexpected_error');
            $response['code'] = 500;
            $response['credit'] = $tbl_credit->fresh();
            return $response;
        }
        switch ( $response['order_status'] ) {
            case 'completed':
                break;
            case 'pending':
                $isExpired = Carbon::parse($tbl_credit->created_on)
                                ->setTimezone('Asia/Kuala_Lumpur')
                                ->addMinutes(15)
                                ->isPast();
                if ($isExpired) {
                    if ( (int) $tbl_credit->status === 0 ) {
                        $tbl_credit->update([
                            'status' => -1,
                            'reason' => 'credit.expire',
                            'updated_on' => $response['order_datetime'],
                        ]);
                    }
                    $response['status'] = true;
                    $response['message'] = __('credit.expire');
                    $response['error'] = "";
                    $response['code'] = 200;
                    $response['credit'] = $tbl_credit->fresh();
                    return $response;
                }
                break;
            case 'fail':
                break;
            default:
                $response['status'] = false;
                $response['message'] = __('messages.unexpected_error');
                $response['error'] = __('messages.unexpected_error');
                $response['code'] = 500;
                $response['credit'] = $tbl_credit;
                return $response;
                break;
        }
        $response['status'] = true;
        $response['message'] = __('messages.list_success');
        $response['error'] = "";
        $response['code'] = 200;
        $response['credit'] = $tbl_credit->fresh();
        return $response;
    }

    public static function detailduitnow($tbl_credit)
    {
        if (!isset(self::$username)) self::initduitnow();
        $send['username'] = self::$username;
        $send['id'] = $tbl_credit->orderid;
        $response = self::curl( self::$url."merchant/check_status", $send);
        if (!$response['status']) {
            $tbl_credit->update([
                'status' => -1,
                'reason' => 'messages.unexpected_error',
                'updated_on' => now(),
            ]);
            $response['status'] = false;
            $response['message'] = __('messages.unexpected_error');
            $response['error'] = __('messages.unexpected_error');
            $response['code'] = 500;
            $response['credit'] = $tbl_credit->fresh();
            return $response;
        }
        switch ( $response['order_status'] ) {
            case 'completed':
                break;
            case 'pending':
                $isExpired = Carbon::parse($tbl_credit->created_on)
                    ->setTimezone('Asia/Kuala_Lumpur')
                    ->addMinutes(2)
                    ->isPast();
                if ($isExpired) {
                    if ( (int) $tbl_credit->status === 0 ) {
                        $tbl_credit->update([
                            'status' => -1,
                            'reason' => 'credit.expire',
                            'updated_on' => $response['order_datetime'],
                        ]);
                    }
                    $response['status'] = true;
                    $response['message'] = __('credit.expire');
                    $response['error'] = "";
                    $response['code'] = 200;
                    $response['credit'] = $tbl_credit->fresh();
                    return $response;
                }
                break;
            case 'fail':
                break;
            default:
                $response['status'] = false;
                $response['message'] = __('messages.unexpected_error');
                $response['error'] = __('messages.unexpected_error');
                $response['code'] = 500;
                $response['credit'] = $tbl_credit;
                return $response;
                break;
        }
        $response['status'] = true;
        $response['message'] = __('messages.list_success');
        $response['error'] = "";
        $response['code'] = 200;
        $response['credit'] = $tbl_credit->fresh();
        return $response;
    }

    public static function withdrawstatus(Request $request, $tbl_credit)
    {
        if (!isset(self::$username)) self::init();
        $send['username'] = self::$username;
        $send['id'] = $tbl_credit->orderid;
        $response = self::curl( self::$url."merchant/check_withdraw_status", $send);
        return $response;

    }

    public static function withdrawstatusduitnow(Request $request, $tbl_credit)
    {
        if (!isset(self::$username)) self::initduitnow();
        $send['username'] = self::$username;
        $send['id'] = $tbl_credit->orderid;
        $response = self::curl( self::$url."merchant/check_withdraw_status", $send);
        return $response;

    }

}
