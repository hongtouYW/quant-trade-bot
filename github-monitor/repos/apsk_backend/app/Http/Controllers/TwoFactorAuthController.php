<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateTwoFactorAuthenticationRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Illuminate\Support\Facades\DB;

class TwoFactorAuthController extends Controller
{
    /**
     * Enable two-factor authentication (Google Authenticator).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enable(Request $request, $type)
    {
        $user = $request->user();

        // Validate password (required if confirmPassword is true in config/fortify.php)
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|max:255',
            'password' => ['required', 'string', 'current_password:api'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.unvalidation'),
                'error' => $validator->errors(),
                'code' => 422,
                'datetime' => now(),
            ], 422);
        }

        try {
            $tbl_table = DB::table('tbl_'.$type)
                            ->where($type.'_id', $user->{"{$type}_id"})
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            if (!$tbl_table) {
                return response()->json([
                    'status' => false,
                    'datetime' => now(),
                    'message' => __('messages.noexist'),
                    'error' => __('messages.noexist'),
                    'code' => 500
                ], 500);
            }
            // Generate TOTP secret and recovery codes
            $secret = app('two-factor')->generateSecretKey();
            $recoveryCodes = GenerateTwoFactorAuthenticationRecoveryCodes::generate();
            // Update user with 2FA fields
            $user->forceFill([
                'two_factor_secret' => encrypt($secret),
                'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
                'two_factor_confirmed_at' => null, // Set to null until confirmed
                'GAstatus' => 0, // Set to 0 until confirmed
            ])->save();
            // Generate QR code for Google Authenticator
            $login =$user->{"{$type}_login"};
            $qrCode = app('two-factor')->getQRCodeSvg(
                $secret,
                $login,
                config('app.name')
            );
            $tbl_table->update([
                'two_factor_secret' => encrypt($secret),
                'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
                'two_factor_confirmed_at' => null, // Set to null until confirmed
                'GAstatus' => 0, // Set to 0 until confirmed
                'updated_on' => now(),
            ]);
            return response()->json([
                'status' => true,
                'message' => __('messages.2FAinitiated'),
                'error' => '',
                'code' => 200,
                'datetime' => now(),
                'qr_code' => $qrCode,
                'recovery_codes' => $recoveryCodes, // Optionally return recovery codes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => __('messages.2FAinitiatedfail'),
                'error' => $e->getMessage(),
                'code' => 500,
                'datetime' => now(),
            ], 500);
        }
    }

    /**
     * Confirm two-factor authentication setup.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request, $type)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.unvalidation'),
                'error' => $validator->errors(),
                'code' => 422,
                'datetime' => now(),
            ], 422);
        }

        try {
            $tbl_table = DB::table('tbl_'.$type)
                            ->where($type.'_id', $user->{"{$type}_id"})
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            if (!$tbl_table) {
                return response()->json([
                    'status' => false,
                    'datetime' => now(),
                    'message' => __('messages.noexist'),
                    'error' => __('messages.noexist'),
                    'code' => 500
                ], 500);
            }
            $twoFactorCode = $request->input('code');
            if (!$user->two_factor_secret || !app(TwoFactorAuthenticationProvider::class)->verify(decrypt($user->two_factor_secret), $twoFactorCode)) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.invalid2FA'),
                    'error' => __('messages.invalid2FA'),
                    'code' => 401,
                    'datetime' => now(),
                ], 401);
            }
            // Confirm 2FA setup
            $user->forceFill([
                'two_factor_confirmed_at' => now(),
                'GAstatus' => 1,
            ])->save();
            $tbl_table->update([
                'two_factor_confirmed_at' => now(),
                'GAstatus' => 1,
                'updated_on' => now(),
            ]);
            return response()->json([
                'status' => true,
                'message' => __('messages.2FAsuccess'),
                'error' => '',
                'code' => 200,
                'datetime' => now(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => __('messages.2FAfail'),
                'error' => $e->getMessage(),
                'code' => 500,
                'datetime' => now(),
            ], 500);
        }
    }

    /**
     * Disable two-factor authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function disable(Request $request, $type)
    {
        $user = $request->user();

        try {
            $tbl_table = DB::table('tbl_'.$type)
                            ->where($type.'_id', $user->{"{$type}_id"})
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            if (!$tbl_table) {
                return response()->json([
                    'status' => false,
                    'datetime' => now(),
                    'message' => __('messages.noexist'),
                    'error' => __('messages.noexist'),
                    'code' => 500
                ], 500);
            }
            // Disable 2FA
            app(DisableTwoFactorAuthentication::class)($user);
            // Clear 2FA fields
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'GAstatus' => 0,
            ])->save();
            $tbl_table->update([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'GAstatus' => 0,
                'updated_on' => now(),
            ]);
            return response()->json([
                'status' => true,
                'message' => __('messages.2FAstop'),
                'error' => '',
                'code' => 200,
                'datetime' => now(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => __('messages.2FAstopfail'),
                'error' => $e->getMessage(),
                'code' => 500,
                'datetime' => now(),
            ], 500);
        }
    }

    /**
     * Get the QR code for 2FA setup.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showQrCode(Request $request, $type)
    {
        $user = $request->user();
        if (!$user->two_factor_secret) {
            return response()->json([
                'status' => false,
                'message' => __('messages.2FAinactive'),
                'error' => __('messages.2FAinactive'),
                'code' => 400,
                'datetime' => now(),
            ], 400);
        }
        $tbl_table = DB::table('tbl_'.$type)
                        ->where($type.'_id', $user->{"{$type}_id"})
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
        if (!$tbl_table) {
            return response()->json([
                'status' => false,
                'datetime' => now(),
                'message' => __('messages.noexist'),
                'error' => __('messages.noexist'),
                'code' => 500
            ], 500);
        }
        return response()->json([
            'status' => true,
            'message' => __('messages.2FAgetQR'),
            'error' => '',
            'code' => 200,
            'datetime' => now(),
            'qr_code' => app('two-factor')->getQRCodeSvg(
                $user->twoFactorSecret(),
                $user->{"{$type}_login"},
                config('app.name')
            ),
        ], 200);
    }

    /**
     * Get the recovery codes.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showRecoveryCodes(Request $request, $type)
    {
        $user = $request->user();
        if (!$user->two_factor_recovery_codes) {
            return response()->json([
                'status' => false,
                'message' => __('messages.norecoveryQR'),
                'error' => 'No recovery codes',
                'code' => 400,
                'datetime' => now(),
            ], 400);
        }
        $tbl_table = DB::table('tbl_'.$type)
                        ->where($type.'_id', $user->{"{$type}_id"})
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
        if (!$tbl_table) {
            return response()->json([
                'status' => false,
                'datetime' => now(),
                'message' => __('messages.noexist'),
                'error' => __('messages.noexist'),
                'code' => 500
            ], 500);
        }
        return response()->json([
            'status' => true,
            'message' => __('messages.recoveryQRGet'),
            'error' => '',
            'code' => 200,
            'datetime' => now(),
            'recovery_codes' => $user->recoveryCodes(),
        ], 200);
    }
}
