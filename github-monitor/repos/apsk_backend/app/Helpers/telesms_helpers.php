<?php

use App\Models\Telesmscallback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Telesms
{
    // /**
    //  * Extract mtmsgid from API response.
    //  */
    // protected static function extractMsgId($resp)
    // {
    //     preg_match('/mtmsgid=(.*?)&/', $resp, $matches);
    //     return (!empty($matches) && count($matches) >= 2) ? $matches[1] : "";
    // }

    protected static function decodeResponse($response)
    {
        $data = [];
        parse_str($response, $data);
        return $data;
    }

    /**
     * API credentials and URL
     */
    protected static function getApiConfig()
    {
        return [
            'url' => rtrim(config('services.telesms.url', env('TELESMS_API_URL')), '/'),
            'cpid' => config('services.telesms.key', env('TELESMS_API_KEY')),
            'cppwd' => config('services.telesms.secret', env('TELESMS_SECRET')),
        ];
    }

    private static function logError($response, $error = null)
    {
        Telesmscallback::create([
            'response' => json_encode($response),
            'error' => $error,
            'created_on' => now(),
            'updated_on' => now(),
        ]);
    }

    /**
     * Verify if a phone number is valid
     *
     * @param string $phone
     * @return array
     */
    public static function verifyPhoneNumber($phone)
    {
        $config = self::getApiConfig();
        $url = "{$config['url']}/verifyPhoneNumber?cpid={$config['cpid']}&cppwd={$config['cppwd']}&da={$phone}";

        try {
            $response = file_get_contents($url);
            $result = json_decode($response, true);
            if ( !isset($result['errcode']) ) {
                self::logError( $url, $response );
                return false;
            }
            if ( $result['mtstat'] !== 'ACCEPTD' || $result['errcode'] !== '000' ) {
                self::logError( $url, $response );
                return false;
            }
            return true;
        } catch (Exception $e) {
            Log::error('[TeleSMS] Verify Phone Number failed', ['error' => $e->getMessage()]);
            self::logError( $url, $e->getMessage() );
            return false;
        }
    }

    /**
     * Send SMS message via TeleSMS API
     *
     * @param string $phone Phone number (e.g. 60123456789)
     * @param string $message SMS content
     * @return array
     */
    public static function send($phone, $message)
    {
        $israndomphonecode = \Prefix::israndomphonecode($phone);
        if ( $israndomphonecode ) {
            return [
                'success' => false,
                'message' => __('messages.phone_invalid'),
                'error' => __('messages.phone_invalid'),
                'code' => 400,
            ];
        }
        $config = self::getApiConfig();
        $encodedMessage = urlencode($message);
        $url = "{$config['url']}/submit?command=MT_REQUEST&cpid={$config['cpid']}&cppwd={$config['cppwd']}&da={$phone}&sm={$encodedMessage}";
        try {
            $phonevalid = self::verifyPhoneNumber($phone);
            if ( !$phonevalid ) {
                return [
                    'success' => false,
                    'message' => __('messages.phone_invalid'),
                    'error' => __('messages.phone_invalid'),
                    'code' => 400,
                ];
            }
            $response = file_get_contents($url);
            $result = self::decodeResponse($response);
            if ( !isset($result['mtmsgid']) ) {
                self::logError( $response, 'No message ID returned' );
                return [
                    'success' => false,
                    'message' => __('messages.phone_fail'),
                    'error' => 'No message ID returned',
                    'code' => 500,
                ];
            }
            if ( $result['mtstat'] !== 'ACCEPTD' || $result['mterrcode'] !== '000' ) {
                self::logError( $response, 'Delivery Failed' );
                return [
                    'success' => false,
                    'message' => __('messages.phone_fail'),
                    'error' => 'No message ID returned',
                    'code' => 500,
                ];
            }
            $delivervalid = self::getDeliveryStatus( $result['mtmsgid'], $phone );
            return [
                'success' => true,
                'message' => __('messages.phone_success'),
                'error' => '',
                'code' => 200,
                'msg_id' => $result['mtmsgid'],
            ];
        } catch (Exception $e) {
            Log::error('[TeleSMS] Send failed', ['error' => $e->getMessage()]);
            self::logError( $url, $e->getMessage() );
            return [
                'success' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    /**
     * Send SMS message to multiple recipients via TeleSMS API
     *
     * @param array $phones Array of phone numbers (e.g. ['60123456789','60123456780'])
     * @param string $message SMS content
     * @param string|null $sender Optional custom sender ID (alphanumeric, max 11 chars)
     * @param string|null $lang Optional language for voice messages (not required for SMS)
     * @return array
     */
    public static function sendMultiple($phones, $message, $sender = null, $lang = null)
    {
        $config = self::getApiConfig();
        $multiPhone = implode(',', $phones);
        $encodedMessage = urlencode($message);
        $url = "{$config['url']}/submit?command=MULTI_MT_REQUEST&cpid={$config['cpid']}&cppwd={$config['cppwd']}&da={$multiPhone}&sm={$encodedMessage}";
        // Optional parameters
        if ($sender) {
            $url .= "&sa={$sender}";
        }
        if ($lang) {
            $url .= "&lang={$lang}";
        }
        try {
            $response = file_get_contents($url);
            preg_match('/mtmsgid=(.*?)&/', $response, $matches);
            $msgId = (!empty($matches) && count($matches) >= 2) ? $matches[1] : "";
            if (empty($msgId)) {
                return [
                    'success' => false,
                    'message' => 'No message ID returned',
                    'response' => $response,
                ];
            }
            return [
                'success' => true,
                'msg_id' => $msgId,
                'response' => $response,
            ];
        } catch (Exception $e) {
            Log::error('[TeleSMS] Send Multiple failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Delivery Report
     *
     * @param string $msgId Message ID returned from send()
     * @param string|null $phone (optional) phone number with country code (e.g. 60123456789)
     * @return array
     */
    public static function getDeliveryStatus($msgId, $phone = null)
    {
        $config = self::getApiConfig();
        $url = "{$config['url']}/dr/get-rptstatus?cpid={$config['cpid']}&cppwd={$config['cppwd']}&msgid={$msgId}";
        if ($phone) {
            $url .= "&da={$phone}";
        }
        try {
            $response = file_get_contents($url);
            $result = json_decode($response, true);
            if ( !isset($result['errcode']) ) {
                self::logError( $url, $response );
                return false;
            }
            if ( $result['errcode'] !== '000' ) {
                self::logError( $url, $response );
                return false;
            }
            return true;
        } catch (Exception $e) {
            Log::error('[TeleSMS] Get Delivery Status failed', ['error' => $e->getMessage()]);
            self::logError( $url, $e->getMessage() );
            return false;
        }
    }

    /**
     * Get Account Balance
     *
     * @return array
     */
    public static function getBalance()
    {
        $config = self::getApiConfig();
        $url = "{$config['url']}/get-balance?cpid={$config['cpid']}&cppwd={$config['cppwd']}";
        try {
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            return $data ?: ['error' => 'Invalid response', 'raw' => $response];
        } catch (Exception $e) {
            Log::error('[TeleSMS] Get Balance failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get Country Pricing
     *
     * @param string
     * @return array
     */
    public static function getPricing()
    {
        $config = self::getApiConfig();
        $countryCode = 'MY';
        $url = "{$config['url']}/get-pricing?cpid={$config['cpid']}&cppwd={$config['cppwd']}&countryCode={$countryCode}";
        try {
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            return $data ?: ['error' => 'Invalid response', 'raw' => $response];
        } catch (Exception $e) {
            Log::error('[TeleSMS] Get Pricing failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Check for sensitive words in a message
     *
     * @param string $message Text to check for sensitive words
     * @return array
     */
    public static function checkSensitiveWord($message)
    {
        $config = self::getApiConfig();
        $url = "{$config['url']}/sensitive/verify";
        try {
            $response = Http::withoutVerifying()->asForm()->post($url, [
                'cpid' => $config['cpid'],
                'cppwd' => $config['cppwd'],
                'msg'  => $message,
            ]);
            $data = $response->json();
            return $data ?: ['error' => 'Invalid response', 'raw' => $response];
        } catch (Exception $e) {
            Log::error('[TeleSMS] Sensitive Word Check failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }
}