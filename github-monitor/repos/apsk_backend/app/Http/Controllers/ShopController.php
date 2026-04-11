<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Shopcredit;
use App\Models\Member;
use App\Models\Game;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Feedback;
use App\Models\Credit;
use App\Models\Notifications;
use App\Models\Provider;
use App\Models\Agent;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ShopController extends Controller
{
    /**
     * Login tbl_shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string|max:255',
            'password' => 'required|string|min:6|max:255',
            'devicekey' => 'nullable|string|max:255',
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
            $tbl_shop = Shop::where('shop_login', $request->input('login'))
                            ->with('Areas.Countries', 'Areas.States')
                            ->first();
            if (!$tbl_shop) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('shop.no_data_found'),
                        'error' => __('shop.no_data_found'),
                    ],
                    401
                );
            }
            if ( $request->input('password') !== decryptPassword( $tbl_shop->shop_pass ) ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.unvalidation'),
                        'error' => __('messages.unvalidation'),
                    ],
                    401
                );
            }
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $token = issueApiTokens($tbl_shop, "shop");
            $password = generatePasswordAbFormat();
            // Special for Elaine K
            if ( $request->input('login') !=='shop001' ) {
                $tbl_shop->update([
                    'shop_pass' => encryptPassword( $password ) ,
                    'devicekey' => $request->filled('devicekey') ? $request->input('devicekey') : null,
                    'lastlogin_on' => now(),
                    'updated_on' => now(),
                ]);
            }
            LogLogin( $tbl_shop, "shop", $tbl_shop->shop_name, $request );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.login_success'),
                    'error' => "",
                    'token' => $token,
                    'data' => $tbl_shop->fresh(),
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
     * dashboard tbl_shop.
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
            'shop_id' => 'required|integer',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->with('Areas.Countries', 'Areas.States')
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    400
                );
            }
            $totalincome = $tbl_shop->can_income === 1 ? (float) $tbl_shop->balance - (float) $tbl_shop->principal : null;
            $totalmember = Gamemember::where('shop_id', $request->input('shop_id'))->count();
            $totalplayer = Member::where('shop_id', $request->input('shop_id'))->count();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_shop,
                    'totalincome' => ReverseDecimal($totalincome),
                    'totalmember' => $totalmember,
                    'totalplayer' => $totalplayer,
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
            'shop_id' => 'required|integer',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->with('Areas.Countries', 'Areas.States')
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    400
                );
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_shop,
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
            'shop_id' => 'required|integer',
            'oldpassword' => 'required|string|min:8|max:255',
            'newpassword' => 'required|string|min:8|max:255',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ( $request->input('oldpassword') !== decryptPassword( $tbl_shop->shop_pass ) ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.unvalidation'),
                        'error' => __('messages.unvalidation'),
                    ],
                    401
                );
            }
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop->update([
                'shop_pass' => encryptPassword( $request->input('newpassword') ),
                'updated_on' => now(),
            ]);
            $tbl_shop = $tbl_shop->fresh();
            LogChangePassword( $tbl_shop, "shop", $request);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.passwordchangesuccess'),
                    'error' => "",
                    'data' => $tbl_shop,
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
     * Alarm tbl_shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function alarm(Request $request)
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
            'shop_id' => 'required|integer',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop->update([
                'alarm' => 1,
                'updated_on' => now(),
            ]);
            // Notifications Alert
            $notification_desc = NotificationDesc(
                'shop.alarm_success_desc',
                [
                    'shop_name'=>$tbl_shop->shop_name,
                ]
            );
            $tbl_notification = Notifications::create([
                'sender_id' => $request->input('shop_id'),
                'sender_type' => 'shop',
                'recipient_id' => $tbl_shop->manager_id,
                'recipient_type' => 'manager',
                'title' => 'shop.alarm_success',
                'notification_type' => "alert",
                'notification_desc' => $notification_desc,
                'agent_id' => $tbl_shop->agent_id,
                'status' => 0,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            LogAlarm($tbl_shop, "shop", $tbl_shop->shop_name, true, $request );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('shop.alarm_success'),
                    'error' => "",
                    'data' => $tbl_shop,
                    'notification' => $tbl_notification,
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
     * add new member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function newmember(Request $request)
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
            'shop_id' => 'required|integer',
            'phone' => 'required|string',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->with('Agent')
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_create === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $login = Member::where('member_login', $request->input('phone'))
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
            $phone = Member::where('phone', $request->input('phone'))
                            ->first();
            if ($phone) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.phoneduplicate'),
                        'error' => __('messages.phoneduplicate'),
                    ],
                    422
                );
            }
            if ( !$tbl_shop->Agent ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('agent.no_data_found'),
                        'error' => __('agent.no_data_found'),
                    ],
                    400
                );
            }
            $password = generatePasswordPairFormat();
            // production
            if (app()->environment('production')) {
                $telesms = \Telesms::send( 
                    $request->input('phone'),
                    __(
                        'messages.revealmemberpassword', 
                        [
                            'phone' => $request->input('phone'),
                            'password' => $password,
                        ] 
                    ) 
                );
                if ( !$telesms['success'] ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => $telesms['message'],
                            'error' => $telesms['message'],
                            'data' => $tbl_member,
                            'password' => null,
                        ],
                        $telesms['code']
                    );
                }
            }
            $tbl_member = Member::create([
                'member_login' => $request->input('phone'),
                'member_pass' => encryptPassword( $password ),
                'member_name' => $request->input('phone'),
                'phone' => $request->input('phone'),
                'whatsapp' => $request->input('phone'),
                'area_code' => $tbl_shop->area_code,
                'shop_id' => $request->input('shop_id'),
                'agent_id' => $tbl_shop->Agent->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('member.member_added_successfully'),
                    'error' => "",
                    'data' => $tbl_member,
                    'password'=>$password,
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
     * Change member password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function memberchangepassword(Request $request)
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
            'shop_id' => 'required|integer',
            'search' => 'required|string', //member_id/phone
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_create === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_member = Member::where('member_id', $request->input('search'))
                                ->orWhere('phone', $request->input('search'))
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
            $password = generatePasswordPairFormat();
            $israndomphonecode = \Prefix::israndomphonecode( $tbl_member->phone );
            if ( !$israndomphonecode ) {
                // production
                if (app()->environment('production')) {
                    $telesms = \Telesms::send( 
                        $tbl_member->phone,
                        __(
                            'messages.revealmemberpassword', 
                            [
                                'phone' => $tbl_member->phone,
                                'password' => $password,
                            ] 
                        ) 
                    );
                    if ( !$telesms['success'] ) {
                        return sendEncryptedJsonResponse(
                            [
                                'status' => false,
                                'message' => $telesms['message'],
                                'error' => $telesms['message'],
                                'data' => $tbl_member,
                                'password' => null,
                            ],
                            $telesms['code']
                        );
                    }
                }
            }
            $tbl_member->update([
                'member_pass' => encryptPassword( $password ),
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.otpsuccess'),
                    'error' => "",
                    'data' => $tbl_member,
                    'password'=> $password,
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
     * new random member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function randommember(Request $request)
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
            'shop_id' => 'required|integer',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->with('Agent')
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_create === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( !$tbl_shop->Agent ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('agent.no_data_found'),
                        'error' => __('agent.no_data_found'),
                    ],
                    400
                );
            }
            $phone = UniqueMalaysiaPhoneNumber();
            $password = generatePasswordPairFormat();
            $tbl_member = Member::create([
                'member_login' => $phone,
                'member_pass' => encryptPassword( $password ),
                'member_name' => $phone,
                'area_code' => $tbl_shop->area_code,
                'phone' => $phone,
                'shop_id' => $request->input('shop_id'),
                'agent_id' => $tbl_shop->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('member.member_added_successfully'),
                    'error' => "",
                    'data' => $tbl_member,
                    'password' => $password,
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
     * search member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchmember(Request $request)
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
            'shop_id' => 'required|integer',
            'search' => 'required|string',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $searchTerm = $request->input('search');
            $tbl_member = null;
            $tbl_player = null;
            $tbl_member = Member::where(function ($query) use ($searchTerm) {
                                    $query->where('member_id', 'LIKE', '%' . $searchTerm . '%' )
                                        ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%'  );
                                })
                                ->where('delete', 0)
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if ( $tbl_member ) {
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
                if ( $tbl_member->area_code !== $tbl_shop->area_code ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __(
                                'member.invalid_area_code',
                                [
                                    'area_name'=>optional($tbl_member->Areas)->area_name
                                ]
                            ),
                            'error' => __(
                                'member.invalid_area_code',
                                [
                                    'area_name'=>optional($tbl_member->Areas)->area_name
                                ]
                            ),
                        ],
                        400
                    );
                }
                $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            }
            //include palyer id as well
            $tbl_player = Gamemember::where(function ($query) use ($searchTerm) {
                                        $query->where('gamemember_id', 'LIKE', '%' . $searchTerm . '%')
                                            ->orWhere('loginId', 'LIKE', '%' . $searchTerm . '%')
                                            ->orWhere('name', 'LIKE', '%' . $searchTerm . '%');
                                    });
            if ( !is_null($tbl_member) ) {
                $tbl_player = $tbl_player->where('member_id', $tbl_member->member_id);
            }
            $tbl_player = $tbl_player->with('Gameplatform', 'Provider', 'Game')
                                     ->first();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_member,
                    'player' => $tbl_player,
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
     * search member list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchmemberlist(Request $request)
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
            'shop_id' => 'required|integer',
            'search' => 'required|string|min:1',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $searchTerm = $request->input('search');
            $tbl_member = Member::where(function ($query) use ($searchTerm) {
                    $query->where('member_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('member_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('full_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
                })
                ->where('shop_id', $request->input('shop_id'))
                ->with('Areas.Countries', 'Areas.States')
                ->orderBy('created_on', 'desc')
                ->get();
            $tbl_member = $tbl_member->map(function ($member) {
                $member->balance = number_format((float)$member->balance, 2, '.', '');
                return $member;
            });
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
     *  detail member list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailmemberlist(Request $request)
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
            'shop_id' => 'required|integer',
            'search' => 'nullable|string',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $searchTerm = $request->filled('search') ? $request->input('search') : "";
            $tbl_member = Member::where(function ($query) use ($searchTerm) {
                    $query->where('member_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('member_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('full_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
                })
                ->where('shop_id', $request->input('shop_id'))
                ->with('Agent')
                ->orderBy('created_on', 'desc')
                ->get();
            $memberlist = $tbl_member->map(function ($member) {
                return [
                    'member_name' => $member->member_name,
                    'phone' => $member->phone,
                    'prefix' => $member->prefix,
                    'devicekey' => $member->devicekey,
                    'devicemeta' => $member->devicemeta,
                    'agent_name' => optional($member->Agent)->agent_name,
                    'status' => $member->status,
                    'lastlogin_on' => $member->lastlogin_on,
                    'created_on' => $member->created_on,
                ];
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $memberlist,
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
     * reveal member password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revealmemberpassword(Request $request)
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
            'shop_id' => 'required|integer',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if (!$tbl_shop->can_view_credential) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('shop.invalid_credential'),
                        'error' => __('shop.invalid_credential'),
                    ],
                    400
                );
            }
            $tbl_member = Member::where('member_id', $request->input('member_id'))
                                ->with('Areas.Countries', 'Areas.States')
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
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'password' => decryptPassword( $tbl_member->member_pass ),
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
     * unblock member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unblockmember(Request $request)
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
            'shop_id' => 'required|integer',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_block === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
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
            if ($tbl_member->delete === 1 ) {
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
                'status' => 1,
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            LogBlock($tbl_shop, "shop", $tbl_member->member_name, false, $request );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('member.block_off'),
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
     * block member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function blockmember(Request $request)
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
            'shop_id' => 'required|integer',
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
        // 🔐 Redis Lock (prevent duplicate execution)
        $memberlock = Cache::lock('block_member_'.$request->member_id, 60);
        if (!$memberlock->block(5)) {
            return sendEncryptedJsonResponse([
                'status' => false,
                'message' => 'Processing in progress, please wait',
            ], 429);
        }
        try {
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_block === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
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
            if ($tbl_member->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            // Gamemember close account balance
            $ip = $request->filled('ip')
                ? $request->input('ip')
                : $request->ip();
            $withdrawresponses = \Gamehelper::block( $tbl_member, $request );
            foreach ($withdrawresponses as $withdrawresponse) {
                if (empty($withdrawresponse['data'])) {
                    continue;
                }
                DB::transaction(function () use (
                    $withdrawresponse,
                    $tbl_shop,
                    $tbl_member,
                    $ip
                ) {
                    $player = $withdrawresponse['data'];
                    $gamepoint = Gamepoint::create([
                        'gamemember_id'  => $player->gamemember_id,
                        'orderid'        => $withdrawresponse['orderid'],
                        'type'           => "withdraw",
                        'ip'             => $ip,
                        'amount'         => $player->balance - $withdrawresponse['remain'],
                        'before_balance' => $player->balance,
                        'after_balance'  => $withdrawresponse['remain'],
                        'start_on'       => now(),
                        'end_on'         => now(),
                        'agent_id'       => $tbl_shop->agent_id,
                        'status'         => $withdrawresponse['status'] ? 1 : -1,
                        'delete'         => 0,
                        'created_on'     => now(),
                        'updated_on'     => now(),
                    ]);
                    if (!$withdrawresponse['status']) {
                        return;
                    }
                    $tbl_member->increment(
                        'balance', 
                        $player->balance - $withdrawresponse['remain'], 
                        [
                            'updated_on' => now(),
                        ]
                    );
                    $player->update([
                        'status' => 0,
                        'balance' => $withdrawresponse['remain'],
                        'has_balance' => 0,
                        'updated_on' => now(),
                    ]);
                });
            }
            // All Player block
            Gamemember::where('member_id', $request->input('member_id'))
                ->where('status', 1)
                ->update(['status' => 0]);
            $tbl_member->update([
                'status' => 0,
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            LogBlock($tbl_shop, "shop", $tbl_member->member_name, true, $request );
            return sendEncryptedJsonResponse(
                [
                    'data'=>$tbl_member,
                    'status' => true,
                    'message' => __('member.block_on'),
                    'error' => "",
                ],
                200
            );
        } catch (\Throwable $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        } finally {
            optional($memberlock)->release();
        }
    }

    /**
     * block member reason.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function blockmemberreason(Request $request)
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
            'shop_id' => 'required|integer',
            'member_id' => 'required|integer',
            'reason' => 'required|string|max:10000',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 ||
                $tbl_shop->alarm === 1 || $tbl_shop->can_block === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
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
            if ($tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
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
                'status' => 0,
                'reason' => $request->input('reason'),
                'updated_on' => now(),
            ]);
            // Notifications block user
            $notification_desc = NotificationDesc(
                'member.block_on_desc',
                [
                    'member_name'=>$tbl_member->member_name,
                ]
            );
            $tbl_notification = Notifications::create([
                'sender_id' => $request->input('shop_id'),
                'sender_type' => 'shop',
                'recipient_id' => $tbl_shop->manager_id,
                'recipient_type' => 'manager',
                'title' => 'member.block_on',
                'notification_type' => "shop",
                'notification_desc' => $notification_desc,
                'agent_id' => $tbl_shop->agent_id,
                'status' => 0,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('member.block_on'),
                    'error' => "",
                    'data'=>$tbl_member,
                    'notification'=>$tbl_notification,
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
            'shop_id' => 'required|integer',
            'type' => 'required|string|in:all,deposit,withdraw,clear,limit,userdelete',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $mergedCollection = new Collection();
            $transactions = [];
            $tables = [];
            $page  = $request->filled('page') ? $request->input('page') : 1;
            $limit = $request->filled('limit') ? $request->input('limit') : 20;
            switch ( $request->input('type') ) {
                case "all":
                    $transactions[0]['table'] = 'tbl_credit';
                    $transactions[1]['table'] = 'tbl_gamepoint';
                    $transactions[2]['table'] = 'tbl_shopcredit';
                    $transactions[0]['module'] = 'member';
                    $transactions[1]['module'] = 'game';
                    $transactions[2]['module'] = 'shop';
                    break;
                case "deposit":
                    $transactions[0]['table'] = 'tbl_credit';
                    $transactions[1]['table'] = 'tbl_gamepoint';
                    $transactions[0]['module'] = 'member';
                    $transactions[1]['module'] = 'game';
                    break;
                case "withdraw":
                    $transactions[0]['table'] = 'tbl_credit';
                    $transactions[1]['table'] = 'tbl_gamepoint';
                    $transactions[0]['module'] = 'member';
                    $transactions[1]['module'] = 'game';
                    break;
                case "clear":
                    $transactions[0]['table'] = 'tbl_shopcredit';
                    $transactions[0]['module'] = 'shop';
                case "limit":
                    $transactions[0]['table'] = 'tbl_shopcredit';
                    $transactions[0]['module'] = 'shop';
                case "userdelete":
                    $transactions[0]['table'] = 'tbl_shopcredit';
                    $transactions[0]['module'] = 'shop';
                    break;
                default:
                    break;
            }
            foreach ($transactions as $cnttrans => $transaction) {
                if ( (string) $transaction['table'] === "tbl_shopcredit" && $tbl_shop->read_clear !== 1 ) {
                    continue;
                }
                $query = DB::table( $transaction['table'] )->where('agent_id', $tbl_shop->agent_id);
                $query->where('shop_id', $request->input('shop_id'));
                if ( $request->input('type') !== "all" ) {
                    $mytable = (string) $transaction['table'];
                    switch ( $mytable ) {
                        case 'tbl_gamepoint':
                            $type = $request->input('type') === "deposit" ? "reload" : "withdraw";
                            break;
                        case 'tbl_shopcredit':
                            $type = "shopcredit.".$request->input('type');
                            break;
                        default:
                            $type = $request->input('type');
                            break;
                    }
                    $query->where( 'type', $type );
                }
                $tbl_table = $query->get();
                $tbl_table = $tbl_table->map(function ($table) use ($transaction) {
                    $table->transactiontype = $transaction['module'];
                    if ( $transaction['table'] === 'tbl_credit' ) {
                        $isqr = $table->isqr === 1 ? "qr": "";
                        if ( is_null( $table->shop_id ) && $table->isqr === 0 ) {
                            $table->title = __('credit.'.$table->type);
                        } else {
                            $table->title = __('credit.shop'.$table->type.$isqr);
                        }
                    }
                    if ( $transaction['table'] === 'tbl_gamepoint' ) {
                        $table->player = Gamemember::where('gamemember_id', $table->gamemember_id )
                                        ->with('Game','Provider')
                                        ->first();
                        $table->title = __('gamepoint.'.$table->type);
                    }
                    return $table;
                });
                if ( $cnttrans === 0 ) {
                    $mergedCollection = $tbl_table;
                } else {
                    $mergedCollection = $mergedCollection->merge($tbl_table);
                }
            }
            $data = collect($mergedCollection)
                ->sortByDesc('created_on')
                ->values();
            $paginated = new LengthAwarePaginator(
                $data->forPage($page, $limit),
                $data->count(),
                $limit,
                $page,
                [
                    'path'  => request()->url(),
                    'query' => request()->query(),
                ]
            );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $paginated->items(),
                    'pagination'    => [
                        'total' => $paginated->total(),
                        'perpage' => $paginated->perPage(),
                        'currentpage' => $paginated->currentPage(),
                        'totalpages' => $paginated->lastPage(),
                        'hasnextpage' => $paginated->hasMorePages(),
                        'haspreviouspage' => $paginated->currentPage() > 1,
                    ],
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
    public function membertransactionlist(Request $request)
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
            'shop_id' => 'required|integer',
            'types'   => 'required|array',
            'types.*' => 'string|in:register,deposit,withdraw,clear,userdelete',
            'search' => 'nullable|string',
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
            $shop_id = $request->input('shop_id');
            $tbl_shop = Shop::where('shop_id', $shop_id)->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $mergedCollection = new Collection();
            $transactions = [];
            $tables = [];
            $page  = $request->filled('page') ? $request->input('page') : 1;
            $limit = $request->filled('limit') ? $request->input('limit') : 20;
            $types = $request->input('types');
            $search = $request->filled('search') ? $request->input('search') : null;
            foreach ($types as $key => $type) {
                switch ( $type ) {
                    case "register":
                        Member::where('shop_id',$shop_id)
                            ->where('status',1)
                            ->where('delete',0)
                            ->orderBy('member_id')
                            ->when($search !== null && $search !== '', function($q) use ($search) {
                                $q->where(function ($q2) use ($search) {
                                    $q2->where('member_login', 'LIKE', "%{$search}%")
                                       ->orWhere('member_name', 'LIKE', "%{$search}%")
                                       ->orWhere('full_name', 'LIKE', "%{$search}%")
                                       ->orWhere('phone', 'LIKE', "%{$search}%");
                                });
                            })
                            ->chunk(200, function($rows) use (&$transactions,&$type) {
                                foreach ($rows as $member) {
                                    $title = Str::startsWith($member->phone, \Prefix::phonecode()) ? 
                                        __('member.register_random_member') : __('member.register_member');
                                    $transactiontype = Str::startsWith($member->phone, \Prefix::phonecode()) ? 
                                        'randommember' : 'member';
                                    $transactions[] = [
                                        'title' => $title,
                                        'member_id' => $member->member_id,
                                        'created_on' => $member->created_on,
                                        'transactiontype' => $transactiontype,
                                        'type' => $type,
                                        'phone' => $member->phone,
                                    ];
                                }
                            });
                        Gamemember::where('shop_id',$shop_id)
                            ->where('status',1)
                            ->where('delete',0)
                            ->with('Provider')
                            ->orderBy('gamemember_id')
                            ->when($search !== null && $search !== '', function($q) use ($search) {
                                $q->where(function ($q2) use ($search) {
                                    $q2->where(function ($q2) use ($search) {
                                        $q2->where('login', 'LIKE', "%{$search}%")
                                        ->orWhere('loginId', 'LIKE', "%{$search}%")
                                        ->orWhere('name', 'LIKE', "%{$search}%");
                                    })
                                    ->orWhereHas('Provider', function ($q3) use ($search) {
                                        $q3->where('provider_name', 'LIKE', "%{$search}%");
                                    });
                                });
                            })
                            ->chunk(200, function($rows) use (&$transactions,&$type) {
                                foreach ($rows as $gm) {
                                    $transactions[] = [
                                        'title' => __('member.register_random_player'),
                                        'member_id' => $gm->member_id,
                                        'created_on' => $gm->created_on,
                                        'transactiontype' => 'player',
                                        'type' => $type,
                                        'gamemember_id' => $gm->gamemember_id,
                                        'name' => $gm->loginId,
                                        'prefix' => $gm->name,
                                        'provider_id' => $gm->provider_id,
                                        'provider_name' => optional($gm->Provider)->provider_name,
                                    ];
                                }
                            });
                        break;
                    case "deposit":
                        Credit::where('shop_id',$shop_id)
                            ->where('delete',0)
                            ->where('type','deposit')
                            ->with('Member','Paymentgateway')
                            ->orderBy('credit_id')
                            ->when($search !== null && $search !== '', function($q) use ($search) {
                                $q->whereHas('Member', function($mq) use ($search) {
                                    $mq->where('member_name', 'LIKE', "%{$search}%")
                                    ->orWhere('full_name', 'LIKE', "%{$search}%")
                                    ->orWhere('phone', 'LIKE', "%{$search}%");
                                });
                            })
                            ->chunk(200, function($rows) use (&$transactions,&$type) {
                                foreach ($rows as $row) {
                                    $transactions[] = [
                                        'title' => __('member.deposit_member'),
                                        'member_id' => $row->member_id,
                                        'created_on' => $row->created_on,
                                        'transactiontype' => 'credit',
                                        'type' => $type,
                                        'member_name' => $row->Member?->member_name,
                                        'amount' => number_format($row->amount, 2),
                                        'status' => $row->status,
                                        'payment_id' => $row->payment_id,
                                        'payment_name' => $row->Paymentgateway?->payment_name,
                                    ];
                                }
                            });
                        Gamepoint::where('shop_id',$shop_id)
                            ->where('delete',0)
                            ->where('type','reload')
                            ->with('Gamemember','Gamemember.Provider')
                            ->orderBy('gamepoint_id')
                            ->when($search !== null && $search !== '', function($q) use ($search) {
                                $q->whereHas('Gamemember', function($gmq) use ($search) {
                                    $gmq->where(function($sq) use ($search) {
                                        $sq->where('login', 'LIKE', "%{$search}%")
                                        ->orWhere('loginId', 'LIKE', "%{$search}%")
                                        ->orWhere('name', 'LIKE', "%{$search}%");
                                    })->orWhereHas('Provider', function($pq) use ($search) {
                                        $pq->where('provider_name', 'LIKE', "%{$search}%");
                                    });
                                });
                            })
                            ->chunk(200, function($rows) use (&$transactions,&$type) {
                                foreach ($rows as $row) {
                                    $transactions[] = [
                                        'title' => __('member.deposit_player'),
                                        'gamemember_id' => $row->gamemember_id,
                                        'created_on' => $row->created_on,
                                        'transactiontype' => 'game',
                                        'type' => $type,
                                        'name' => optional($row->Gamemember)->loginId,
                                        'prefix' => optional($row->Gamemember)->name,
                                        'provider_id' => optional($row->Gamemember)->provider_id,
                                        'provider_name' => optional($row->Gamemember?->Provider)->provider_name,
                                        'amount' => number_format($row->amount, 2),
                                        'status' => $row->status,
                                    ];
                                }
                            });
                        break;
                    case "withdraw":
                        Credit::where('shop_id',$shop_id)
                            ->where('delete',0)
                            ->where('type','withdraw')
                            ->with('Member','Paymentgateway')
                            ->orderBy('credit_id')
                            ->when($search !== null && $search !== '', function($q) use ($search) {
                                $q->whereHas('Member', function($mq) use ($search) {
                                    $mq->where('member_name', 'LIKE', "%{$search}%")
                                    ->orWhere('full_name', 'LIKE', "%{$search}%")
                                    ->orWhere('phone', 'LIKE', "%{$search}%");
                                });
                            })
                            ->chunk(200, function($rows) use (&$transactions,&$type) {
                                foreach ($rows as $row) {
                                    $transactions[] = [
                                        'title' => __('member.withdraw_member'),
                                        'member_id' => $row->member_id,
                                        'created_on' => $row->created_on,
                                        'transactiontype' => 'credit',
                                        'type' => $type,
                                        'member_name' => $row->Member?->member_name,
                                        'amount' => number_format($row->amount, 2),
                                        'status' => $row->status,
                                        'payment_id' => $row->payment_id,
                                        'payment_name' => $row->Paymentgateway?->payment_name,
                                    ];
                                }
                            });
                        Gamepoint::where('shop_id',$shop_id)
                            ->where('delete',0)
                            ->where('type','withdraw')
                            ->with('Gamemember','Gamemember.Provider')
                            ->orderBy('gamepoint_id')
                            ->when($search !== null && $search !== '', function($q) use ($search) {
                                $q->whereHas('Gamemember', function($gmq) use ($search) {
                                    $gmq->where(function($sq) use ($search) {
                                        $sq->where('login', 'LIKE', "%{$search}%")
                                        ->orWhere('loginId', 'LIKE', "%{$search}%")
                                        ->orWhere('name', 'LIKE', "%{$search}%");
                                    })->orWhereHas('Provider', function($pq) use ($search) {
                                        $pq->where('provider_name', 'LIKE', "%{$search}%");
                                    });
                                });
                            })
                            ->chunk(200, function($rows) use (&$transactions,&$type) {
                                foreach ($rows as $row) {
                                    $transactions[] = [
                                        'title' => __('member.withdraw_player'),
                                        'gamemember_id' => $row->gamemember_id,
                                        'created_on' => $row->created_on,
                                        'transactiontype' => 'game',
                                        'type' => $type,
                                        'name' => optional($row->Gamemember)->loginId,
                                        'prefix' => optional($row->Gamemember)->name,
                                        'provider_id' => optional($row->Gamemember)->provider_id,
                                        'provider_name' => optional($row->Gamemember?->Provider)->provider_name,
                                        'amount' => number_format($row->amount, 2),
                                        'status' => $row->status,
                                    ];
                                }
                            });
                        break;
                    case "clear":
                        Shopcredit::where('shop_id', $shop_id)
                            ->where('delete',0)
                            ->where('type','shopcredit.clear')
                            ->with('Manager','Shop')
                            ->orderBy('shopcredit_id')
                            ->when($search !== null && $search !== '', function($q) use ($search) {
                                $q->where(function ($sq) use ($search) {
                                    $sq->whereHas('Manager', function ($mq) use ($search) {
                                        $mq->where('manager_login', 'LIKE', "%{$search}%")
                                        ->orWhere('manager_name', 'LIKE', "%{$search}%")
                                        ->orWhere('full_name', 'LIKE', "%{$search}%");
                                    })
                                    ->orWhereNull('manager_id');
                                });
                            })
                            ->chunk(200, function($rows) use (&$transactions,&$type) {
                                foreach ($rows as $row) {
                                    $shop_manager_id = optional($row->Shop)->manager_id;
                                    $isMainManager = $row->manager_id === $shop_manager_id;
                                    $transactions[] = [
                                        'title' => __('member.clear_manager'),
                                        'manager_id' => $row->manager_id,
                                        'created_on' => $row->created_on,
                                        'transactiontype' => 'shop',
                                        'type' => "shopcredit.".$type,
                                        'manager_name' => $row->Manager?->manager_name,
                                        'amount' => number_format($row->amount, 2),
                                        'isMainManager' => $isMainManager,
                                        'status' => $row->status,
                                    ];
                                }
                            });
                        break;
                    case "userdelete":
                        Shopcredit::where('shop_id', $shop_id)
                            ->where('delete',0)
                            ->where('type','shopcredit.userdelete')
                            ->with('Manager','Shop')
                            ->orderBy('shopcredit_id')
                            ->when($search !== null && $search !== '', function($q) use ($search) {
                                $q->where(function ($sq) use ($search) {
                                    $sq->whereHas('Manager', function ($mq) use ($search) {
                                        $mq->where('manager_login', 'LIKE', "%{$search}%")
                                        ->orWhere('manager_name', 'LIKE', "%{$search}%")
                                        ->orWhere('full_name', 'LIKE', "%{$search}%");
                                    })
                                    ->orWhereNull('manager_id');
                                });
                            })
                            ->chunk(200, function($rows) use (&$transactions,&$type) {
                                foreach ($rows as $row) {
                                    $shop_manager_id = optional($row->Shop)->manager_id;
                                    $isMainManager = $row->manager_id === $shop_manager_id;
                                    $transactions[] = [
                                        'title' => __('member.clear_manager'),
                                        'manager_id' => $row->manager_id,
                                        'created_on' => $row->created_on,
                                        'transactiontype' => 'shop',
                                        'type' => "shopcredit.".$type,
                                        'manager_name' => $row->Manager?->manager_name,
                                        'amount' => number_format($row->amount, 2),
                                        'isMainManager' => $isMainManager,
                                        'status' => $row->status,
                                    ];
                                }
                            });
                        break;
                    default:
                        break;
                }
            }
            $data = collect($transactions)->sortByDesc('created_on')->values();
            $paginated = new LengthAwarePaginator(
                $data->forPage($page, $limit)->values(),
                $data->count(),
                $limit,
                $page,
                [
                    'path'  => request()->url(),
                    'query' => request()->query(),
                ]
            );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $paginated->items(),
                    'pagination'    => [
                        'total' => $paginated->total(),
                        'perpage' => $paginated->perPage(),
                        'currentpage' => $paginated->currentPage(),
                        'totalpages' => $paginated->lastPage(),
                        'hasnextpage' => $paginated->hasMorePages(),
                        'haspreviouspage' => $paginated->currentPage() > 1,
                    ],
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
    public function topupmember(Request $request)
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
            'shop_id' => 'required|integer',
            'search' => 'required|string',
            'amount' => 'required|numeric|gt:1.00',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_deposit === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_member = Member::where('member_id', $request->input('search') )
                                ->orWhere('phone', $request->input('search') )
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
            if ( $tbl_member->area_code !== $tbl_shop->area_code ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __(
                            'member.invalid_area_code',
                            [
                                'area_name'=>optional($tbl_member->Areas)->area_name
                            ]
                        ),
                        'error' => __(
                            'member.invalid_area_code',
                            [
                                'area_name'=>optional($tbl_member->Areas)->area_name
                            ]
                        ),
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
            if ($tbl_shop->balance < $request->input('amount') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.insufficient'),
                        'error' => __('messages.insufficient'),
                    ],
                    403
                );
            }
            $tbl_credit = Credit::create([
                'member_id' => $tbl_member->member_id,
                'shop_id' => $request->input('shop_id'),
                'payment_id' => 1,
                'type' => "deposit",
                'amount' => $request->input('amount'),
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $request->input('amount'),
                'submit_on' => now(),
                'agent_id' => $tbl_shop->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_member->increment('balance', $request->input('amount'), [
                'updated_on' => now(),
            ]);
            $tbl_shop->decrement('balance', $request->input('amount'), [
                'updated_on' => now(),
            ]);
            // VIP Score
            $tbl_score = AddScore( $tbl_member, 'deposit', $request->input('amount') );
            // Commission
            $salestarget = AddCommission( $tbl_member, $request->input('amount') );
            LogDeposit( $tbl_shop, "shop", $tbl_member->member_name, $request );
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            $tbl_shop = $tbl_shop->fresh();
            $tbl_notification = null;
            if ( $tbl_shop->balance < $tbl_shop->lowestbalance ) {
                // Notifications but allow negative
                $notification_desc = NotificationDesc(
                    'notification.low_balance_desc',
                    [
                        'shop_name'=>$tbl_shop->shop_name,
                    ]
                );
                $tbl_notification = Notifications::create([
                    'sender_id' => $tbl_shop->shop_id,
                    'sender_type' => 'shop',
                    'recipient_id' => $tbl_shop->manager_id,
                    'recipient_type' => 'manager',
                    'title' => 'notification.low_balance',
                    'notification_type' => "shop",
                    'notification_desc' => $notification_desc,
                    'agent_id' => $tbl_shop->agent_id,
                    'status' => 0,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }
            return sendEncryptedJsonResponse(
                [
                    'shop' => $tbl_shop,
                    'member' => $tbl_member,
                    'credit' => $tbl_credit,
                    'score' => $tbl_score,
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
     * new withdraw member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawmember(Request $request)
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
            'shop_id' => 'required|integer',
            'member_id' => 'required|integer',
            'amount' => 'required|numeric|gt:1.00',
            'password' => 'required|string|min:6|max:255',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->with('Manager','Agent')
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_withdraw === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
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
            if ( $tbl_member->area_code !== $tbl_shop->area_code ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __(
                            'member.invalid_area_code',
                            [
                                'area_name'=>optional($tbl_member->Areas)->area_name
                            ]
                        ),
                        'error' => __(
                            'member.invalid_area_code',
                            [
                                'area_name'=>optional($tbl_member->Areas)->area_name
                            ]
                        ),
                    ],
                    400
                );
            }
            if ( $request->input('password') !== decryptPassword( $tbl_member->member_pass ) ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.invalid_password'),
                        'error' => __('member.invalid_password'),
                    ],
                    401
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
            if ( $tbl_member->balance - $request->input('amount') < 0 ) {
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
                'shop_id' => $request->input('shop_id'),
                'payment_id' => 1,
                'type' => "withdraw",
                'amount' => $request->input('amount'),
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance - $request->input('amount'),
                'submit_on' => now(),
                'agent_id' => $tbl_shop->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_member->decrement('balance', $request->input('amount'), [
                'updated_on' => now(),
            ]);
            $tbl_shop->increment('balance', $request->input('amount'), [
                'updated_on' => now(),
            ]);
            $tbl_shop = $tbl_shop->fresh();
            $tbl_notification = null;
            if ( $tbl_shop->balance < $tbl_shop->lowestbalance ) {
                // Notifications but allow negative
                $notification_desc = NotificationDesc(
                    'notification.low_balance_desc',
                    [
                        'shop_name'=>$tbl_shop->shop_name,
                    ]
                );
                $tbl_notification = Notifications::create([
                    'sender_id' => $request->input('shop_id'),
                    'sender_type' => 'shop',
                    'recipient_id' => $tbl_shop->manager_id,
                    'recipient_type' => 'manager',
                    'title' => 'notification.low_balance',
                    'notification_type' => "shop",
                    'notification_desc' => $notification_desc,
                    'agent_id' => $tbl_shop->agent_id,
                    'status' => 0,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }
            LogWithdraw( $tbl_shop, "shop", $tbl_member->member_name, $request );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.withdraw_success'),
                    'error' => '',
                    'data' => $tbl_credit,
                    'phone' => $tbl_member->phone,
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
     * search player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchplayer(Request $request)
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
            'shop_id' => 'required|integer',
            'provider_id' => 'required|integer',
            // 'gamemember_id' => 'required|integer',
            'search' => 'required|string',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || $tbl_shop->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_provider = Provider::where( 'provider_id', $request->input('provider_id') )
                            ->where( 'status', 1 )
                            ->where( 'delete', 0 )
                            ->first();
            if (!$tbl_provider) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('provider.no_data_found'),
                        'error' => __('provider.no_data_found'),
                        'data' => $tbl_provider,
                    ],
                    500
                );
            }
            $search = $request->input('search');
            $tbl_player = Gamemember::where('provider_id', $request->input('provider_id') )
                                    ->where( 'status', 1 )
                                    ->where( 'delete', 0 )
                                    ->where(function ($q) use ( $search ) {
                                        $q->where('gamemember_id', $search );
                                        $q->orWhere('uid', $search );
                                        $q->orWhere('loginId', $search );
                                        $q->orWhere('login', $search );
                                    })
                                    ->first();
            if (!$tbl_player) {
                return sendEncryptedJsonResponse(
                    [
                        'status'  => false,
                        'message' => __('gamemember.player_no_found'),
                        'error' => __('gamemember.player_no_found'),
                    ],
                    400
                );
            }
            $response = \Gamehelper::view( $tbl_player->gamemember_id );
            if (!$response['status']) {
                return sendEncryptedJsonResponse(
                    $response,
                    $response['code']
                );
            }
            $tbl_player = $response['data'];
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');                 
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_player,
                    'deeplink' => $response['deeplink'],
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
            'shop_id' => 'required|integer',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || $tbl_shop->alarm === 1 ) {
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
                'shop_id' => $request->input('shop_id'),
                'feedback_desc' => $request->input('feedback_desc'),
                'photo' => $path,
                'agent_id' => $tbl_shop->agent_id,
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
     * QR withdraw scan.
     *
     * @param Request $request
     * @param string $payload
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawqrscan(Request $request, $payload)
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
        try {
            $shop_id = $authorizedUser->currentAccessToken()->tokenable_id;
            $tbl_shop = Shop::where('shop_id', $shop_id )->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_withdraw === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $data = json_decode(Crypt::decryptString($payload), true);
            $credit_id = $data['credit_id'];
            $member_id = $data['member_id'];
            $amount = (float) $data['amount'];
            $tbl_credit = Credit::where( 'credit_id', $credit_id )->first();
            if (!$tbl_credit) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.no_data_found'),
                        'error' => __('credit.no_data_found'),
                    ],
                    401
                );
            }
            if ( $tbl_credit->status !== 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.inactive'),
                        'error' => __('credit.inactive'),
                    ],
                    401
                );
            }
            if ( $tbl_credit->delete !== 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.delete_credit'),
                        'error' => __('credit.delete_credit'),
                    ],
                    401
                );
            }
            $tbl_member = Member::where( 'member_id', $member_id )->first();
            if (!$tbl_member) {
                $tbl_credit->update([
                    'shop_id'=> $shop_id,
                    'reason' => 'member.no_data_found',
                    'status' => -1,
                    'updated_on' => now(),
                ]);
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
                $tbl_credit->update([
                    'shop_id'=> $shop_id,
                    'reason' => 'member.inactive',
                    'status' => -1,
                    'updated_on' => now(),
                ]);
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ($tbl_member->balance < $amount ) {
                $tbl_credit->update([
                    'shop_id'=> $shop_id,
                    'reason' => 'messages.insufficient',
                    'status' => -1,
                    'updated_on' => now(),
                ]);
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('role.member')." ".__('messages.insufficient'),
                        'error' => __('role.member')." ".__('messages.insufficient'),
                    ],
                    403
                );
            }
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'credit' => $tbl_credit,
                    'member' => $tbl_member,
                    'shop' => $tbl_shop,
                    'status' => true,
                    'message' => __('credit.verified'),
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
     * QR withdraw scan password.
     *
     * @param Request $request
     * @param string $payload
     * @param string $password
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawqrscanpassword(Request $request)
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
            'shop_id' => 'required|integer',
            'credit_id' => 'required|integer',
            'member_id' => 'required|integer',
            'password' => 'required|string',
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
            $shop_id = $authorizedUser->currentAccessToken()->tokenable_id;
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id') )->first();
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_withdraw === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_credit = Credit::where( 'credit_id', $request->input('credit_id') )->first();
            if (!$tbl_credit) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.no_data_found'),
                        'error' => __('credit.no_data_found'),
                    ],
                    401
                );
            }
            if ( $tbl_credit->status !== 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.inactive'),
                        'error' => __('credit.inactive'),
                    ],
                    401
                );
            }
            if ( $tbl_credit->delete !== 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.delete_credit'),
                        'error' => __('credit.delete_credit'),
                    ],
                    401
                );
            }
            $created_on = Carbon::parse($tbl_credit->created_on);
            if ($created_on->diffInMinutes(now()) > 3) {
                $tbl_credit->update([
                    'shop_id'=> $shop_id,
                    'reason' => 'credit.expire',
                    'status' => -1,
                    'updated_on' => now(),
                ]);
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('credit.expire'),
                        'error' => __('credit.expire'),
                    ],
                    401
                );
            }
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                $tbl_credit->update([
                    'shop_id'=> $shop_id,
                    'reason' => 'member.no_data_found',
                    'status' => -1,
                    'updated_on' => now(),
                ]);
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ( $request->input('password') !== decryptPassword( $tbl_member->member_pass ) ) {
                $tbl_credit->update([
                    'shop_id'=> $shop_id,
                    'reason' => 'messages.unvalidation',
                    'status' => -1,
                    'updated_on' => now(),
                ]);
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.unvalidation'),
                        'error' => __('messages.unvalidation'),
                    ],
                    401
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                $tbl_credit->update([
                    'shop_id'=> $shop_id,
                    'reason' => 'member.inactive',
                    'status' => -1,
                    'updated_on' => now(),
                ]);
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ($tbl_member->balance < $tbl_credit->amount ) {
                $tbl_credit->update([
                    'shop_id'=> $shop_id,
                    'reason' => 'messages.insufficient',
                    'status' => -1,
                    'updated_on' => now(),
                ]);
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('role.member')." ".__('messages.insufficient'),
                        'error' => __('role.member')." ".__('messages.insufficient'),
                    ],
                    403
                );
            }
            $tbl_credit->update([
                'shop_id'=> $shop_id,
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance - $tbl_credit->amount,
                'status' => 1,
                'updated_on' => now(),
            ]);
            $tbl_member->decrement('balance', $tbl_credit->amount, [
                'updated_on' => now(),
            ]);
            $tbl_shop->increment('balance', $tbl_credit->amount, [
                'updated_on' => now(),
            ]);
            $tbl_shop = $tbl_shop->fresh();
            $tbl_notification = null;
            if ( $tbl_shop->balance < $tbl_shop->lowestbalance ) {
                // Notifications but allow negative
                $notification_desc = NotificationDesc(
                    'notification.low_balance_desc',
                    [
                        'shop_name'=>$tbl_shop->shop_name,
                    ]
                );
                $tbl_notification = Notifications::create([
                    'sender_id' => $shop_id,
                    'sender_type' => 'shop',
                    'recipient_id' => $tbl_shop->manager_id,
                    'recipient_type' => 'manager',
                    'title' => 'notification.low_balance',
                    'notification_type' => "shop",
                    'notification_desc' => $notification_desc,
                    'agent_id' => $tbl_shop->agent_id,
                    'status' => 0,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }
            LogWithdraw( $tbl_shop, "shop", $tbl_member->member_name, $request );
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.withdraw_success'),
                    'error' => '',
                    'credit' => $tbl_credit,
                    'member' => $tbl_member,
                    'shop' => $tbl_shop,
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
     * Edit tbl_shop.
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
            'shop_id' => 'required|integer',
            'login' => 'required|string|max:255',
            'shop_name' => 'required|string|max:255',
            'area_code' => 'nullable|string|max:10',
            'principal' => 'required|numeric',
            'status' => 'nullable|in:1,0',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_withdraw === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ( $request->input('principal') < $tbl_shop->lowestbalance ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('shop.principalmustgreaterlowestbalance'),
                        'error' => __('shop.principalmustgreaterlowestbalance'),
                    ],
                    401
                );
            }
            $tbl_shop->update([
                'shop_login' => $request->input('login'),
                'shop_name' => $request->input('shop_name'),
                'area_code' => $request->input('area_code') ?? null,
                'principal' => $request->input('principal') ?? 0.00,
                'status' => $request->input('status'),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_shop->fresh(),
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
     * Balance remain % tbl_shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function balance(Request $request)
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
            'shop_id' => 'required|integer',
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
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->with('Manager')
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
            if ($tbl_shop->status !== 1 || $tbl_shop->delete === 1 || 
                $tbl_shop->alarm === 1 || $tbl_shop->can_withdraw === 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $message = $tbl_shop->balance > $tbl_shop->lowestbalance ?
                       "shop.shopbalancegreater": "shop.shopbalance";
            if ( $message === "shop.shopbalance" ) {
                $notification_desc = NotificationDesc(
                    $message,
                    [
                        'shop_name'=>$tbl_shop->shop_name,
                        'amount'=> $tbl_shop->lowestbalance * 100,
                    ]
                );
                $tbl_notification = Notifications::create([
                    'sender_id' => $request->input('shop_id'),
                    'sender_type' => 'shop',
                    'recipient_id' => $tbl_shop->manager_id,
                    'recipient_type' => 'manager',
                    'title' => 'notification.low_balance',
                    'notification_type' => "shop",
                    'notification_desc' => $notification_desc,
                    'agent_id' => $tbl_shop->agent_id,
                    'status' => 0,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __(
                        $message,
                        [
                            'shop_name'=>$tbl_shop->shop_name,
                            'amount'=> $tbl_shop->lowestbalance * 100,
                        ]
                    ),           
                    'error' => "",
                    'data' => $tbl_shop,
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
