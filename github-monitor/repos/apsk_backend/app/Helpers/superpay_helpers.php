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
use App\Models\Superpaycallback;
use Carbon\Carbon;

class Superpay
{

    /**
     * Api Version Number
     */
    private static $VERSION_NO = "v1";

    /**
     * gateway Api Url
     */
    private static string $BASE_URL = "https://api.superpay.info/";

    /**
     * in developer settings : AppId
     */
    private static string $CLIENT_ID;

    /**
     * in developer settings : Key
     */
    private static string $CLIENT_SYMMETRIC_KEY;

    /**
     * in developer settings : Secret
     */
    private static string $CLIENT_SECRET;

    /**
     * rsa algorithm
     */
    private static string $ALGORITHM = 'aes-256-cbc';

    /**
     * aes algorithm
     */
    private static string $HASH_ALGORITHM = 'rsa-sha256';

    /**
     * encrypt auth info
     */
    private static string $EncryptAuthInfo = '';

    /**
     * in developer settings : Server Public Key
     */
    private static string $SERVER_PUB_KEY;

    /**
     * in developer settings : Private Key
     */
    private static string $PRIVATE_KEY;

    /**
     * Flag to check if init already ran
     */
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$CLIENT_ID           = env('SUPERPAYCLIENT_ID');
        self::$CLIENT_SYMMETRIC_KEY = env('SUPERPAYCLIENT_SYMMETRIC_KEY');
        self::$CLIENT_SECRET       = env('SUPERPAYCLIENT_SECRET');
        self::$PRIVATE_KEY         = file_get_contents(storage_path('keys/superpay/private.pem'));
        self::$SERVER_PUB_KEY      = file_get_contents(storage_path('keys/superpay/public.pem'));
        self::$initialized = true;
    }

    private static function neworderid(int $length = 15)
    {
        do {
            $orderid = '';
            $orderid .= random_int(1, 9);
            for ($i = 1; $i < $length; $i++) {
                $orderid .= random_int(0, 9);
            }
        } while (Credit::where('orderid', $orderid)->exists());
        return $orderid;
    }

    /** user deposit
     * @param $orderId [order number - maxlength(40)]
     * @param $amount [order amount - maxlength(20)]
     * @param $currency [Empty default: MYR - maxlength(16)]
     * @param $payMethod [FPX, TNG_MY, ALIPAY_CN, GRABPAY_MY, BOOST_MY - maxlength(16)]
     * @param $customerName [customer name - maxlength(64)]
     * @param $customerEmail [customer email - maxlength(64)]
     * @param $customerPhone [customer phone - maxlength(20)]
     * @return array [code,message,paymentUrl,transactionId]
     */
    public static function deposit(
        $amount,
        $currency,
        $payMethod,
        $customerName,
        $customerEmail,
        $customerPhone
    ) {
        self::init();
        $result = array();
        $orderId = self::neworderid();
        try {
            $token = self::getToken();
            if (self::isnull($token)) return $result;
            $requestUrl = "gateway/"  . self::$VERSION_NO . "/createPayment";
            $cnst = self::generateConstant($requestUrl);
            // If callbackUrl and redirectUrl are empty, take the values ​​of [curl] and [rurl] in the developer center.
            // Remember, the format of json and the order of json attributes must be the same as the SDK specifications.
            // The sorting rules of Json attribute data are arranged from [a-z]
            $bodyJson = "{\"customer\":{\"email\":\"" . $customerEmail . "\",\"name\":\"" . $customerName . "\",\"phone\":\"" . $customerPhone . "\"},\"method\":\"" . $payMethod . "\",\"order\":{\"additionalData\":\"\",\"amount\":\"" . $amount . "\",\"currencyType\":\"" . (self::isnull($currency) ? "MYR" : $currency) . "\",\"id\":\"" . $orderId . "\",\"title\":\"Payment\"}}";
            //$bodyJson = "{\"callbackUrl\":\"https://www.google.com\",\"customer\":{\"email\":\"" . $customerEmail . "\",\"name\":\"" . $customerName . "\",\"phone\":\"" . $customerPhone . "\"},\"method\":\"" . $payMethod . "\",\"order\":{\"additionalData\":\"\",\"amount\":\"" . $amount . "\",\"currencyType\":\"" . (self::isnull($currency) ? "MYR" : $currency) . "\",\"id\":\"" . $orderId . "\",\"title\":\"Payment\"},\"redirectUrl\":\"https://www.google.com\"}";
            $base64ReqBody = self::sortedAfterToBased64($bodyJson);
            $signature = self::createSignature($cnst, $base64ReqBody);
            $encryptData = self::symEncrypt($base64ReqBody);
            $json = ["data" => $encryptData];
            $dict = self::post($requestUrl, $token, $signature, $json, $cnst["nonceStr"], $cnst["timestamp"]);
            if (!self::isnull($dict["code"]) && strval($dict["code"]) == "1" && !self::isnull($dict["encryptedData"])) {
                $decryptedData = self::symDecrypt($dict["encryptedData"]);
                $dict = json_decode($decryptedData,true);
                if (!self::isnull($dict["data"]["paymentUrl"]) && !self::isnull($dict["data"]["transactionId"])) {
                    $result['code'] = "1";
                    $result["message"] = "";
                    $result["paymentUrl"] = $dict["data"]["paymentUrl"];
                    $result["transactionId"] = $dict["data"]["transactionId"];
                    $result["orderid"] = $orderId;
                    return $result;
                }
            }
            $result["code"] = "0";
            $result["message"] = $dict["message"];
            $result["orderid"] = $orderId;
            return $result;
        } catch (\Exception $e) {
            $result["code"] = '0';
            $result["message"] = $e->getMessage();
            $result["orderid"] = $orderId;
            return $result;
        }
    }

    /** user withdraw
     * @param $orderId [order number - maxlength(40)]
     * @param $amount [order amount - maxlength(20)]
     * @param $currency [Empty default: MYR - maxlength(16)]
     * @param $bankCode [MayBank=MBB,Public Bank=PBB,CIMB Bank=CIMB,Hong Leong Bank=HLB,RHB Bank=RHB,AmBank=AMMB,United Overseas Bank=UOB,Bank Rakyat=BRB,OCBC Bank=OCBC,HSBC Bank=HSBC  - maxlength(16)]
     * @param $cardholder [cardholder - maxlength(64)]
     * @param $accountNumber [account number - maxlength(20)]
     * @param $refName [recipient refName - maxlength(64)]
     * @param $recipientEmail [recipient email - maxlength(64)]
     * @param $recipientPhone [recipient phone - maxlength(20)]
     * @return array [code,message,transactionId]
     */
    public static function  withdraw(
        $amount,
        $currency,
        $bankCode,
        $cardholder,
        $accountNumber,
        $refName,
        $recipientEmail,
        $recipientPhone
    ) {
        self::init();
        $result = array();
        $orderId = self::neworderid();
        try {
            $token = self::getToken();
            if (self::isnull($token)) return $result;
            $requestUrl = "gateway/" . self::$VERSION_NO . "/withdrawRequest";
            $cnst = self::generateConstant($requestUrl);
            // payoutspeed contain "fast", "normal", "slow" ,default is : "fast"
            // Remember, the format of json and the order of json attributes must be the same as the SDK specifications.
            // The sorting rules of Json attribute data are arranged from [a-z]
            $bodyJson = "{\"order\":{\"amount\":\"" . $amount . "\",\"currencyType\":\"" . (self::isnull($currency) ? "MYR" : $currency) . "\",\"id\":\"" . $orderId . "\"},\"recipient\":{\"email\":\"" . $recipientEmail . "\",\"methodRef\":\"" . $refName . "\",\"methodType\":\"" . $bankCode . "\",\"methodValue\":\"" . $accountNumber . "\",\"name\":\"" . $cardholder . "\",\"phone\":\"" . $recipientPhone . "\"}}";
            //$bodyJson = "{\"callbackUrl\":\"https://www.google.com\",\"order\":{\"amount\":\"" . $amount . "\",\"currencyType\":\"" . (self::isnull($currency) ? "MYR" : $currency) . "\",\"id\":\"" . $orderId . "\"},\"payoutspeed\":\"normal\",\"recipient\":{\"email\":\"" . $recipientEmail . "\",\"methodRef\":\"" . $refName . "\",\"methodType\":\"" . $bankCode . "\",\"methodValue\":\"" . $accountNumber . "\",\"name\":\"" . $cardholder . "\",\"phone\":\"" . $recipientPhone . "\"}}";
            $base64ReqBody = self::sortedAfterToBased64($bodyJson);
            $signature = self::createSignature($cnst, $base64ReqBody);
            $encryptData = self::symEncrypt($base64ReqBody);
            $json = ["data" => $encryptData];
            $dict = self::post($requestUrl, $token, $signature, $json, $cnst["nonceStr"], $cnst["timestamp"]);
            if (!self::isnull($dict["code"]) && strval($dict["code"]) == "1" && !self::isnull($dict["encryptedData"])) {
                $decryptedData = self::symDecrypt($dict["encryptedData"]);
                $dict = json_decode($decryptedData,true);
                if (!self::isnull($dict["data"]["transactionId"])) {
                    $result["code"] = "1";
                    $result["message"] = "";
                    $result["transactionId"] = $dict["data"]["transactionId"];
                    $result["orderid"] = $orderId;
                    return $result;
                }
            }
            $result["code"] = "0";
            $result["message"] = $dict["message"];
            $result["orderid"] = $orderId;
            return $result;
        } catch (\Exception $e) {
            $result["code"] = "0";
            $result["message"] = $e->getMessage();
            $result["orderid"] = $orderId;
            return $result;
        }
    }

    /** User deposit and withdrawal details
     * @param $orderId [transaction id]
     * @param $type [1 deposit,2 withdrawal]
     * @return array [code,message,transactionId,amount,fee]
     */
    public static function detail($tbl_credit, $type)
    {
        self::init();
        $result = array();
        try {
            $token = self::getToken();
            if (self::isnull($token)) return $result;
            $requestUrl = "gateway/" . self::$VERSION_NO . "/getTransactionStatusById";
            $cnst = self::generateConstant($requestUrl);
            // Remember, the format of json and the order of json attributes must be the same as the SDK specifications.
            // The sorting rules of Json attribute data are arranged from [a-z]
            // type : 1 deposit,2 withdrawal
            $bodyJson = "{\"transactionId\":\"" . $tbl_credit->transactionId . "\",\"type\":" . $type . "}";
            $base64ReqBody = self::sortedAfterToBased64($bodyJson);
            $signature = self::createSignature($cnst, $base64ReqBody);
            $encryptData = self::symEncrypt($base64ReqBody);
            $json = ["data" => $encryptData];
            $dict = self::post($requestUrl, $token, $signature, $json, $cnst["nonceStr"], $cnst["timestamp"]);
            // if (!self::isnull($dict["code"]) && $dict["code"] == "1" && !self::isnull($dict["encryptedData"])) {
            //     $decryptedData = self::symDecrypt($dict["encryptedData"]);
            //     return json_decode($decryptedData,true);
            // }
            // $result["code"] = "0";
            // $result["message"] = isset($dict["data"]["message"]) ? $dict["data"]["message"] : "Unknown error";
            // $result["orderid"] = $orderId;
            // $result["status"] = true;
            // return $result;
            if ( self::isnull($dict["encryptedData"]) ) {
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
            $decryptedData = self::symDecrypt($dict["encryptedData"]);
            $data = json_decode($decryptedData,true);
            $response['response'] = $data;
            if ( self::isnull($data["data"]) ) {
                if ( (int) $tbl_credit->status !== -1 ) {
                    $tbl_credit->update([
                        'status' => -1,
                        'reason' => 'messages.unexpected_error',
                        'updated_on' => now(),
                    ]);
                }
                $response['status'] = false;
                $response['message'] = __('messages.unexpected_error');
                $response['error'] = isset($dict["data"]["message"]) ? $dict["data"]["message"] : __('messages.unexpected_error');
                $response['code'] = 500;
                $response['credit'] = $tbl_credit->fresh();
                return $response;
            }
            if ( $data['data']['status'] === "PENDING" ) {
                $isExpired = Carbon::parse($tbl_credit->created_on)
                                ->setTimezone('Asia/Kuala_Lumpur')
                                ->addMinutes(15)
                                ->isPast();
                if ($isExpired) {
                    $tbl_credit->update([
                        'status' => -1,
                        'reason' => 'credit.expire',
                        'updated_on' => now(),
                    ]);
                    $response['status'] = true;
                    $response['message'] = __('credit.expire');
                    $response['error'] = "";
                    $response['code'] = 200;
                    $response['credit'] = $tbl_credit->fresh();
                    return $response;
                }
                $response['status'] = true;
                $response['message'] = __('credit.pending');
                $response['error'] = __('credit.pending');
                $response['code'] = 500;
                $response['credit'] = $tbl_credit->fresh();
                return $response;
            }
            $response['status'] = true;
            $response['message'] = __('messages.list_success');
            $response['error'] = "";
            $response['code'] = 200;
            $response['credit'] = $tbl_credit->fresh();
            return $response;
        } catch (\Exception $e) {
            // $result["code"] = "0";
            // $result["message"] = $e->getMessage();
            // $result["orderid"] = $orderId;
            // $result["status"] = false; 
            // return $result;
            $response['status'] = false;
            $response['message'] = __('messages.unexpected_error');
            $response['error'] = $e->getMessage();
            $response['code'] = 500;
            $response['credit'] = $tbl_credit;
            return $response;
        }
    }

    /** get server token
     * @return string
     */
    private static function getToken()
    {
        $token = "";
        if (self::isnull(self::$EncryptAuthInfo)) {
            $authString = self::stringToBase64(self::$CLIENT_ID . ":" . self::$CLIENT_SECRET);
            self::$EncryptAuthInfo = self::publicEncrypt($authString);
        }
        $json=["data"=>self::$EncryptAuthInfo];
        $keys = array("code", "encryptedToken");
        $dict = self::post("gateway/"  . self::$VERSION_NO . "/createToken", "", "", $json, "", "", $keys);
        if (!self::isnull($dict["code"]) && !self::isnull($dict["encryptedToken"]) && strval($dict["code"]) == "1") {
            $token = self::symDecrypt($dict["encryptedToken"]);
        }
        return $token;
    }

    /** to test getToken
     * @return string
     */
    public static function testGetToken()
    {
        return self::getToken();
    }

    /** A simple http request method
     * @param $url
     * @param $token
     * @param $signature
     * @param $json
     * @param $nonceStr
     * @param $timestamp
     * @return mixed
     */
    private static function post($url, $token, $signature, $json, $nonceStr, $timestamp)
    {
        if (self::endWith(self::$BASE_URL, "/")) {
            $url = self::$BASE_URL . $url;
        } else {
            $url = self::$BASE_URL . "/" . $url;
        }
        $options = array(
            "http" => array(
                "method" => "POST",
                "header" => array("Content-type:application/json") ,
                "content" =>json_encode($json)
            )
        );
        if (!self::isnull($token) && !self::isnull($signature) && !self::isnull($nonceStr) && !self::isnull($timestamp)) {
            $options["http"]["header"] = array("Content-type:application/json",
                "Authorization:". $token,
                "X-Nonce-Str:". $nonceStr,
                "X-Signature:".$signature,
                "X-Timestamp:".$timestamp);
        }
        $context = stream_context_create($options);
        try {
            $result = @file_get_contents($url, false, $context);
            if ($result === false) {
                return [
                    "code"    => "0",
                    "message" => "HTTP request failed: $url"
                ];
            }
            return json_decode($result, true);
        } catch (\Exception $e) {
            return [
                "code"    => "0",
                "message" => $e->getMessage()
            ];
        }
    }

    /** create a signature
     * @param $cnst
     * @param $base64ReqBody
     * @return string
     */
    private static function createSignature($cnst, $base64ReqBody)
    {
        $dataString = "data=" . $base64ReqBody . "&method=" . $cnst["method"] . "&nonceStr=" . $cnst["nonceStr"] . "&requestUrl=" . $cnst["requestUrl"] . "&signType=" . $cnst["signType"] . "&timestamp=" . $cnst["timestamp"];
        $signature = self::sign($dataString);
        return $cnst["signType"] . " " . $signature;
    }

    /** generate constant
     * @param $requestUrl
     * @return array
     */
    private static function generateConstant($requestUrl)
    {
        return [
            "method" => "post",
            "nonceStr" => self::randomNonceStr(),
            "requestUrl" => $requestUrl,
            "signType" => "sha256",
            "timestamp" => time(),
        ];
    }

    /** random nonceStr
     * @return string
     */
    private static function randomNonceStr()
    {
        $sb = "";
        $chars = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
        for ($i = 1; $i <= 8; $i++) {
            $index = rand(0, count($chars)-1);
            $sb = $sb . $chars[$index];
        }
        $bytes = self::stringToBytes($sb);
        return self::bytesToHex($bytes);
    }

    /** Encrypt data based on the server's public key
     * @param $data data to be encrypted
     * @return string encrypted data
     */
    private static function publicEncrypt($data)
    {
        if(openssl_public_encrypt($data, $encryptData, self::$SERVER_PUB_KEY, OPENSSL_PKCS1_PADDING))
        {
            $encryptBytes = self::stringToBytes($encryptData);
            return self::bytesToHex($encryptBytes);
        }else{
            return '';
        }
    }

    /** Decrypt data according to the interface private key
     * @param $encryptData
     * @return mixed
     */
    private static function privateDecrypt($encryptData)
    {
        $binary = hex2bin($encryptData);
        if (!openssl_private_decrypt($binary, $decrypted, self::$PRIVATE_KEY, OPENSSL_PKCS1_PADDING)) {
            return null;
        }
        $json = json_decode($decrypted);
        return $json;
    }

    /** Payment interface data encryption method
     * @param $message
     * @return string
     */
    private static function symEncrypt($message)
    {
        $iv = self::generateIv(self::$CLIENT_SYMMETRIC_KEY);
        $cipherData = openssl_encrypt($message, self::$ALGORITHM, self::$CLIENT_SYMMETRIC_KEY, OPENSSL_RAW_DATA, $iv);
        return self::stringToHex($cipherData);
    }

    /** Payment interface data decryption method
     * @param $encryptedMessage encryptedMessage The data that needs to be encryptedMessage, the result encrypted by symEncrypt can be decrypted
     * @return string Return the data content of utf-8 after decryption
     */
    public static function symDecrypt($encryptedMessage)
    {
        self::init();
        $encryptedText = self::hexToString($encryptedMessage);
        $iv = self::generateIv(self::$CLIENT_SYMMETRIC_KEY);
        $decrypted = openssl_decrypt($encryptedText, self::$ALGORITHM, self::$CLIENT_SYMMETRIC_KEY, OPENSSL_RAW_DATA, $iv);
        if($decrypted){
            return $decrypted;
        }else{
            return "";
        }
    }

    /** private key signature
     * @param $data
     * @return string
     */
    private static function sign($data)
    {
        openssl_sign($data, $signature, self::$PRIVATE_KEY, self::$HASH_ALGORITHM);
        return self::stringToBase64($signature);;
    }

    /** Public key verification signature information
     * @param $data
     * @param $signature
     * @return false|int
     */
    private static function verify($data, $signature)
    {
        return openssl_verify($data, $signature, self::$SERVER_PUB_KEY, self::$HASH_ALGORITHM);
    }

    /** Return base64 after sorting argument list
     * @param $json
     * @return string
     */
    private static function sortedAfterToBased64($json)
    {
        return self::stringToBase64($json);
    }

    /** Generate an IV based on the data encryption key
     * @param $symmetricKey
     * @return string
     */
    private static function generateIv($symmetricKey)
    {
        //$data = md5($symmetricKey,true);
        //$iv = self::stringToBytes($data);
        //return $iv;
        return md5($symmetricKey,true);
    }

    /** string to batys
     * @param $data
     * @return array|false
     */
    private static function stringToBytes($data)
    {
        return unpack('C*', $data);
    }

    /** string to base64
     * @param $data
     * @return string
     */
    private static function stringToBase64($data)
    {
        return base64_encode($data);
    }

    /** base64 to string
     * @param $base64
     * @return false|string
     */
    private static function base64ToString($base64)
    {
        return base64_decode($base64);
    }

    /** String to bytes
     * @param $bytes
     * @return string
     */
    private static function bytesToString($bytes)
    {
        $chars = array_map('chr', $bytes);
        return join($chars);
    }

    /** Bytes to hex
     * @param $bytes
     * @return string
     */
    private static function bytesToHex($bytes)
    {
        $chars = array_map('chr', $bytes);
        $bin = join($chars);
        return bin2hex($bin);
    }

    /** Hex to bytes
     * @param $hex
     * @return array|false
     */
    private static function hexToBytes($hex)
    {
        $string = hex2bin($hex);
        return unpack('C*', $string);
    }

    /** Bytes to base64
     * @param $bytes
     * @return string
     */
    private static function bytesToBase64($bytes)
    {
        $string = self::bytesToString($bytes);
        return self::stringToBase64($string);
    }

    /** Base64 to bytes
     * @param $base64
     * @return array
     */
    private static function base64ToBytes($base64)
    {
        $arr = base64_decode($base64);
        $bytes = array();
        foreach (str_split($arr) as $chr) {
            $bytes[] = sprintf('%08b', ord($chr));
        }
        return $bytes;
    }

    /**
     * String to Hex
     *
     * @param string $string
     * @return string
     */
    private static function stringToHex($string)
    {
        return bin2hex($string);
    }

    /**
     * Hex String to String
     *
     * @param string $hexString
     * @return string
     */
    private static function hexToString($hexString)
    {
        return hex2bin($hexString);
    }

    /** isnull
     * @param $val
     * @return bool
     */
    private static function isnull($val): bool
    {
        return $val === null || $val === '';
    }

    /** end with
     * @param $str
     * @param $pattern
     * @return bool
     */
    private  static  function endWith($str,$pattern) {
        $length = strlen($pattern);
        if ($length == 0) {
            return true;
        }
        return (substr($str, -$length) === $pattern);
    }
}