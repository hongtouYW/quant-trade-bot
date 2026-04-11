<?php

use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

if (!function_exists('ReverseDecimal')) {
    function ReverseDecimal($number)
    {
        return $number * -1;
    }
}

if (!function_exists('setAppLocale')) {
    /**
     * Sets the application locale based on the Accept-Language header.
     *
     * @param Request $request
     * @param string $defaultLocale
     * @param array $supportedLocales
     * @param string $headerName
     * @return void
     */
    function setAppLocale(
        Request $request,
        string $defaultLocale = 'en',
        string $headerName = 'Accept-Language'
    ): void {
        $locale = $request->header($headerName);
        $supportedLocales = config('languages.supported');
        if ($locale && in_array($locale, $supportedLocales)) {
            App::setLocale($locale);
        } else {
            App::setLocale($defaultLocale);
        }
    }
}

if (!function_exists('generateOTP')) {
    function generateOTP(
        string $login,
        string $password,
        string $type = 'user',
        string $verifyby = 'phone',
        string $module = 'register',
        ?int $agent_id = null,
        ?string $email = null,
        int $otpLength = 6,
        int $expiryMinutes = 3 // Default OTP expiry is 3 minutes
    ) {
        $code = str_pad(random_int(0, pow(10, $otpLength) - 1), $otpLength, '0', STR_PAD_LEFT);
        $currentTime = Carbon::now();
        $matchingAttributes = [
            'login' => $login,
            'type' => $type,
            'verifyby' => $verifyby,
            'module' => $module,
            'agent_id' => $agent_id,
        ];
        $values = [
            'password' => encryptPassword( $password ),
            'code' => $code,
            'verified' => 0,
            'attempts' => 0,
            'expires_on' => Carbon::now()->addMinutes($expiryMinutes),
            'last_attempt_on' => null,
            'blocked_until' => null,
            'created_on' => $currentTime,
            'updated_on' => $currentTime,
        ];
        $status_email = null;
        try {
            switch ( $verifyby ) {
                case 'phone':
                    // development
                    if (app()->environment('staging')) {
                        break;
                    }
                    // random phone
                    $israndomphonecode = \Prefix::israndomphonecode($login);
                    if ( $israndomphonecode ) {
                        break;
                    }
                    if ( in_array($module, ['changepassword']) ) {
                        break;
                    }
                    $telesms = \Telesms::send($login, __('messages.otp_sms_message', ['otpcode'=>$code] ) );
                    if ( !$telesms['success'] ) {
                        return [
                            'status' => false,
                            'message' => $telesms['message'],
                            'error' => __('messages.unexpected_error'),
                            'code' => '',
                            'status_email' => $status_email,
                        ];
                    }
                    break;
                case 'email':
                    if ( is_null($email) ) {
                        return [
                            'status' => false,
                            'message' => __('member.email_no_found'),
                            'error' => __('member.email_no_found'),
                            'code' => '',
                            'status_email' => $status_email,
                        ];
                    }
                    $status_email = OTPEmail( $email, $code );
                    if ( !$status_email['status'] ) {
                        return [
                            'status' => false,
                            'message' => __('member.email_invalid'),
                            'error' => __('member.email_invalid'),
                            'code' => '',
                            'status_email' => $status_email,
                        ];
                    }
                    break;
                default:
                    return [
                        'status' => false,
                        'message' => __('messages.unexpected_error'),
                        'error' => __('messages.unexpected_error'),
                        'code' => '',
                        'status_email' => $status_email,
                    ];
                    break;
            }
            $existing = DB::table('tbl_otp')->where($matchingAttributes)->first();
            if ($existing) {
                DB::table('tbl_otp')->where($matchingAttributes)->update($values);
            } else {
                $values['created_on'] = $currentTime;
                DB::table('tbl_otp')->insert(array_merge($matchingAttributes, $values));
            }
        } catch (\Illuminate\Database\QueryException $e) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
                'code' => '',
                'status_email' => $status_email,
            ];
        }
        return [
            'status' => true,
            'message' => __('messages.otpsuccess'),
            'error' => '',
            'code' => $code,
            'status_email' => $status_email,
        ];
    }
}

if (!function_exists('verifyOTP')) {
    /**
     * Verifies an OTP code and applies cooldown logic for failed attempts.
     *
     * @param string $phone The user's phone number.
     * @param string $login The user's login identifier.
     * @param string $code The OTP code entered by the user.
     * @return array A result array with 'status' (bool) and 'message' (string),
     * and optionally 'remaining_cooldown_seconds' (int).
     */
    function verifyOTP(
        string $login,
        string $code,
        string $type = 'user',
        string $verifyby = 'phone',
        string $module = 'register',
        ?int $agent_id = null,
    ): array {
        $otpRecord = DB::table('tbl_otp')
            ->where('login', $login)
            ->where('type', $type)
            ->where('verifyby', $verifyby)
            ->where('module', $module);
        $otpRecord = $otpRecord->when(
            is_null($agent_id),
            fn($q) => $q->whereNull('agent_id'),
            fn($q) => $q->where('agent_id', $agent_id)
        );
        $otpRecord = $otpRecord->latest('id')->first();
        $currentTime = Carbon::now();
        if (!$otpRecord) {
            return [
                'status' => false, 
                'message' => __('messages.otp_invalid'), 
            ];
        }
        if ($otpRecord->blocked_until && Carbon::parse($otpRecord->blocked_until)->greaterThan($currentTime)) {
            $remainingSeconds = Carbon::parse($otpRecord->blocked_until)->diffInSeconds($currentTime);
            $minutes = floor($remainingSeconds / 60);
            $seconds = $remainingSeconds % 60;
            $message = __('messages.otpblock', [
                'minutes' => $minutes,
                'seconds' => $seconds,
            ]);
            return [
                'status' => false,
                'message' => $message,
                'remaining_cooldown_seconds' => $remainingSeconds
            ];
        }
        if ($otpRecord->expires_on && Carbon::parse($otpRecord->expires_on)->lessThan($currentTime)) {
            DB::table('tbl_otp')
                ->where('id', $otpRecord->id)
                ->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'last_attempt_on' => $currentTime,
                    'blocked_until' => calculateBlockedUntil($otpRecord->attempts + 1),
                    'updated_on' => $currentTime,
                    'verified' => 0
                ]);
            return ['status' => false, 'message' => __('messages.otpexpire')];
        }
        if ($otpRecord->code !== $code) {
            $newAttempts = $otpRecord->attempts + 1;
            $blockedUntil = calculateBlockedUntil($newAttempts);
            DB::table('tbl_otp')
                ->where('id', $otpRecord->id)
                ->update([
                    'attempts' => $newAttempts, // Increment attempts
                    'last_attempt_on' => $currentTime,
                    'blocked_until' => $blockedUntil, // Set new block
                    'updated_on' => $currentTime,
                    'verified' => 0 // Ensure it's not marked verified
                ]);
            $message = __('messages.otp_invalid');
            $remainingSeconds = 0;
            if ($blockedUntil) {
                $remainingSeconds = Carbon::parse($blockedUntil)->diffInSeconds($currentTime);
                $minutes = floor($remainingSeconds / 60);
                $seconds = $remainingSeconds % 60;
                $message = __('messages.otpblock', [
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                ]);
            }
            return [
                'status' => false,
                'message' => $message,
                'remaining_cooldown_seconds' => $blockedUntil ? $remainingSeconds : 0
            ];
        }
        DB::table('tbl_otp')
            ->where('id', $otpRecord->id)
            ->update([
                'verified' => 1, // Mark as verified
                'attempts' => 0, // Reset attempts on status
                'last_attempt_on' => $currentTime,
                'blocked_until' => null, // Clear any block
                'updated_on' => $currentTime,
            ]);
        return ['status' => true, 'message' => __('messages.otpsuccess')];
    }
}

if (!function_exists('calculateBlockedUntil')) {
    /**
     * Calculates the blocked_until timestamp based on the number of attempts.
     *
     * @param int $attempts The current number of failed attempts.
     * @return Carbon|null The timestamp when the block ends, or null if no block.
     */
    function calculateBlockedUntil(int $attempts): ?Carbon
    {
        $currentTime = Carbon::now();
        $cooldownPeriod = 0; // seconds
        switch ($attempts) {
            case 1:
                $cooldownPeriod = 30; // 30 seconds
                break;
            case 2:
                $cooldownPeriod = 2 * 60; // 2 minutes
                break;
            case 3:
                $cooldownPeriod = 30 * 60; // 30 minutes
                break;
            case 4:
            default: // For 4th attempt and beyond, keep it at 1 hour or increase as needed
                $cooldownPeriod = 60 * 60; // 1 hour
                break;
        }
        return $currentTime->addSeconds($cooldownPeriod);
    }

}

if (!function_exists('verifyResponse')) {
    function verifyResponse($data) {
        $signature = $data['encode_sign'];
        unset($data['encode_sign']);
        ksort($data);
        $signStr = http_build_query($data) . config('security.sign_key');
        return $signature === md5($signStr);
    }
}

if (!function_exists('encryptData')) {
    /**
     * Encrypts data and returns base64 encoded encrypted string and IV suffix.
     *
     * @return array ['data' => encrypted_base64_string, 'iv' => iv_suffix]
     */
    function encryptData(array $payload): array
    {
        ksort($payload); // Sort by key 按键名排序
        $signStr = http_build_query($payload) . config('security.sign_key');
        $payload['encode_sign'] = md5($signStr); // 32-char MD5
        $ivSuffix = substr(bin2hex(random_bytes(3)), 0, 6); // 6 random chars 随机6位
        $iv = config('security.iv_base') . $ivSuffix; // Full 16-char IV 完整16位IV
        $encrypted = openssl_encrypt(
            json_encode($payload),
            'AES-256-CBC',
            config('security.aes_key'),
            OPENSSL_RAW_DATA,
            $iv
        );
        return [
            'data' => base64_encode($encrypted),
            'iv' => $ivSuffix,
        ];
    }
}

if (!function_exists('decryptData')) {
    /**
     * Decrypts base64 encoded data using the provided IV suffix.
     *
     * @return array The decrypted payload.
     */
    function decryptData(string $encryptedDataBase64, string $ivSuffix): array
    {
        $decrypted = openssl_decrypt(
            base64_decode($encryptedDataBase64),         // 先做Base64解码
            'AES-256-CBC',                               // 加密算法
            config('security.aes_key'),                  // 加密密钥(与加密时相同)
            OPENSSL_RAW_DATA,                            // 数据格式
            config('security.iv_base') . $ivSuffix       // 拼接完整16位IV
        );
        $result = json_decode($decrypted, true);
        if (!verifyResponse($result)) {
            return null;
        }
        return $result;
    }
}

if (!function_exists('sendEncryptedJsonResponse')) {
    /**
     * Encrypts a payload and sends it as a JSON response.
     *
     * @param array $payload The data to encrypt for the response.
     * @param int $statusCode The HTTP status code for the response.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception If encryption fails.
     */
    function sendEncryptedJsonResponse(array $payload, int $statusCode) {
        $encryptedResponse = encryptData($payload);
        return response()->json([
            'data' => $encryptedResponse['data'],
            'iv' => $encryptedResponse['iv'],
        ], $statusCode);
    }
}

if (! function_exists('LogCreateAccount')) {
    function LogCreateAccount( $tbl_table, $type, $target, $request = null)
    {
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "create_account", $target);
        activityLog( $tbl_table, $type, "create_account", $log_desc, $request );
    }
}

if (! function_exists('LogLogin')) {
    function LogLogin($tbl_table, $type, $target, $request = null)
    {
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "login", $target);
        activityLog( $tbl_table, $type, "login", $log_desc, $request );
    }
}

if (! function_exists('LogLogout')) {
    function LogLogout(Request $request, string $type)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return;
        }
        $tbl_table = DB::table('tbl_'.$type)
                        ->where($type.'_id', $authorizedUser->currentAccessToken()->tokenable_id )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
        if (!$tbl_table) {
            return;
        }
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "logout", $tbl_table->$username);
        activityLog( $tbl_table, $type, "logout", $log_desc, $request );
    }
}

if (! function_exists('LogChangePassword')) {
    function LogChangePassword($tbl_table, $type, $request = null)
    {
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "changepassword");
        activityLog( $tbl_table, $type, "changepassword", $log_desc, $request );
    }
}

if (! function_exists('LogBlock')) {
    function LogBlock($tbl_table, $type, $target, $block = true, $request = null)
    {
        $username = "{$type}_name";
        $logblock = $block ? "block" : "unblock" ;
        $log_desc = LogDesc($tbl_table->$username, $type, $logblock, $target);
        activityLog( $tbl_table, $type, $logblock, $log_desc, $request );
    }
}

if (! function_exists('LogAlarm')) {
    function LogAlarm($tbl_table, $type, $target, $alarm = true, $request = null)
    {
        $username = "{$type}_name";
        $logalarm = $alarm ? "active_alarm" : "deactive_alarm" ;
        $log_desc = LogDesc($tbl_table->$username, $type, $logalarm, $target);
        activityLog( $tbl_table, $type, $logalarm, $log_desc, $request );
    }
}

if (! function_exists('LogCreatePlayerAccount')) {
    function LogCreatePlayerAccount( $tbl_table, $type, $target, $request = null)
    {
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "create_player_account", $target);
        activityLog( $tbl_table, $type, "create_player_account", $log_desc, $request );
    }
}

if (! function_exists('LogDeposit')) {
    function LogDeposit( $tbl_table, $type, $target, $request = null)
    {
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "deposit", $target);
        activityLog( $tbl_table, $type, "deposit", $log_desc, $request );
    }
}

if (! function_exists('LogWithdraw')) {
    function LogWithdraw( $tbl_table, $type, $target, $request = null)
    {
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "withdraw", $target);
        activityLog( $tbl_table, $type, "withdraw", $log_desc, $request );
    }
}

if (! function_exists('LogDepositPlayer')) {
    function LogDepositPlayer( $tbl_table, $type, $target, $request = null)
    {
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "gamedeposit", $target);
        activityLog( $tbl_table, $type, "gamedeposit", $log_desc, $request );
    }
}

if (! function_exists('LogWithdrawPlayer')) {
    function LogWithdrawPlayer( $tbl_table, $type, $target, $request = null)
    {
        $username = "{$type}_name";
        $log_desc = LogDesc($tbl_table->$username, $type, "gamewithdraw", $target);
        activityLog( $tbl_table, $type, "gamewithdraw", $log_desc, $request );
    }
}


if (!function_exists('formatPhone')) {
    function formatPhone(?string $phone): string
    {
        if (!$phone) {
            return '-';
        }
        // Malaysia format starting with 60
        if (str_starts_with($phone, '60') && strlen($phone) >= 10) {
            return substr($phone, 0, 4) . ' '
                . substr($phone, 4, 4) . ' '
                . substr($phone, 8);
        }
        // Local format starting with 0
        if (str_starts_with($phone, '0') && strlen($phone) >= 10) {
            return substr($phone, 0, 3) . ' '
                . substr($phone, 4, 4) . ' '
                . substr($phone, 8);
        }
        // Fallback
        return $phone;
    }
}
