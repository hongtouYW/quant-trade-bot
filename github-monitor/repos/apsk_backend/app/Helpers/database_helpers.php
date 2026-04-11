<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Enums\TokenAbility;
use Illuminate\Support\Str;
use App\Models\Log;
use App\Models\Member;
use App\Models\User;

if (!function_exists('countTableRows')) {
    function countTableRows(string $tableName): int
    {
        return \DB::table($tableName)->count();
    }
}

if (!function_exists('issueApiTokens')) {
    /**
     * Issues new Access and Refresh tokens for a given authenticatable user model.
     *
     * @param Authenticatable $user // This type hint will now correctly accept Manager, Member, Shop, or User models
     * @param string $deviceName The name of the device/client creating the token.
     * @param string $tokenType The custom type for the token (e.g., 'member', 'manager', 'shop', 'user').
     * @return array An array containing 'access_token', 'refresh_token', and their expiry times.
     */
    function issueApiTokens(Authenticatable $user, string $tokenType, string $deviceName = "api-token"): array
    {

        $user->tokens()->where('name', $deviceName)->where('type', $tokenType)->delete();
        $accessTokenExpiration = Carbon::now()->addDays(config('sanctum.refresh_token_expiration_days'));
        $accessTokenRefresh = Carbon::parse('2038-01-19 03:14:07');

        $accessToken = $user->createToken(
            $deviceName,
            [TokenAbility::API_ACCESS->value],
            // Carbon::now()->addMinutes(config('sanctum.expiration'))
            $accessTokenExpiration
        );
        $accessToken->accessToken->type = $tokenType;
        $accessToken->accessToken->save();

        $refreshToken = $user->createToken(
            $deviceName,
            [TokenAbility::TOKEN_REFRESH->value],
            $accessTokenRefresh
        );
        $refreshToken->accessToken->type = $tokenType;
        $refreshToken->accessToken->save();
        $user->update([
            'lastlogin_on' => now(),
        ]);
        return [
            'access_token' => $accessToken->plainTextToken,
            'access_token_expires_at' => $accessTokenExpiration->toDateTimeString(),
            'refresh_token' => $refreshToken->plainTextToken,
            'refresh_token_expires_at' => $accessTokenRefresh->toDateTimeString(),
            'token_type' => 'Bearer',
        ];
    }

}

if (!function_exists('activityLog')) {
    function activityLog($tbl_table, $log_type, $log_text, $log_desc, $request = null)
    {
        $location = null;
        $device = null;
        if (!is_null($request)) {
            $userAgent = $request->userAgent();
            $location = json_encode( GeoLocation($request) );
            $device = json_encode( DeviceUserAgent($userAgent) );
            $log_api = $request->path();
        }

        $user_id = "{$log_type}_id";
        $newdata = [
            'area_code' => $tbl_table->area_code,
            'user_id' => $tbl_table->$user_id,
            'log_type' => $log_type,
            'log_text' => $log_text,
            'log_desc' => $log_desc,
            'log_api'  => $log_api,
            'location' => $location,
            'device' => $device,
            'agent_id' => $tbl_table->agent_id,
            'created_on' => now(),
        ];
        $id = DB::table('tbl_log')->insertGetId($newdata);      
    }
}

/**
 * Generates a unique Malaysian phone number for tbl_member.
 *
 * @return string The unique phone number.
 */
if (!function_exists('UniqueMalaysiaPhoneNumber')) {
    function UniqueMalaysiaPhoneNumber(): string
    {
        $prefix = \Prefix::phonecode();
        $phoneNumber = '';
        $isUnique = false;

        // Keep generating until a unique number is found
        while (!$isUnique) {
            // Generate 7 random digits to make a 10-digit number after '60'
            $suffix = str_pad(random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
            $phoneNumber = $prefix . $suffix;

            // Check if the phone number already exists in tbl_member
            $exists = DB::table('tbl_member')->where('phone', $phoneNumber)->exists();

            if (!$exists) {
                $isUnique = true;
            }
        }

        return $phoneNumber;
    }
}

/**
 * Validate tbl_member.
 *
 * @return array array response.
 */
if (!function_exists('InvalidMember')) {
    function InvalidMember( $member_id )
    {
        $tbl_table = DB::table('tbl_member')->where('member_id', $member_id )->first();
        if (!$tbl_table) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('member.no_data_found'),
                    'error' => __('member.no_data_found'),
                ],
                400
            );
        }
        if ($tbl_table->status !== 1 || $tbl_table->delete === 1 || $tbl_table->alarm === 1 ) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.profileinactive'),
                    'error' => __('messages.profileinactive'),
                ],
                401
            );
        }
        return [];
    }
}

/**
 * createUIDRecord
 *
 * @return string string uid.
 */
if (!function_exists('createUIDRecord')) {
    function createUIDRecord(int $uidLength = 9)
    {
        do {
            $uid = '';
            $uid .= random_int(1, 9);
            for ($i = 1; $i < $uidLength; $i++) {
                $uid .= random_int(0, 9);
            }
        } while (Member::where('uid', $uid)->exists());
        return $uid;
    }
}

if (!function_exists('CheckAvailabilityUser')) {
    function CheckAvailabilityUser($user_id, $type)
    {
        $tbl_table = DB::table('tbl_'.$type)
                        ->where($type.'_id', $user_id )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
        if (!$tbl_table) {
            return [
                'status' => false,
                'message' => __($type.'.no_data_found'),
                'error' => __($type.'.no_data_found'),
            ];
        }
        if ($tbl_table->status !== 1 || $tbl_table->delete === 1 ) {
            return [
                'status' => false,
                'message' => __('messages.profileinactive'),
                'error' => __('messages.profileinactive'),
            ];
        }
        if ( in_array( $type, ['member','shop'] ) ) {
            if ($tbl_table->alarm === 1 ) {
                return [
                    'status' => false,
                    'message' => __('messages.profileinactive'),
                    'error' => __('messages.profileinactive'),
                ];
            }
        }
        return null;
    }
}

/**
 * Get User Agent.
 *
 * @return array array response.
 */
if (!function_exists('GetUserAgent')) {
    function GetUserAgent( $user_id )
    {
        $tbl_user = User::where('user_id', $user_id )
                 ->where('status', 1)
                 ->where('delete', 0)
                 ->first();
        if (!$tbl_user) {
            return null;
        }
        return $tbl_user->agent_id;
    }
}