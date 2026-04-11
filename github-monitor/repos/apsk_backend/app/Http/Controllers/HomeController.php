<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\User;
use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    /**
     * Login tbl_user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'two_factor_code' => 'nullable|string|size:6',
        ]);
        if ($validator->fails()) {
            // Return JSON for validation errors
            return response()->json([
                'token' => null,
                'datetime' => now(),
                'status' => false,
                'message' => __('validation.custom.validation_error'),
                'error' => $validator->errors(),
                'code' => 422
            ], 422);
        }
        try {
            $tbl_table = User::where('user_login', $request->input('login'))->first();
            if (!$tbl_table) {
                // Return JSON for user not found
                return response()->json([
                    'token' => null,
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.invalid_credentials'),
                    'error' => trans('messages.user_not_found', ['attribute' => $request->input('login')]),
                    'code' => 401
                ], 401);
            }
            if (!Hash::check( trim( $request->input('password') ), $tbl_table->user_pass)) {
                // Return JSON for invalid password
                return response()->json([
                    'token' => null,
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('user.invalid_password'),
                    'error' => __('user.invalid_password'),
                    'code' => 401
                ], 401);
            }
            if ($tbl_table->status !== 1) {
                // Return JSON for inactive user
                return response()->json([
                    'token' => null,
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.account_inactive'),
                    'error' => __('messages.account_inactive'),
                    'code' => 401
                ], 401);
            }
            if ($tbl_table->delete === 1) {
                // Return JSON for deleted user
                return response()->json([
                    'token' => null,
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.account_deleted'),
                    'error' => __('messages.account_deleted'),
                    'code' => 401
                ], 401);
            }
            // --- TWO-FACTOR AUTHENTICATION CHECK ---
            if ($tbl_table->two_factor_secret && ($tbl_table->two_factor_confirmed_at || $tbl_table->GAstatus === 1)) {
                $twoFactorCode = $request->input('two_factor_code');
                if (!$twoFactorCode) {
                    // Return JSON if 2FA code is required but missing
                    return response()->json([
                        'token' => null,
                        'datetime' => now(),
                        'status' => false,
                        'message' => __('messages.2fa_required'),
                        'error' => __('messages.2fa_required'),
                        'code' => 401
                    ], 401);
                }
                try {
                    $isValid2FA = app(TwoFactorAuthenticationProvider::class)->verify(
                        decrypt($tbl_table->two_factor_secret),
                        $twoFactorCode
                    );
                    if (!$isValid2FA) {
                        // Return JSON for invalid 2FA code
                        return response()->json([
                            'token' => null,
                            'datetime' => now(),
                            'status' => false,
                            'message' => __('messages.2fa_invalid'),
                            'error' => __('messages.2fa_invalid'),
                            'code' => 401
                        ], 401);
                    }
                } catch (DecryptException $e) {
                    Log::error('2FA secret decryption failed for user ' . $tbl_table->user_login . ': ' . $e->getMessage());
                    return response()->json([
                        'token' => null,
                        'datetime' => now(),
                        'status' => false,
                        'message' => __('messages.2fa_error'),
                        'error' => __('messages.2fa_error'),
                        'code' => 500
                    ], 500);
                } catch (\Exception $e) {
                    Log::error('2FA verification failed for user ' . $tbl_table->user_login . ': ' . $e->getMessage());
                    return response()->json([
                        'token' => null,
                        'datetime' => now(),
                        'status' => false,
                        'message' => __('messages.2fa_verification_error'),
                        'error' => __('messages.2fa_verification_error'),
                        'code' => 500
                    ], 500);
                }
            }
            Auth::login($tbl_table);
            // $request->session()->regenerate(); CSRF Token mismatch
            $token = issueApiTokens($tbl_table, "user");
            LogLogin( $tbl_table, "user", $tbl_table->user_name, $request );
            return response()->json([
                'token' => $token,
                'csrf' => csrf_token(),
                'datetime' => now(),
                'status' => true,
                'message' => __('messages.login_success'),
                'error' => '',
                'code' => 200
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error during login for ' . $request->input('login') . ': ' . $e->getMessage());
            return response()->json([
                'token' => null,
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.database_error'),
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        } catch (\Exception $e) {
            Log::error('General error during login for ' . $request->input('login') . ': ' . $e->getMessage());
            return response()->json([
                'token' => null,
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * View Profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.unauthorized'),
                'error' => __('messages.unauthorized'),
                'code' => 403
            ], 403);
        }
        try {
            $tbl_table = DB::table('tbl_user')->where('user_id', $authorizedUser->currentAccessToken()->tokenable_id)->first();
            if (!$tbl_table) {
                return response()->json([
                    'data' => [],
                    'datetime' => now(),
                    'status' => false,
                    'message' => trans('messages.user_not_found', ['attribute' => $request->input('username')]),
                    'error' => trans('messages.user_not_found', ['attribute' => $request->input('username')]),
                    'code' => 500
                ], 500);
            }
            if ($tbl_table->status !== 1) {
                return response()->json([
                    'token' => null,
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.account_inactive'),
                    'error' => __('messages.account_inactive'),
                    'code' => 401
                ], 401);
            }
            if ($tbl_table->delete === 1) {
                return response()->json([
                    'token' => null,
                    'datetime' => now(),
                    'status' => false,
                    'message' => __('messages.account_deleted'),
                    'error' => __('messages.account_deleted'),
                    'code' => 401
                ], 401);
            }
            return response()->json([
                'data' => $tbl_table,
                'datetime' => now(),
                'status' => true,
                'message' => __('messages.profile_success'),
                'error' => '',
                'code' => 200
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'data' => [],
                'datetime' => now(),
                'status' => false,
                'message' => __('messages.database_error'),
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            $tbl_table = Auth::user();
            $tbl_table->tokens()->where('type', "user")->delete();
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('success', __('messages.logout_success') );
        LogLogout( $request, "user" );
        return redirect('/login');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function setTheme(Request $request)
    {
        $theme = $request->input('theme', 'light');
        if (in_array($theme, ['light', 'dark'])) {
            Auth::user()->update(['theme' => $theme]);
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 400);
    }

    public function setup()
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        $user = User::where('status', 1)
                    ->where('delete', 0)
                    ->where('user_id', $authorizedUser->user_id)
                    ->first();
        if (!$user) {
            return redirect()->back()->with('error', __('user.no_data_found'));
        }
        $agent = Agent::where('agent_id', $user->agent_id)->first();
        if (!$agent) {
            return redirect()->back()->with('error', __('agent.no_data_found'));
        }
        return view('setup', ['user' => $user, 'agent' => $agent ]);
        // return view('module.user.edit', compact('user', 'agent'));
    }

    public function setupupdate(Request $request, $id)
    {
        $authorizedUser = Auth::user();
        if (!$authorizedUser) {
            return redirect()->route('dashboard')->with('error', __('messages.Unauthorized'));
        }
        if (!CheckAuthorizedEdit($authorizedUser->user_id, GetModuleID('admin_management'))) {
            return redirect()->back()->with('error', __('messages.Unauthorized'));
        }

        $user = User::where('user_id', $id)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('messages.nodata'));
        }
        $userId = (int) $id; 
        $validator = Validator::make($request->all(), [
            'user_login' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tbl_user', 'user_login')->whereNot('user_id', $userId),
            ],
            'user_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tbl_user', 'user_name')->whereNot('user_id', $userId),
            ],
            'user_pass' => 'nullable|string|min:8|max:255', // For optional password change
            'support' => 'nullable|string', // agent customer service
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $tbl_agent = Agent::where('agent_id', $user->agent_id)->first();
        if (!$tbl_agent) {
            return redirect()->back()->with('error', __('agent.no_data_found'));
        }
        try {
            $updateData = [
                'user_login' => $request->input('user_login'),
                'user_name' => $request->input('user_name'),
                'updated_on' => now(),
            ];

            // Only update password if provided
            if ($request->filled('user_pass')) {
                $updateData['user_pass'] = Hash::make($request->input('user_pass'));
            }

            $user->update($updateData);
            $user->refresh();
            $agent = [];
            if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
                if ($tbl_agent->icon && Storage::disk('public')->exists($tbl_agent->icon)) {
                    Storage::disk('public')->delete($tbl_agent->icon);
                }
                $sanitizedName = Str::slug($tbl_agent->agent_name, '_');
                $extension = $request->file('icon')->getClientOriginalExtension();
                $filename = $sanitizedName.'_' . time() . '.' . $extension;
                $agent['icon'] = $request->file('icon')->storeAs('assets/img/agent', $filename, 'public');
            }
            if ( $request->filled('support') ) {
                $agent['support'] = $request->input('support');
            }
            if ( $request->filled('telegramsupport') ) {
                $agent['telegramsupport'] = $request->input('telegramsupport');
            }
            if ( $request->filled('whatsappsupport') ) {
                $agent['whatsappsupport'] = $request->input('whatsappsupport');
            }
            if (!empty($agent)) {
                $agent['updated_on'] = now();
                $tbl_agent->update($agent);
                $tbl_agent->refresh();
            }

            return redirect()->route('setup')->with('success', __('user.user_updated_successfully'));

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Error updating user: " . $e->getMessage());
            return redirect()->back()->with('error', __('messages.api_error'));
        }

    }
}
