<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use App\Enums\TokenAbility;
use Carbon\Carbon;
use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Member;
use App\Models\Version;
use App\Models\Application;

class AuthController extends Controller
{
    public function refresh(Request $request, string $type = "member") // $type is the route parameter
    {
        $plainTextRefreshToken = $request->input('refresh_token');

        if (!$plainTextRefreshToken) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('validation.refreshtoken_required'),
                    'error' => __('validation.refreshtoken_required'),
                ],
                401
            );
        }

        $token = PersonalAccessToken::findToken($plainTextRefreshToken);

        // --- VALIDATION ---
        // 1. Check if token exists, is for a user, has refresh ability, and is not expired.
        // 2. Add the type mismatch check using the $type route parameter.
        if (
            !$token ||
            !$token->tokenable ||
            !$token->can(TokenAbility::TOKEN_REFRESH->value) ||
            $token->expires_at->isPast() ||
            $token->type !== $type // <--- CORRECTED: Compare with $type from route parameter
        ) {
            if ($token) {
                // If token exists but is invalid/expired/mismatched type, revoke it for security
                $token->delete();
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('validation.refreshtoken_invalid'),
                    'error' => __('validation.refreshtoken_invalid'),
                ],
                401
            );
        }

        $user = $token->tokenable;
        $deviceName = $token->name; // Use the name from the old token
        $tokenType = $token->type; // Get the type from the old refresh token (which we just validated against $type)

        // Invalidate the used refresh token (important for security: one-time use)
        $token->delete();

        // Issue a new Access Token
        $newAccessToken = $user->createToken(
            $deviceName,
            [TokenAbility::API_ACCESS->value],
            Carbon::now()->addDays(config('sanctum.refresh_token_expiration_days'))
        );
        $newAccessToken->accessToken->type = $tokenType; // Assign the retrieved type
        $newAccessToken->accessToken->save();

        // Issue a new Refresh Token
        $newRefreshToken = $user->createToken(
            $deviceName,
            [TokenAbility::TOKEN_REFRESH->value],
            Carbon::now()->addDays(config('sanctum.refresh_token_expiration_days'))
        );
        $newRefreshToken->accessToken->type = $tokenType; // Assign the retrieved type
        $newRefreshToken->accessToken->save();

        // Get Current Detail
        $tbl_table = DB::table('tbl_'.$type)
                ->where($type.'_id', $token->tokenable_id)
                ->first();

        return sendEncryptedJsonResponse(
            [
                'status' => true,
                'message' => __('messages.refreshtokensuccess'),
                'error' => "",
                'access_token' => $newAccessToken->plainTextToken,
                'refresh_token' => $newRefreshToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration') * 60,
                'refresh_expires_in' => config('sanctum.refresh_token_expiration_days') * 24 * 60 * 60,
                'data' => $tbl_table,
            ],
            200
        );
    }

    public function testotpemail(Request $request)
    {
        try{       
            $otp = str_pad(random_int(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
            $data = [
                'subject' => __('messages.otp_title'),
                'otp' => $otp,
            ];
            $result = sendDynamicEmail(
                $request->input('email'), 
                "emails.otp", 
                $data
            );
            return response()->json([
                    'status' => true,
                    'message' => "Email Sent",
                    'error' => "",
                    'otp_email' => $otp,
                    'result' => $result,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * check version.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function version(Request $request, string $type)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_application = Application::where('status', 1)
                                          ->where('delete', 0)
                                          ->where('type', $type)
                                          ->where('platform', $request->input('platform') )
                                          ->first();
            if (!$tbl_application) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('application.no_data_found'),
                        'error' => __('application.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_application->url = $tbl_application->download_url;
            unset($tbl_application->download_url);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_application,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * check version list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function versionlist(Request $request, string $type)
    {
        try {
            $tbl_application = Application::where('status', 1)
                                          ->where('delete', 0)
                                          ->where('type', $type)
                                          ->get();
            $tbl_application = $tbl_application->map(function ($application) {
                $application->url = $application->download_url;
                unset($application->download_url);
                return $application;
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_application,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }
}