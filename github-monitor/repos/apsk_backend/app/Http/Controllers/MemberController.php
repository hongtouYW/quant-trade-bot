<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Member;
use App\Models\Game;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Gameplatform;
use App\Models\Gamelog;
use App\Models\Bankaccount;
use App\Models\Score;
use App\Models\Recruit;
use App\Models\Credit;
use App\Models\Paymentgateway;
use App\Models\Notifications;
use App\Models\Agent;
use App\Models\Feedback;
use App\Models\Invitation;
use App\Models\Invitationhistory;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class MemberController extends Controller
{
    /**
     * Register.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:255',
            'password' => 'required|string|min:6|max:255',
            'member_login' => 'nullable|string|max:255',
            'agent_code' => 'nullable|string', //md5
            'shop_code' => 'nullable|string', //md5
            'invitecode' => 'nullable|string',
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
            $tbl_member = Member::where('phone', $request->input('phone'))
                ->first();
            if ($tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.phoneduplicate'),
                        'error' => __('messages.phoneduplicate'),
                    ],
                    422
                );
            }
            if ( $request->filled('member_login') ) {
                $login = Member::where('member_login', $request->input('member_login'))
                               ->first();
                if ($login) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.loginduplicate'),
                            'error' => __('messages.loginduplicate'),
                        ],
                        422
                    );
                }
            }
            $agent_id = null;
            if ( $request->filled('agent_code') ) {
                $tbl_agent = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->whereRaw('MD5(agent_code) = ?', [ $request->input('agent_code') ])
                                ->first();
                if (!$tbl_agent) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('agent.no_data_found'),
                            'error' => __('agent.no_data_found'),
                        ],
                        400
                    );
                }
                $agent_id = $tbl_agent->agent_id;
            }
            if ( $request->filled('shop_code') ) {
                $tbl_shop = Shop::where('status', 1)
                                ->where('delete', 0)
                                ->whereRaw('MD5(shop_code) = ?', [ $request->input('shop_code') ])
                                ->first();
                if (!$tbl_shop) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('shop.no_data_found'),
                            'error' => __('shop.no_data_found'),
                        ],
                        400
                    );
                }
                $agent_id = $tbl_shop->agent_id;
            }
            if ( $request->filled('invitecode') ) {
                $tbl_recruit = Recruit::where('status', 1)
                                    ->where('delete', 0)
                                    ->where('invitecode', $request->input('invitecode') )
                                    ->first();
                if ( !$tbl_recruit ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('member.referral_code_invalid'),
                            'error' => __('member.referral_code_invalid'),
                        ],
                        401
                    );
                }
                if ( is_null($agent_id) ) {
                    $tbl_member = Member::where('member_id', $tbl_recruit->member_id)
                                        ->where('status', 1)
                                        ->where('delete', 0)
                                        ->first();
                    if (!$tbl_member) {
                        return sendEncryptedJsonResponse(
                            [
                                'status' => false,
                                'message' => __('member.referral_code_invalid'),
                                'error' => __('member.referral_code_invalid'),
                            ],
                            422
                        );
                    }
                    $agent_id = $tbl_member->agent_id ?? null;
                }
            }
            $otpcode = generateOTP(
                $request->input('phone'),
                $request->input('password'),
                'member',
                'phone',
                'register',
                $agent_id,
            );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.otpsuccess'),
                    'error' => "",
                    'otpcode'=> null,
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
     * Login tbl_member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:255',
            'password' => 'required|string|min:6|max:255',
            'devicekey' => 'nullable|string|max:255',
            'ip' => 'nullable|string',
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
            $tbl_member = Member::where('phone', $request->input('phone'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    401
                );
            }
            if ( $request->input('password') !== decryptPassword( $tbl_member->member_pass ) ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.unvalidation'),
                        'error' => __('messages.unvalidation'),
                    ],
                    401
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $message = null;
            $token = null;
            $member = [];
            $otpcode = null;
            $needbinding = false;
            // $islogindevicemeta = \Prefix::islogindevicemeta($request, $tbl_member);
            $islogindevicemeta = \Prefix::islogindevicemetamultiple($request, $tbl_member);
            $israndomphonecode = \Prefix::israndomphonecode( $request->input('phone') );
            if ( !is_null($islogindevicemeta['error']) ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $islogindevicemeta['message'],
                        'error' => $islogindevicemeta['error'],
                        'otpcode'=> null,
                        'token' => $token,
                        'data' => null,
                        'needbinding' => null,
                    ],
                    400
                );
            }
            if ( $israndomphonecode ) {
                $message = __('messages.list_success');
                $token = null;
                $needbinding = true;
                $data = null;
            } else {
                if ( $islogindevicemeta['status'] ) {
                    $message = __('messages.verifysuccess');
                    $member['devicekey'] = $request->input('devicekey') ?? null;
                    $member['devicemeta'] = $request->header('X-Device-Meta') ?? null;
                    $member['ip'] = $request->filled('ip') ? $request->input('ip') : $request->ip();
                    $member['lastlogin_on'] = now();
                    $member['updated_on'] = now();
                    $tbl_member->update($member);
                    $token = issueApiTokens($tbl_member, "member");
                    LogLogin( $tbl_member, "member", $tbl_member->member_name, $request );
                    $needbinding = false;
                    $data = $tbl_member->fresh();
                } else {
                    $otpcode = generateOTP(
                        $request->input('phone'),
                        $request->input('password'),
                        'member',
                        'phone',
                        'login',
                        $tbl_member->agent_id,
                    );
                    $message = __('messages.otpsuccess');
                    $token = null;
                    $needbinding = $israndomphonecode;
                    $data = null;
                }
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => $message,
                    'error' => "",
                    'otpcode'=> null,
                    'token' => $token,
                    'data' => $data,
                    'member_id' => $tbl_member->member_id,
                    'needbinding' => $needbinding,
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
     * Verify OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|max:255',
            'password' => 'required|string|min:6|max:255',
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'verifyby' => 'required|string|in:phone,email,google',
            'module' => 'required|string|max:50|in:register,login',
            'agent_code' => 'nullable|string', //md5
            'shop_code' => 'nullable|string', //md5
            'invitecode' => 'nullable|string',
            'devicekey' => 'nullable|string|max:255',
            'ip' => 'nullable|string',
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
        try{
            if (!\Prefix::duplicaterequest($request->input('member_id'), $request)) {
                return sendEncryptedJsonResponse([
                    'status'  => false,
                    'message' => __('messages.duplicaterequest'),
                    'error'   => __('messages.duplicaterequest'),
                ], 400);
            }
            $agent_id = null;
            $shop_id = null;
            $area_code = null;
            $tbl_agent = null;
            $tbl_shop = null;
            if ( $request->filled('agent_code') ) {
                $tbl_agent = Agent::where('status', 1)
                                ->where('delete', 0)
                                ->whereRaw('MD5(agent_code) = ?', [ $request->input('agent_code') ])
                                ->first();
                if (!$tbl_agent) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('agent.no_data_found'),
                            'error' => __('agent.no_data_found'),
                        ],
                        400
                    );
                }
                $agent_id = $tbl_agent->agent_id;
            }
            if ( $request->filled('shop_code') ) {
                $tbl_shop = Shop::where('status', 1)
                                ->where('delete', 0)
                                ->whereRaw('MD5(shop_code) = ?', [ $request->input('shop_code') ])
                                ->first();
                if (!$tbl_shop) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('shop.no_data_found'),
                            'error' => __('shop.no_data_found'),
                        ],
                        400
                    );
                }
                $agent_id = $tbl_shop->agent_id;
                $shop_id = $tbl_shop->shop_id;
                $area_code = $tbl_shop->area_code;
            }
            $tbl_recruit = null;
            if ( $request->filled('invitecode') ) {
                $tbl_recruit = Recruit::where('status', 1)
                                    ->where('delete', 0)
                                    ->where('invitecode', $request->input('invitecode') )
                                    ->with('Member','Member.Agent')
                                    ->first();
                if ( !$tbl_recruit ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('member.referral_code_invalid'),
                            'error' => __('member.referral_code_invalid'),
                        ],
                        401
                    );
                }
                if ( is_null( $tbl_agent ) && is_null( $agent_id ) ) {
                    if ($tbl_recruit->Member && $tbl_recruit->Member->Agent) {
                        $tbl_agent = $tbl_recruit->Member->Agent;
                        $agent_id = $tbl_agent->agent_id;
                    }
                }
            }
            if ( $request->input('verifyby') === "phone" ) {
                $tbl_member = Member::where('phone', $request->input('login'))
                                    ->with('Areas.Countries', 'Areas.States')
                                    ->first();
            } else {
                $tbl_member = Member::where('member_login', $request->input('login'))
                                    ->with('Areas.Countries', 'Areas.States')
                                    ->first();
            }
            if ( $request->input('module') !== "register" ) {
                if (!$tbl_member) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('member.no_data_found'),
                            'error' => __('member.no_data_found'),
                        ],
                        400
                    );
                }
                $agent_id = $tbl_member->agent_id;
            }
            $verifyOTP = verifyOTP(
                $request->input('login'),
                $request->input('code'),
                "member",
                $request->input('verifyby'),
                $request->input('module'),
                $agent_id
            );
            if ( !$verifyOTP['status'] ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $verifyOTP['message'],
                        'error' => $verifyOTP['message'],
                    ],
                    400
                );
            }
            // skip OTP for devicemeta
            $metaHeader = $request->header('X-Device-Meta');
            if (!$tbl_member) {
                $tbl_member = Member::create([
                    'member_login' => $request->input('login'),
                    'member_pass' => encryptPassword( $request->input('password') ),
                    'member_name' => $request->input('login'),
                    'phone' => $request->input('login'),
                    'whatsapp' => $request->input('login'),
                    'area_code' => $area_code,
                    'shop_id' => $shop_id,
                    'agent_id' => $agent_id,
                    'devicekey' => $request->input('devicekey') ?? null,
                    'devicemeta' => $metaHeader ?? null,
                    'ip' => $request->filled('ip') ? $request->input('ip') : $request->ip(),
                    'status' => 1,
                    'delete' => 0,
                    'lastlogin_on' => now(),
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                if (!is_null($tbl_recruit)) {
                    $invitecode = Recruit::newcode();
                    $tbl_recruit = Recruit::create([
                        'member_id' => $tbl_member->member_id,
                        'title' => "newbie",
                        'upline' => $tbl_recruit->member_id,
                        'invitecode' => $invitecode,
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    $tbl_invitation = Invitation::create([
                        'invitecode' => $invitecode,
                        'member_id' => $tbl_member->member_id,
                        'agent_id' => $agent_id,
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    $tbl_invitationhistory = Invitationhistory::create([
                        'invitecode' => $request->input('invitecode'),
                        'member_id' => $tbl_member->member_id,
                        'upline' => $tbl_recruit->Member->member_id,
                        'agent_id' => $agent_id,
                        'registered_on' => now(),
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                }
                LogCreateAccount( $tbl_member, "member", $tbl_member->member_name, $request );
            } else {
                if ( $request->input('password') !== decryptPassword( $tbl_member->member_pass ) ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.unvalidation'),
                            'error' => __('messages.unvalidation'),
                        ],
                        401
                    );
                }
                if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.profileinactive'),
                            'error' => __('messages.profileinactive'),
                        ],
                        401
                    );
                }
                $member = [];
                if ( $request->filled('devicekey') ) {
                    $member['devicekey'] = $request->input('devicekey');
                }
                if ( $metaHeader ) {
                    $member['devicemeta'] = $metaHeader;
                }
                if (!empty($member)) {
                    $member['ip'] = $request->filled('ip') ? $request->input('ip') : $request->ip();
                    $member['lastlogin_on'] = now();
                    $member['updated_on'] = now();
                    $tbl_member->update($member);
                }
                LogLogin( $tbl_member, "member", $tbl_member->member_name, $request );
            }
            $tbl_member = $tbl_member->fresh();
            $token = issueApiTokens($tbl_member, "member");
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.verifysuccess'),
                    'error' => "",
                    'token' => $token,
                    'data' => $tbl_member,
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
     * bind phone random number.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bindphonerandommember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'phone' => 'required|string|max:255',
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
        try{
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $phone = $request->input('phone');
            $existphone = Member::where(function ($query) use ($phone) {
                $query->where('member_login', $phone)
                    ->orWhere('member_name', $phone)
                    ->orWhere('phone', $phone);
            })->exists();
            if ( $existphone ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.phone_exist'),
                        'error' => __('member.phone_exist'),
                    ],
                    400
                );
            }
            $otpcode = generateOTP(
                $request->input('phone'),
                decryptPassword($tbl_member->member_pass),
                'member',
                'phone',
                'bind',
                $tbl_member->agent_id,
            );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.otpsuccess'),
                    'error' => "",
                    'otpcode'=> null,
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
     * bind phone random number otp.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bindphonerandommemberOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'phone' => 'required|string|max:255',
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'devicekey' => 'nullable|string|max:255',
            'ip' => 'nullable|string',
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
        try{
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $phone = $request->input('phone');
            $existphone = Member::where(function ($query) use ($phone) {
                $query->where('member_login', $phone)
                    ->orWhere('member_name', $phone)
                    ->orWhere('phone', $phone);
            })->exists();
            if ( $existphone ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.phone_exist'),
                        'error' => __('member.phone_exist'),
                    ],
                    400
                );
            }
            $verifyOTP = verifyOTP(
                $phone,
                $request->input('code'),
                "member",
                "phone",
                'bind',
                $tbl_member->agent_id,
            );
            if ( !$verifyOTP['status'] ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $verifyOTP['message'],
                        'error' => $verifyOTP['message'],
                    ],
                    400
                );
            }
            $metaHeader = $request->header('X-Device-Meta');
            $member = [];
            $member['member_login'] = $request->input('phone');
            $member['member_name'] = $request->input('phone');
            $member['phone'] = $request->input('phone');
            $member['whatsapp'] = $request->input('phone');
            $member['bindphone'] = 1;
            if ( $request->filled('devicekey') ) {
                $member['devicekey'] = $request->input('devicekey');
            }
            if ( $metaHeader ) {
                $member['devicemeta'] = $metaHeader;
            }
            if (!empty($member)) {
                $member['ip'] = $request->filled('ip') ? $request->input('ip') : $request->ip();
                $member['lastlogin_on'] = now();
                $member['updated_on'] = now();
                $tbl_member->update($member);
            }
            $tbl_member = $tbl_member->fresh();
            $token = issueApiTokens($tbl_member, "member");
            LogLogin( $tbl_member, "member", $tbl_member->member_name, $request );
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_member,
                    'token' => $token,
                    'status' => true,
                    'message' => __('member.bind_success'),
                    'error' => ""
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
     * dashboard member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $slider = \Prefix::dashboardslider();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'slider' => $slider,
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
     * Change password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changepassword(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'oldpassword' => 'required|string|min:6|max:255',
            'newpassword' => 'required|string|min:6|max:255',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ( $request->input('oldpassword') !== decryptPassword( $tbl_member->member_pass ) ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.unvalidation'),
                        'error' => __('messages.unvalidation'),
                    ],
                    401
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('member.member_verify_successfully'),
                    'error' => "",
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
     * Change password send OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changepasswordsendOTP(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'verifyby' => 'required|string|in:phone,email',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $otpcode = "";
            $otpcode = generateOTP(
                $tbl_member->member_login,
                $tbl_member->member_pass,
                'member',
                $request->input('verifyby'),
                'changepassword',
                $tbl_member->agent_id,
                $tbl_member->email,
            );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.otpsuccess'),
                    'error' => "",
                    'otpcode'=> $otpcode,
                    'status_email' => $otpcode['status_email'],
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
     * Password OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordOTP(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'newpassword' => 'required|string|min:6|max:255',
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'verifyby' => 'required|string|in:phone,email,google',
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
        try{
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( $request->input('verifyby') === "google" )
            {
                if ( !$tbl_member->two_factor_secret )
                {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.2FAinvalid'),
                            'error' => $e->getMessage(),
                        ],
                        400
                    );
                }
                $verifyGoogle2FA = verifyGoogle2FA( $tbl_member->two_factor_secret, $request->input('code') );
                if ( !$verifyGoogle2FA ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.2FAfail'),
                            'error' => __('messages.2FAfail'),
                        ],
                        400
                    );
                }
            } else {
                $verifyOTP = verifyOTP(
                    $tbl_member->member_login,
                    $request->input('code'),
                    "member",
                    $request->input('verifyby'),
                    'changepassword',
                    $tbl_member->agent_id,
                );
            }
            if ( !$verifyOTP['status'] ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $verifyOTP['message'],
                        'error' => $verifyOTP['message'],
                    ],
                    400
                );
            }
            $tbl_member->update([
                'member_pass' => encryptPassword( $request->input('newpassword') ),
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            LogChangePassword( $tbl_member, "member", $request);
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.passwordchangesuccess'),
                    'error' => "",
                    'data' => $tbl_member,
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
     * Reset password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetpassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:255',
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
            $tbl_member = Member::where('phone', $request->input('phone'))
                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $otpcode = generateOTP(
                $tbl_member->member_login,
                $tbl_member->member_pass,
                'member',
                'phone',
                'resetpassword',
                $tbl_member->agent_id,
            );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.otpsuccess'),
                    'error' => "",
                    'otpcode'=> null,
                    'member_id'=> $tbl_member->member_id,
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
     * Reset OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'verifyby' => 'required|string|in:phone,email,google',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $verifyOTP = verifyOTP(
                $tbl_member->member_login,
                $request->input('code'),
                "member",
                $request->input('verifyby'),
                'resetpassword',
                $tbl_member->agent_id,
            );
            if ( !$verifyOTP['status'] ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $verifyOTP['message'],
                        'error' => $verifyOTP['message'],
                    ],
                    400
                );
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.passwordresetsuccess'),
                    'error' => "",
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
     * Reset New Password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetnewpassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'newpassword' => 'required|string|min:6|max:255',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_member->update([
                'member_pass' => encryptPassword( $request->input('newpassword') ),
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.passwordchangesuccess'),
                    'error' => "",
                    'data' => $tbl_member,
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
     * view profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function view(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_member = DisplayLevel($tbl_member);
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_member,
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
     * is music app.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function alarm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_member->update([
                'alarm' => $tbl_member->alarm === 0 ? 1: 0,
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            LogAlarm($tbl_member, "member", $tbl_member->member_name, true, $request );
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => $tbl_member->alarm === 1 ? __('member.alarm_on'): __('member.alarm_off'),
                    'error' => "",
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
     * new topup member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topup(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'payment_id' => 'required|integer',
            'amount' => 'required|numeric|between:1,5000',
            'device' => 'required|string|in:web,android'
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_paymentgateway = Paymentgateway::where('payment_id', $request->input('payment_id'))
                                                ->where('status', 1)
                                                ->where('delete', 0)
                                                ->first();
            if ( !$tbl_paymentgateway ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('paymentgateway.no_data_found'),
                        'error' => __('paymentgateway.no_data_found'),
                    ],
                    400
                );
            }
            if ( $tbl_paymentgateway->max_amount > 0.00 ) {
                if ( $request->input('amount') < $tbl_paymentgateway->min_amount ||
                     $request->input('amount') > $tbl_paymentgateway->max_amount ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('paymentgateway.paymentminmaxbetween',
                                [
                                    'min_amount'=>$tbl_paymentgateway->min_amount,
                                    'max_amount'=>$tbl_paymentgateway->max_amount
                                ]
                            ),
                            'error' => __('paymentgateway.paymentminmaxbetween',
                                [
                                    'min_amount'=>$tbl_paymentgateway->min_amount,
                                    'max_amount'=>$tbl_paymentgateway->max_amount
                                ]
                            ),
                        ],
                        400
                    );
                }
            }
            $tbl_credit = Credit::create([
                'member_id' => $request->input('member_id'),
                'payment_id' => $request->input('payment_id'),
                'type' => "deposit",
                'amount' => $request->input('amount'),
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $request->input('amount'),
                'submit_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 0,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            // development
            if (app()->environment('staging')) {
                $tbl_credit->update([
                    'status' => 1,
                    'updated_on' => now(),
                ]);
                $tbl_member->increment('balance', $request->input('amount'), [
                    'updated_on' => now(),
                ]);
                // VIP Score
                $tbl_score = AddScore( $tbl_member, 'deposit', $request->input('amount') );
                // Commission
                $salestarget = AddCommission( $tbl_member, $request->input('amount') );
            }
            $response = \Paymenthelper::deposit($request, $tbl_credit, $tbl_member);
            if (!$response['status']) {
                return sendEncryptedJsonResponse(
                    [
                        'status'   => false,
                        'response' => $response,
                        'message'  => $response['message'],
                        'error'    => $response['error'],
                    ],
                    $response['code']
                );
            }
            $tbl_paymentgateway = Paymentgateway::where('payment_id', $tbl_credit->payment_id)->first();
            $provider = $tbl_paymentgateway ? strtolower($tbl_paymentgateway->payment_name) : null;
            $payload = Crypt::encryptString(json_encode([
                'credit_id' => $tbl_credit->credit_id,
                'paymentUrl' => $response['url'],
                'device' => $request->input('device'),
                'provider' => $provider,
            ]));
            LogDeposit( $tbl_member, "member", $tbl_member->member_name, $request );
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'credit' => $tbl_credit,
                    'member' => $tbl_member,
                    'url' => url('/api/payment/redirect/' . $payload),
                    'status' => true,
                    'message' => __('messages.deposit_success'),
                    'error' => '',
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
     * submit withdraw member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdraw(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'amount' => 'required|numeric|gt:1.00',
            'bankaccount_id' => 'nullable|integer',
            'bank_id' => 'nullable|integer',
            'bank_account' => 'nullable|string',
            'bank_full_name' => 'nullable|string',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( $request->filled('bankaccount_id') ) {
                $tbl_bankaccount = Bankaccount::where( 'bankaccount_id', $request->input('bankaccount_id') )
                                              ->first();
                if (!$tbl_bankaccount)
                {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('bank.no_account_found'),
                            'error' => __('bank.no_account_found'),
                        ],
                        422
                    );
                }
            } else {
                if ( !$request->filled('bank_id') ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('bank.bank_id_required'),
                            'error' => __('bank.bank_id_required'),
                        ],
                        422
                    );
                }
                if ( !$request->filled('bank_account') ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('bank.bank_account_required'),
                            'error' => __('bank.bank_account_required'),
                        ],
                        422
                    );
                }
                if ( !$request->filled('bank_full_name') ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('bank.bank_full_name_required'),
                            'error' => __('bank.bank_full_name_required'),
                        ],
                        422
                    );
                }
                $tbl_bankaccount = Bankaccount::create([
                    'member_id' => $request->input('member_id'),
                    'bank_id' => $request->input('bank_id'),
                    'bank_account' => $request->input('bank_account'),
                    'bank_full_name' => $request->input('bank_full_name'),
                    'fastpay' => 0,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),

                ]);
            }
            // 充值了 没有玩游戏 抽5%
            $shop_no_withdrawal_fee = $tbl_member->shop_id ? shopNoWithdrawalFee($tbl_member->shop_id) : false;
            $has_game = hasgame( $request->input('member_id') );
            $balance = ($has_game || $shop_no_withdrawal_fee) ? $tbl_member->balance : round($tbl_member->balance * 0.95, 2);
            if ( $balance - $request->input('amount') < 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('role.member')." ".__('messages.insufficient'),
                        'error' => __('role.member')." ".__('messages.insufficient'),
                    ],
                    403
                );
            }
            $tbl_credit = Credit::create([
                'member_id' => $request->input('member_id'),
                'payment_id' => 2,
                'bankaccount_id' => $tbl_bankaccount->bankaccount_id,
                'type' => "withdraw",
                'amount' => $request->input('amount'),
                'before_balance' => $balance,
                'after_balance' => $balance - $request->input('amount'),
                'submit_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 0,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            // Notifications withdraw submit
            $notification_desc = NotificationDesc(
                'notification.withdraw_submit_desc', []
            );
            $tbl_notification = Notifications::create([
                'recipient_id' => $tbl_member->member_id,
                'recipient_type' => 'member',
                'title' => 'notification.withdraw_submit',
                'notification_type' => "member",
                'notification_desc' => $notification_desc,
                'agent_id' => $tbl_member->agent_id,
                'status' => 0,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_member->decrement('balance', $request->input('amount'), [
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            LogWithdraw( $tbl_member, "member", $tbl_member->member_name, $request );
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'credit' => $tbl_credit,
                    'member' => $tbl_member,
                    'has_game' => $has_game,
                    'status' => true,
                    'message' => __('messages.withdraw_submit'),
                    'error' => '',
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
     * QR player profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function playerqr(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'gamemember_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_player = Gamemember::with('game', 'Game.gameType')
                                ->where( 'gamemember_id', $request->input('gamemember_id') )
                                ->first();
            if (!$tbl_player) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('gamemember.player_no_found'),
                        'error' => __('gamemember.player_no_found'),
                    ],
                    400
                );
            }
            $link = url('/api/player/qr/scan/' . $request->input('gamemember_id') );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'qr' => $link,
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
     * Add Bank Account.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addbankaccount(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'bank_id' => 'required|integer',
            'bank_account' => 'required|string',
            'bank_full_name' => 'required|string',
            'fastpay' => 'required|integer|in:0,1',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_bankaccount = Bankaccount::where('bank_id', $request->input('bank_id'))
                                        ->where('bank_account', $request->input('bank_account'))
                                        ->where('delete', 0)
                                        ->first();
            if ($tbl_bankaccount) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('bank.bank_account_exist'),
                        'error' => __('bank.bank_account_exist'),
                    ],
                    400
                );

            }
            if ( $request->input('fastpay') > 0 ) {
                Bankaccount::where('member_id', $request->input('member_id'))
                           ->where('fastpay', 1)
                           ->update([
                                'fastpay' => 0,
                                'updated_on' => now(),
                           ]);
            }
            $tbl_bankaccount = Bankaccount::create([
                'member_id' => $request->input('member_id'),
                'bank_id' => $request->input('bank_id'),
                'bank_account' => $request->input('bank_account'),
                'bank_full_name' => $request->input('bank_full_name'),
                'fastpay' => $request->input('fastpay'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('bank.bank_account_added_successfully'),
                    'error' => "",
                    'data' => $tbl_bankaccount,
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
     * list Bank Account.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listbankaccount(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            // 充值了 没有玩游戏 抽5%
            $shop_no_withdrawal_fee = $tbl_member->shop_id ? shopNoWithdrawalFee($tbl_member->shop_id) : false;
            $has_game = hasgame( $request->input('member_id')) ;
            $charge = (!$shop_no_withdrawal_fee || !$has_game) ? 5 : 0;
            $tbl_bankaccount = Bankaccount::where('member_id', $request->input('member_id'))
                                        ->where('status', 1)
                                        ->where('delete', 0)
                                        ->with('Bank')
                                        ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_bankaccount,
                    'has_game' => $has_game,
                    'charge' => $charge,
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
     * fastpay Bank Account.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fastpaybankaccount(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'bankaccount_id' => 'required|integer',
            'status' => 'required|integer|in:0,1',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_bankaccount = Bankaccount::where('bankaccount_id', $request->input('bankaccount_id'))
                                        ->where('status', 1)
                                        ->where('delete', 0)
                                        ->first();
            if (!$tbl_bankaccount) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('bank.no_account_found'),
                        'error' => __('bank.no_account_found'),
                    ],
                    400
                );

            }
            if ( $request->input('status') === 1 ) {
                Bankaccount::where('member_id', $request->input('member_id'))
                    ->update([
                        'fastpay' => 0,
                        'updated_on' => now(),
                    ]);
            }
            $tbl_bankaccount->update([
                'fastpay' => $request->input('status'),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_bankaccount->fresh(),
                    'status' => true,
                    'message' => $request->input('status') > 0 ? __('bank.bank_account_fastpay_enable') :
                                __('bank.bank_account_fastpay_disable'),
                    'error' => ""
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
     * delete Bank Account.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletebankaccount(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'bankaccount_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_bankaccount = Bankaccount::where('bankaccount_id', $request->input('bankaccount_id'))
                                        ->where('status', 1)
                                        ->where('delete', 0)
                                        ->first();
            if (!$tbl_bankaccount) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('bank.no_account_found'),
                        'error' => __('bank.no_account_found'),
                    ],
                    400
                );

            }
            $tbl_bankaccount->update([
                'status' => 0,
                'delete' => 1,
            ]);
            $tbl_bankaccount = $tbl_bankaccount->fresh();
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_bankaccount,
                    'status' => true,
                    'message' => __('bank.bank_account_deleted_successfully'),
                    'error' => ""
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
     * Edit tbl_member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'member_name' => 'required|string|max:255',
            'dob' => 'required|date',
            'avatar' => 'nullable|string',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_member->update([
                'member_name' => $request->input('member_name'),
                'dob' => Carbon::parse($request->input('dob'))->startOfDay(),
                'avatar' => $request->filled('avatar') ? $request->input('avatar') : null,
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_member,
                    'status' => true,
                    'message' => __('messages.edit_success'),
                    'error' => ""
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
     * avatar tbl_member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function avatar(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if (!$tbl_member->avatar) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.avatar_noexist'),
                        'error' => __('member.avatar_noexist'),
                    ],
                    400
                );
            }
            $path = public_path('assets/img/member/' . $tbl_member->avatar);
            if (!File::exists($path)) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.file_noexist'),
                        'error' => __('messages.file_noexist'),
                        'avatar' => null,
                    ],
                    404
                );
            }
            // Read the file content
            $fileContent = file_get_contents($path);
            // Encode the content to Base64
            $avatar = base64_encode($fileContent);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'avatar' => $avatar,
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
     * avatar list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function avatarlist(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $path = public_path('assets/img/member/');
            $files = File::files($path);
            $images = [];
            foreach ($files as $file) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $content = file_get_contents($file->getPathname());
                    $base64  = base64_encode($content);

                    $images[] = [
                        'filename' => $file->getFilename(),
                        'url'      => asset('assets/img/member/' . $file->getFilename()),
                    ];
                }
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'images' => $images,
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
     * all downline tbl_member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function alldownline(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'level' => 'nullable|integer',
            'currentlevel' => 'nullable|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $level = $request->input('level') ?? null;
            $currentlevel = $request->input('currentlevel') ?? 1;
            $alldownline = AllDownline( $tbl_member, $request, $level, $currentlevel);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'alldownline' => $alldownline,
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
     * send feedback.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendfeedback(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'feedbacktype_id' => 'required|integer',
            'feedback_desc' => 'required|string|max:10000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $path = storeFile($request, 'feedback' . time(), 'photo', 'assets/img/feedback' );
            $tbl_feedback = Feedback::create([
                'feedbacktype_id' => $request->input('feedbacktype_id'),
                'member_id' => $request->input('member_id'),
                'feedback_desc' => $request->input('feedback_desc'),
                'photo' => $path,
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('feedback.added_successfully'),
                    'error' => "",
                    'data' => $tbl_feedback,
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
     * transaction list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transactionlist(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'type' => 'nullable|string|in:credit,point,history',
            'startdate' => 'nullable|date',
            'enddate' => 'nullable|date|after_or_equal:startdate',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $gamemember_ids = [];
            if (in_array($request->input('type'), ['point', 'history'])) {
                $gamemember_ids = Gamemember::where('member_id', $request->input('member_id'))
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->pluck('gamemember_id')
                    ->toArray();
                if (empty($gamemember_ids)) {
                    return sendEncryptedJsonResponse([
                        'status' => true,
                        'message' => __('messages.list_success'),
                        'error' => "",
                        'credit' => null,
                        'point' => [],
                        'history' => [],
                    ], 200);
                }
            }
            $tbl_credit = null;
            $tbl_gamepoint = null;
            $tbl_gamelog = null;
            $creditpaginated = null;
            $gamepointpaginated = null;
            $gamelogpaginated = null;
            $limit = $request->filled('limit') ? $request->input('limit') : 20;
            switch ( $request->input('type') ) {
                case "credit":
                    $query = Credit::where('member_id', $request->input('member_id'))
                                   ->with('Bankaccount','Bankaccount.Bank');
                    if ( $request->filled('startdate') || $request->filled('enddate') )
                    {
                        $query = queryBetweenDateEloquent($query, $request, 'created_on');
                    }
                    $query->orderBy('created_on', 'desc');
                    $creditpaginated = $query->paginate($limit);
                    $tbl_credit = $creditpaginated->getCollection()->map(function ($credit) {
                        $credit->invoiceno = 'INV' . str_pad($credit->credit_id, 10, '0', STR_PAD_LEFT);
                        $isqr = $credit->isqr === 1 ? "qr": "";
                        if ( is_null( $credit->shop_id ) && $credit->isqr === 0 ) {
                            $credit->title = __('credit.'.$credit->type);
                        } else {
                            $credit->title = __('credit.shop'.$credit->type.$isqr);
                        }
                        return $credit;
                    });
                    break;
                case "point":
                    $query = Gamepoint::whereIn('gamemember_id', $gamemember_ids )
                                      ->with('Gamemember','Gamemember.Provider');
                    if ( $request->filled('startdate') || $request->filled('enddate') )
                    {
                        $query = queryBetweenDateEloquent($query, $request, 'created_on');
                    }
                    $query->orderBy('created_on', 'desc');
                    $gamepointpaginated = $query->paginate($limit);
                    $tbl_gamepoint = $gamepointpaginated->getCollection()->map(function ($gamepoint) {
                        $gamepoint->title = __('gamepoint.'.$gamepoint->type);
                        $gamepoint->balance = number_format((float)$gamepoint->balance, 2, '.', '');
                        return $gamepoint;
                    });
                    break;
                case "history":
                    $query = Gamelog::whereIn('gamemember_id', $gamemember_ids )
                                    ->with('Gamemember', 'Gamemember.Provider', 'Game');
                    if ( $request->filled('startdate') || $request->filled('enddate') )
                    {
                        $query = queryBetweenDateEloquent($query, $request, 'startdt');
                    }
                    $query->orderBy('startdt', 'desc');
                    $gamelogpaginated = $query->paginate($limit);
                    $tbl_gamelog = $gamelogpaginated->getCollection()->map(function ($gamelog) {
                        $gamename = $gamelog->Game ? $gamelog->Game->game_name
                                    : $gamelog->remark;
                        $gamelog->game_name = __( $gamename );
                        $gamelog->before_balance = number_format((float)$gamelog->before_balance, 2, '.', '');
                        $gamelog->after_balance  = number_format((float)$gamelog->after_balance, 2, '.', '');
                        $gamelog->betamount      = number_format((float)$gamelog->betamount, 2, '.', '');
                        $gamelog->winloss        = number_format((float)$gamelog->winloss, 2, '.', '');
                        return $gamelog;
                    });
                    break;
                default:
                    break;
            }
            $creditpagination = $creditpaginated ? [
                'total' => $creditpaginated->total(),
                'perpage' => $creditpaginated->perPage(),
                'currentpage' => $creditpaginated->currentPage(),
                'totalpages' => $creditpaginated->lastPage(),
                'hasnextpage' => $creditpaginated->hasMorePages(),
                'haspreviouspage' => $creditpaginated->currentPage() > 1,
            ] : null;
            $pointpagination = $gamepointpaginated ? [
                'total' => $gamepointpaginated->total(),
                'perpage' => $gamepointpaginated->perPage(),
                'currentpage' => $gamepointpaginated->currentPage(),
                'totalpages' => $gamepointpaginated->lastPage(),
                'hasnextpage' => $gamepointpaginated->hasMorePages(),
                'haspreviouspage' => $gamepointpaginated->currentPage() > 1,
            ] : null;
            $historypagination = $gamelogpaginated ? [
                'total' => $gamelogpaginated->total(),
                'perpage' => $gamelogpaginated->perPage(),
                'currentpage' => $gamelogpaginated->currentPage(),
                'totalpages' => $gamelogpaginated->lastPage(),
                'hasnextpage' => $gamelogpaginated->hasMorePages(),
                'haspreviouspage' => $gamelogpaginated->currentPage() > 1,
            ] : null;
            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'credit'  => $tbl_credit,
                'creditpagination' => $creditpagination,
                'point'   => $tbl_gamepoint,
                'pointpagination' => $pointpagination,
                'history' => $tbl_gamelog,
                'historypagination' => $historypagination,
            ], 200);
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
     * bind phone.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bindphone(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'phone' => 'required|string|max:255',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $otpcode = generateOTP(
                $request->input('phone'),
                $tbl_member->member_pass,
                'member',
                'phone',
                'bind',
                $tbl_member->agent_id,
            );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.otpsuccess'),
                    'error' => "",
                    'otpcode'=> null,
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
     * bind phone OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bindphoneOTP(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
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
        try{
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $verifyOTP = verifyOTP(
                $tbl_member->member_login,
                $request->input('code'),
                "member",
                "phone",
                'bind',
                $tbl_member->agent_id,
            );
            if ( !$verifyOTP['status'] ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $verifyOTP['message'],
                        'error' => $verifyOTP['message'],
                    ],
                    400
                );
            }
            $tbl_member->update([
                'bindphone' => 1,
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_member,
                    'status' => true,
                    'message' => __('member.bind_success'),
                    'error' => ""
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
     * bind email.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bindemail(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'email' => 'required|string|max:255',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $otpcode = generateOTP(
                $tbl_member->member_login,
                $tbl_member->member_pass,
                'member',
                'email',
                'bind',
                $tbl_member->agent_id,
                $request->input('email'),
            );
            $tbl_member->update([
                'email' => $request->input('email'),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.otpsuccess'),
                    'error' => "",
                    'otpcode'=> null,
                    'status_email' => $otpcode['status_email'],
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
     * bind email OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bindemailOTP(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $verifyOTP = verifyOTP(
                $tbl_member->member_login,
                $request->input('code'),
                "member",
                "email",
                'bind',
                $tbl_member->agent_id,
            );
            if ( !$verifyOTP['status'] ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $verifyOTP['message'],
                        'error' => $verifyOTP['message'],
                    ],
                    400
                );
            }
            $tbl_member->update([
                'bindemail' => 1,
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_member,
                    'status' => true,
                    'message' => __('member.bind_success'),
                    'error' => ""
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
     * generate google 2FA.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generategoogle2FA(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $secret = $tbl_member->two_factor_secret ? decryptPassword( $tbl_member->two_factor_secret ): null;
            $data = generateGoogle2FA(
                $request->input('member_id'),
                "member",
                $secret
            );
            if ( is_null( $data['secret'] ) ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.2FAinitiatedfail'),
                        'error' => __('messages.2FAinitiatedfail'),
                    ],
                    500
                );
            }
            if (!$secret) {
                $tbl_member->update([
                    'two_factor_secret' => encryptPassword($data['secret']),
                    'updated_on' => now(),
                ]);
            }
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.2FAgetQR'),
                    'error' => "",
                    'member' => $tbl_member,
                    'data' => $data,
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
     * bind google.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bindgoogle(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'bind' => 'required|integer|in:0,1',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( !$tbl_member->two_factor_secret )
            {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.2FAinvalid'),
                        'error' => $e->getMessage(),
                    ],
                    400
                );
            }
            $verifyGoogle2FA = verifyGoogle2FA( $tbl_member->two_factor_secret, $request->input('code') );
            if ( !$verifyGoogle2FA ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.2FAfail'),
                        'error' => __('messages.2FAfail'),
                    ],
                    400
                );
            }
            $tbl_member->update([
                'bindgoogle' => $request->input('bind'),
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_member,
                    'status' => true,
                    'message' => $request->input('bind') === 1 ? __('member.bind_success') : __('member.unbind_success'),
                    'error' => ""
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
     * view score.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function score(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_score = Score::where('member_id', $member_id)->first();
            if (!$tbl_score)
            {
                return sendEncryptedJsonResponse(
                    [
                        'status' => true,
                        'message' => __('member.score_no_found'),
                        'error' => "",
                    ],
                    400
                );
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_score,
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
     * QR profile.
     *
     * @param int $member_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function memberqr(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
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
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $link = url('/api/member/qr/scan/' . $request->input('member_id') );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'qr' => $link,
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
     * QR profile scan.
     *
     * @param Request $request
     * @param int $member_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function memberqrscan(Request $request, $member_id)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make(
            [
                'member_id' => $member_id,
            ],
            [
                'member_id' => 'required|string',
            ]
        );
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
            $type = $authorizedUser->currentAccessToken()->type;
            $tbl_table = DB::table('tbl_'.$type)
                            ->where($type.'_id', $authorizedUser->currentAccessToken()->tokenable_id )
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->first();
            if (!$tbl_table) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __($type.'.no_data_found'),
                        'error' => __($type.'.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_table->status !== 1 || $tbl_table->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( in_array( $type, ['shop'] ) ) {
                if ($tbl_table->alarm === 1 ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('messages.profileinactive'),
                            'error' => __('messages.profileinactive'),
                        ],
                        401
                    );
                }
            }
            $tbl_member = Member::where( 'member_id', (int) $member_id )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_member,
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
     * QR withdraw.
     *
     * @param Request $request
     * @param int $member_id
     * @param float $amount
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawqr(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'amount' => 'required|numeric',
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
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ($tbl_member->balance < $request->input('amount') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('role.member')." ".__('messages.insufficient'),
                        'error' => __('role.member')." ".__('messages.insufficient'),
                    ],
                    403
                );
            }
            $tbl_credit = Credit::create([
                'member_id' => $request->input('member_id'),
                'payment_id' => 1,
                'type' => "withdraw",
                'isqr' => 1,
                'amount' => $request->input('amount'),
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance - $request->input('amount'),
                'submit_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 0,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $payload = Crypt::encryptString(json_encode([
                'credit_id' => $tbl_credit->credit_id,
                'member_id' => $request->input('member_id'),
                'amount' => $request->input('amount'),
            ]));
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'credit' => $tbl_credit,
                    'qr' => url('/api/member/withdraw/qr/scan/' . $payload),
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
