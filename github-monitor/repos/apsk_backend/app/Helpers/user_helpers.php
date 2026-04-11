<?php

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Manager;
use App\Models\Member;
use App\Models\Shop;
use App\Models\Device;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class Prefix
{
    private static string $admin = "A";
    private static string $member = "EX";
    private static string $shop = "S";
    private static string $manager = "M";
    private static string $trasaction = "T";
    private static string $invoice = "INV";
    private static string $shoptrasaction = "TS";
    private static string $managertrasaction = "TM";
    private static string $agenttrasaction = "TA";
    private static string $gameplatformtrasaction = "TGP";
    private static string $point = "P";
    private static string $phone = "+";
    protected static $expireSeconds = 3;   // Duplicate window in seconds
    protected static $retentionDays = 2;    // Keep last X days of files
    protected static $storagePath = null;

    private static function method( $method )
    {
        switch ($method) {
            case 'admin':
                return self::$admin;
                break;
            case 'member':
                return self::$member;
                break;
            case 'shop':
                return self::$shop;
                break;
            case 'manager':
                return self::$manager;
                break;
            case 'trasaction':
                return self::$trasaction;
                break;
            case 'invoice':
                return self::$invoice;
                break;
            case 'shoptrasaction':
                return self::$shoptrasaction;
                break;
            case 'managertrasaction':
                return self::$managertrasaction;
                break;
            case 'agenttrasaction':
                return self::$agenttrasaction;
                break;
            case 'gameplatformtrasaction':
                return self::$gameplatformtrasaction;
                break;
            case 'point':
                return self::$point;
                break;
            case 'phone':
                return self::$phone;
                break;
            default:
                return "";
                break;
        }
    }
    
    public static function create( $id, $type )
    {
        return self::method($type) . strval($id);
    }

    public static function phonecode()
    {
        return "1111";
    }

    public static function israndomphonecode($phone)
    {
        $phone_code = self::phonecode();
        return str_starts_with($phone, $phone_code );
    }

    public static function islogindevicemeta(Request $request, $tbl_member)
    {
        // skip OTP for devicemeta
        if ( is_null($tbl_member->devicemeta) ) {
            return [
                'status' => false,
                'message' => null,
                'error' => null,
            ];
        }
        $devicemeta = json_decode( $tbl_member->devicemeta, true );
        if (!is_array($devicemeta)) {
            return ['status' => false, 'message' => null, 'error' => null];
        }
        $metaHeader = $request->header('X-Device-Meta');
        if (!$metaHeader) {
            return [
                'status' => false,
                'message' => null,
                'error' => null,
            ];
        }
        $logindevicemeta = json_decode($metaHeader, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logindevicemeta = json_decode(stripslashes($metaHeader), true);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = base64_decode($metaHeader, true);
            if ($decoded !== false) {
                $logindevicemeta = json_decode($decoded, true);
            }
        }
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($logindevicemeta)) {
            return [
                'status' => false,
                'message' => __('member.invalid_devicemeta'),
                'error' => __('member.invalid_devicemeta'),
            ];
        }
        if ( !isset($logindevicemeta['device_id']) ) {
            return [
                'status' => false,
                'message' => null,
                'error' => null,
            ];
        }
        if ( !isset($devicemeta['device_id']) ) {
            return [
                'status' => false,
                'message' => null,
                'error' => null,
            ];
        }
        if ( 
            $devicemeta['device_id'] !== $logindevicemeta['device_id']
        ) {
            return [
                'status' => false,
                'message' => null,
                'error' => null,
            ];
        }
        return [
            'status' => true,
            'message' => null,
            'error' => null,
        ];
    }

    public static function deviceid(string $jsondevicemeta)
    {
        $logindevicemeta = json_decode($jsondevicemeta, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $logindevicemeta = json_decode(stripslashes($jsondevicemeta), true);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = base64_decode($jsondevicemeta, true);
            if ($decoded !== false) {
                $logindevicemeta = json_decode($decoded, true);
            }
        }
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($logindevicemeta)) {
            return null;
        }
        if ( !isset($logindevicemeta['device_id']) ) {
            return "";
        }
        return $logindevicemeta['device_id'];
    }

    public static function islogindevicemetamultiple(Request $request, $tbl_member)
    {
        $metaHeader = $request->header('X-Device-Meta');
        if (!$metaHeader) {
            return [
                'status' => false,
                'message' => null,
                'error' => null,
            ];
        }
        $devicemetas = Device::where('status',1)
                            ->where('delete',0)
                            ->where('member_id', $tbl_member->member_id )
                            ->pluck('devicemeta')
                            ->toArray();
        if (empty($devicemetas))
        {
            return [
                'status' => false,
                'message' => null,
                'error' => null,
            ];
        }
        $headerdeviceid = self::deviceid($metaHeader);
        if (is_null($headerdeviceid)) {
            return [
                'status' => false,
                'message' => __('member.invalid_devicemeta'),
                'error' => __('member.invalid_devicemeta'),
            ];
        }
        $memberdevice_ids = [];
        foreach ($devicemetas as $devicemeta) {
            $device_id = self::deviceid($devicemeta);
            if (!empty($device_id)) {
                $memberdevice_ids[] = $device_id;
            }
        }
        if ( !in_array( $headerdeviceid, $memberdevice_ids ) ) {
            return [
                'status' => false,
                'message' => null,
                'error' => null,
            ];
        }
        return [
            'status' => true,
            'message' => null,
            'error' => null,
        ];
    }
    
    /**
     * Get storage folder path
     */
    protected static function getStoragePath(): string
    {
        if (!self::$storagePath) {
            self::$storagePath = storage_path('app/member/token');
        }

        // Ensure directory exists
        if (!is_dir(self::$storagePath)) {
            mkdir(self::$storagePath, 0755, true);
        }

        return self::$storagePath;
    }

    /**
     * Get today's token file path
     */
    protected static function getFilePath(): string
    {
        $date = Carbon::now()->format('Y-m-d');
        return self::getStoragePath() . "/request_tokens_{$date}.json";
    }

    /**
     * Delete old daily token files beyond retention period
     */
    public static function cleanOldFiles()
    {
        $files = File::files(self::getStoragePath());
        $now = Carbon::now();

        foreach ($files as $file) {
            $filename = $file->getFilename();

            if (preg_match('/^request_tokens_(\d{4}-\d{2}-\d{2})\.json$/', $filename, $matches)) {
                $fileDate = Carbon::createFromFormat('Y-m-d', $matches[1]);

                if ($fileDate->diffInDays($now) > self::$retentionDays) {
                    File::delete($file->getRealPath());
                }
            }
        }
    }

    /**
     * Check if a request token is duplicate
     * Returns true = not duplicate, false = duplicate
     *
     * @param Request $request
     * @param array $extra Optional extra parameters to include in token
     */
    public static function duplicaterequest( $id, Request $request, array $extra = []): bool
    {
        // Deterministic token: user + API route + optional extra params
        $tokenString = (string) $id . '|' . $request->method() . '|' . $request->path();
        if (!empty($extra)) {
            $tokenString .= json_encode($extra);
        }
        $token = md5($tokenString);

        $now = Carbon::now()->timestamp;

        // Auto-clean old daily files
        self::cleanOldFiles();

        $filePath = self::getFilePath();
        $data = [];

        // Open file with exclusive lock (create if not exists)
        $file = fopen($filePath, 'c+');
        if (!$file) {
            return false; // Unsafe to proceed
        }

        try {
            if (flock($file, LOCK_EX)) {

                // Read existing tokens safely
                $contents = stream_get_contents($file);
                $data = $contents ? json_decode($contents, true) : [];
                if (!is_array($data)) $data = [];

                // Remove expired tokens
                $data = array_filter($data, fn($timestamp) => ($now - $timestamp) < self::$expireSeconds);

                // Check for duplicate
                if (isset($data[$token])) {
                    return false; // Duplicate found
                }

                // Add new token
                $data[$token] = $now;

                // Rewind file and write updated data
                ftruncate($file, 0);
                rewind($file);
                fwrite($file, json_encode($data));

            } else {
                return false; // Could not acquire lock
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }

        return true; // Not duplicate
    }

    public static function dashboardslider($rowslider = 50)
    {
        $results = [];

        $actions = [
            [
                'text' => __('slider.dashboarddeposit'),
                'min' => 50,
                'max' => 800,
                'range' => '50-800'
            ],
            [
                'text' => __('slider.dashboardwin'),
                'min' => 100,
                'max' => 10000,
                'range' => '100-10000'
            ],
            [
                'text' => 'slider.dashboardwithdraw',
                'min' => 300,
                'max' => 10000,
                'range' => '300-10000'
            ],
        ];

        for ($i = 0; $i < $rowslider; $i++) {

            // random last 4 digit
            $last4 = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

            // random action
            $action = $actions[array_rand($actions)];

            // random amount within range
            $amount = rand($action['min'], $action['max']);

            $results[] = __(
                $action['text'],
                [
                    'member_name' => "+60****{$last4}",
                    'amount' => "RM {$amount}",
                ], 
            );
        }

        return $results;
    }
}