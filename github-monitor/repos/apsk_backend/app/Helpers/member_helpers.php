<?php

use App\Models\Member;
use App\Models\Gamemember;
use App\Models\Gamelog;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

if (!function_exists('GeoLocation')) {
    function GeoLocation(Request $request)
    {
        $ipAddress = $request->ip();
        $apiUrl = "http://ip-api.com/json/{$ipAddress}";
        try {
            $response = Http::get($apiUrl);
            if ($response->successful()) {
                $data = $response->json();
                return [
                    "ip" => $ipAddress,
                    "country" => $data['country'] ?? null,
                    "province" =>  $data['regionName'] ?? null,
                    "city" =>  $data['city'] ?? null,
                    "latitude" =>  $data['lat'] ?? null,
                    "longitude" =>  $data['lon'] ?? null,
                ];
            } else {
                Log::error("MemberLocation - Failed to fetch location: ". json_encode($data) );
                return null;
            }
        } catch (\Exception $e) {
            Log::error("MemberLocation - Failed to fetch location: {$e->getMessage()}");
            return null;
        }
    }
}

function DeviceUserAgent($userAgent)
{
    $device = 'Unknown';
    $os = 'Unknown';
    $browser = 'Unknown';
    $model = 'Unknown';

    // ----- Device Type -----
    if (preg_match('/mobile|iphone|android/i', $userAgent)) {
        $device = 'Mobile';
    } elseif (preg_match('/ipad|tablet/i', $userAgent)) {
        $device = 'Tablet';
    } else {
        $device = 'Desktop';
    }

    // ----- OS -----
    if (preg_match('/Windows NT/i', $userAgent)) $os = 'Windows';
    elseif (preg_match('/Mac OS X|Macintosh/i', $userAgent)) $os = 'MacOS';
    elseif (preg_match('/Android/i', $userAgent)) $os = 'Android';
    elseif (preg_match('/iPhone OS|CPU iPhone OS/i', $userAgent)) $os = 'iOS';
    elseif (preg_match('/Linux/i', $userAgent)) $os = 'Linux';

    // ----- Browser -----
    if (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/Edg/i', $userAgent)) $browser = 'Edge';
    elseif (preg_match('/OPR|Opera/i', $userAgent)) $browser = 'Opera';

    // ----- Partial Model Detection -----
    // iPhone model (not perfect)
    if (preg_match('/iPhone/i', $userAgent)) {
        $model = 'iPhone (exact model hidden by Safari)';
    }

    // Android model (Samsung, Xiaomi, etc.)
    if (preg_match('/Android .*; ([^)]+)\)/i', $userAgent, $match)) {
        $model = trim($match[1]); // Ex: "SM-S918B", "Pixel 7"
    }

    return [
        'device_type' => $device,
        'os' => $os,
        'browser' => $browser,
        'device_model' => $model,
    ];
}

if (!function_exists('GetMemberID')) {
    /**
     * Retrieves the member ID for a given member name, using caching for performance.
     *
     * @param string $member_name The name of the member.
     * @return ?int The ID of the member, or null if not found.
     */
    function GetMemberID(string $member_name): ?int
    {
        if (empty($member_name)) {
            return null;
        }
        try {
            $tbl_member = Member::where('member_name', $member_name)->first();
            return $tbl_member ? (int) $tbl_member->member_id : null;
        } catch (\Exception $e) {
            Log::error("GetMemberID - Failed to fetch member ID for {$member_name}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetMemberName')) {
    /**
     * Retrieves the member Name for a given member name, using caching for performance.
     *
     * @param int $member_id The name of the member.
     * @return ?string The Name of the member, or null if not found.
     */
    function GetMemberName(int $member_id): ?string
    {
        if (empty($member_id)) {
            return null;
        }
        try {
            $tbl_member = Member::where('member_id', $member_id)->first();
            return $tbl_member ? (string) $tbl_member->member_name : null;
        } catch (\Exception $e) {
            Log::error("GetMemberName - Failed to fetch member Name for {$member_id}: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('GetMemberIDBalance')) {
    /**
     * Retrieves the Member Balance for a given shop name, using caching for performance.
     *
     * @param ?int $member_id The id of the shop.
     * @return ?float The Balance of the shop, or null if not found.
     */
    function GetMemberIDBalance(?int $member_id): ?float
    {
        if (empty($member_id)) {
            return null;
        }
        try {
            $tbl_member = Member::where('member_id', $member_id)->first();
            return $tbl_member ? (float) $tbl_member->balance : null;
        } catch (\Exception $e) {
            Log::error("GetMemberIDBalance - Failed to fetch balance for {$member_id}: {$e->getMessage()}");
            return null;
        }
    }

}

if (!function_exists('CountMemberBalance')) {
    function CountMemberBalance( $member_id, $amount)
    {
        try {
            $updateData = [
                'balance' => GetMemberIDBalance($member_id) + $amount,
                'updated_on' => now(),
            ];
            $tbl_member = Member::where('member_id', $member_id)->first();
            $tbl_member->update($updateData);
        } catch (\Exception $e) {
            Log::error("CountMemberBalance - Failed to count balance: {$e->getMessage()}");
            return null;
        }
    }
}

if (!function_exists('generateGoogle2FA')) {
    function generateGoogle2FA($user_id, $type = "member", $secret = null, $appName = "911DJAPP" )
    {
        $google2fa = new Google2FA();
        $secret = $secret ?? $google2fa->generateSecretKey();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            $appName,
            "{$user_id}|{$type}",
            $secret
        );
        return [
            'secret' => $secret,
            'qr'     => $qrCodeUrl,
        ];
    }
}

if (!function_exists('verifyGoogle2FA')) {
    function verifyGoogle2FA($secret, $otp)
    {
        $google2fa = new Google2FA();
        return $google2fa->verifyKey( decryptPassword( $secret ) , $otp);
    }
}

// 充值了 没有玩游戏 抽5%
if (!function_exists('hasgame')) {
    function hasgame(int $member_id): bool
    {
        return Gamelog::whereIn(
                'gamemember_id',
                Gamemember::where('member_id', $member_id)
                    ->select('gamemember_id')
            )
            ->where('status', 1)
            ->where('delete', 0)
            ->where('betamount', '>', 0)
            ->exists();
    }
}

// Manager设置店铺 抽5%
if (!function_exists('shopNoWithdrawalFee')) {
    function shopNoWithdrawalFee(int $shopId): bool
    {
        return (bool) Shop::where('shop_id', $shopId)
            ->where('no_withdrawal_fee', 1)
            ->exists();
    }
}
