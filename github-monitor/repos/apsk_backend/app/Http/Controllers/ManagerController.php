<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Manager;
use App\Models\Shop;
use App\Models\Member;
use App\Models\Game;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Gamelog;
use App\Models\Notifications;
use App\Models\Shoppin;
use App\Models\Shopcredit;
use App\Models\Credit;
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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ManagerController extends Controller
{
    /**
     * Login tbl_manager.
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
            $tbl_manager = Manager::where('manager_login', $request->input('login'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if (!Hash::check($request->input('password'), $tbl_manager->manager_pass)) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.unvalidation'),
                        'error' => __('messages.unvalidation'),
                    ],
                    401
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $token = issueApiTokens($tbl_manager, "manager");
            $tbl_manager->update([
                'devicekey' => $request->filled('devicekey') ? $request->input('devicekey') : null,
                'lastlogin_on' => now(),
            ]);
            LogLogin( $tbl_manager, "manager", $tbl_manager->manager_name, $request );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.login_success'),
                    'error' => "",
                    'token' => $token,
                    'data' => $tbl_manager->fresh(),
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
     * dashboard tbl_manager.
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $shopquery = Shop::where('delete', 0)
                             ->where('manager_id', $request->input('manager_id'));
            $totalshop = $shopquery->count();
            $totalshopbalance = (clone $shopquery)->sum(DB::raw('-(principal - balance)'));
            $tbl_shop = $shopquery->get();
            $shopids = $tbl_shop->pluck('shop_id')->toArray();
            $tbl_notification = Notifications::where('delete', 0)
                                            ->when(
                                                is_null($tbl_manager->agent_id),
                                                fn($q) => $q->whereNull('agent_id'),
                                                fn($q) => $q->where('agent_id', $tbl_manager->agent_id)
                                            )
                                            ->where('recipient_type', 'manager')
                                            ->whereIn('sender_id', $shopids )
                                            ->orderBy('is_read', 'asc')
                                            ->orderBy('created_on', 'desc')
                                            ->get();
            $tbl_notification->map(function ($notification) {
                $notification->title = __($notification->title);
                $notification->notification_desc = NotificationDescDetail($notification->notification_desc);
                $type = $notification->sender_type;
                $tbl_user = DB::table('tbl_'.$type)->where($type.'_id', $notification->sender_id )->first();
                $notification->sender = $tbl_user;
                return $notification;
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_manager,
                    'totalshop'=> $totalshop,
                    'totalshopbalance'=> ReverseDecimal($totalshopbalance),
                    'tbl_notification'=> $tbl_notification,
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_manager,
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
     * search member/shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
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
            'manager_id' => 'required|integer',
            'type' => 'required|string|in:all,member,shop',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
            $tbl_shop = Shop::where(function($q) use ($searchTerm) {
                    $q->where('shop_id', 'LIKE', "%$searchTerm%")
                    ->orWhere('shop_name', 'LIKE', "%$searchTerm%");
                })
                ->where('area_code', $tbl_manager->area_code)
                ->where('delete', 0)
                ->with('Areas.Countries', 'Areas.States')
                ->inRandomOrder()
                ->limit(5)
                ->get();
            $tbl_member = Member::where(function($q) use ($searchTerm) {
                    $q->where('member_id', 'LIKE', "%$searchTerm%")
                    ->orWhere('member_login', 'LIKE', "%$searchTerm%")
                    ->orWhere('member_name', 'LIKE', "%$searchTerm%")
                    ->orWhere('full_name', 'LIKE', "%$searchTerm%")
                    ->orWhere('phone', 'LIKE', "%$searchTerm%");
                })
                ->where('delete', 0)
                ->where('area_code', $tbl_manager->area_code)
                ->with('Shop', 'Areas.Countries', 'Areas.States')
                ->inRandomOrder()
                ->limit(5)
                ->get();
            $tbl_member = $tbl_member->map(function ($member) {
                $member->balance = number_format((float)$member->balance, 2, '.', '');
                $gamemember_ids = Gamemember::where('member_id', $member->member_id);
                $gamemember_ids = $gamemember_ids->pluck('gamemember_id')->toArray();
                $tbl_provider = Gamelog::where('delete', 0)
                                       ->whereIn('gamemember_id', $gamemember_ids)
                                       ->with('Gamemember.Provider')
                                       ->orderBy('startdt', 'desc')
                                       ->get()
                                       ->pluck('Gamemember.Provider')
                                       ->filter()
                                       ->unique('provider_id')
                                       ->values()
                                       ->take(3);
                $member->provider = $tbl_provider;
                return $member;
            });
            // $shopremain = 5 - $tbl_shop->count();
            // if ($shopremain > 0) {
            //     $shopids = $tbl_member->pluck('shop_id')->toArray();
            //     $tbl_shopremain = Shop::whereIn('shop_id', $shopids )
            //                           ->whereNotIn('shop_id', $tbl_shop->pluck('shop_id'))
            //                           ->where('area_code', $tbl_manager->area_code)
            //                           ->where('delete', 0)
            //                           ->with('Areas.Countries', 'Areas.States')
            //                           ->inRandomOrder()
            //                           ->limit($shopremain)
            //                           ->get();
            //     $tbl_shop = $tbl_shop->merge($tbl_shopremain)->values();
            // }
            $alldata['shop'] = [];
            $alldata['member'] = [];
            switch ( $request->input('type') ) {
                case 'all':
                    $alldata['shop'] = $tbl_shop;
                    $alldata['member'] = $tbl_member;
                    break;
                case 'member':
                    $alldata['member'] = $tbl_member;
                    break;
                case 'shop':
                    $alldata['shop'] = $tbl_shop;
                    break;
                default:
                    break;
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'shop' => $alldata['shop'],
                    'member' => $alldata['member'],
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
     * manager search phone.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchphone(Request $request)
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
            'manager_id' => 'required|integer',
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
        try{
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_member = Member::where('delete', 0)
                                ->where('phone', $request->input('search') )
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if (!Hash::check($request->input('oldpassword'), $tbl_manager->manager_pass)) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.unvalidation'),
                        'error' => __('messages.unvalidation'),
                    ],
                    401
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_manager->update([
                'manager_pass' => Hash::make($request->input('newpassword')),
                'updated_on' => now(),
            ]);
            $tbl_manager = $tbl_manager->fresh();
            LogChangePassword( $tbl_manager, "manager", $request);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.passwordchangesuccess'),
                    'error' => "",
                    'data' => $tbl_manager,
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
     * view tbl_member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewmember(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
                                ->with('Areas.Countries', 'Areas.States', 'MyVip', 'Shop')
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            if ($tbl_manager->can_view_credential !== 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __(
                            'manager.invalid_view_credential',
                            [
                                'manager_name'=>$tbl_manager->manager_name
                            ]
                        ),
                        'error' => __(
                            'manager.invalid_view_credential',
                            [
                                'manager_name'=>$tbl_manager->manager_name
                            ]
                        ),
                    ],
                    403
                );
            }
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
     * list tbl_member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function memberlist(Request $request)
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
            'manager_id' => 'required|integer',
            'search' => 'nullable|string',
            'status' => 'nullable|integer',
            'delete' => 'nullable|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('status', 1)
                            ->where('delete', 0)
                            ->where('manager_id', $request->input('manager_id'))
                            ->get();
            $shopids = $tbl_shop->pluck('shop_id')->toArray();
            $tbl_member = Member::whereIn('shop_id', $shopids )
                                ->with('Shop');
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $tbl_member->where(function ($q) use ($searchTerm) {
                    $q->where('member_login', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('member_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('full_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('wechat', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('facebook', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('telegram', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            if ($request->filled('status')) {
                $tbl_member->where('status', $request->input('status') );
            }
            if ($request->filled('delete')) {
                $tbl_member->where('delete', $request->input('delete') );
            }
            $tbl_member = $tbl_member->orderBy('created_on', 'desc')->get();
            $tbl_member = $tbl_member->map(function ($member) {
                $member->balance = number_format((float)$member->balance, 2, '.', '');
                $gamemember_ids = Gamemember::where('member_id', $member->member_id);
                $gamemember_ids = $gamemember_ids->pluck('gamemember_id')->toArray();
                $tbl_provider = Gamelog::where('delete', 0)
                                       ->whereIn('gamemember_id', $gamemember_ids)
                                       ->with('Gamemember.Provider')
                                       ->orderBy('startdt', 'desc')
                                       ->get()
                                       ->pluck('Gamemember.Provider')
                                       ->filter()
                                       ->unique('provider_id')
                                       ->values()
                                       ->take(3);
                $member->provider = $tbl_provider;
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
                    'message' => __('messages.passwordchangesuccess'),
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
            LogBlock($tbl_manager, "manager", $tbl_member->member_name, false, $request );
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
            LogBlock($tbl_manager, "manager", $tbl_member->member_name, true, $request );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('member.block_on'),
                    'error' => "",
                    'data' => $tbl_member,
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
                'recipient_id' => $request->input('manager_id'),
                'recipient_type' => 'manager',
                'title' => 'member.block_on',
                'notification_type' => "manager",
                'notification_desc' => $notification_desc,
                'agent_id' => $tbl_manager->agent_id,
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
                    'data' => $tbl_member,
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
     * Delete member.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletemember(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            $tbl_member->update([
                'status' => 0,
                'delete' => 1,
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.delete_success'),
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
     * Delete member reason.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletememberreason(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            $tbl_member->update([
                'reason' => $request->input('reason'),
                'status' => 0,
                'delete' => 1,
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.delete_success'),
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
     * edit member phone.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function editmemberphone(Request $request)
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
            'manager_id' => 'required|integer',
            'member_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
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
            $phone = $request->input('phone');

            $exists = Member::where(function ($query) use ($phone) {
                    $query->where('member_name', $phone)
                        ->orWhere('member_login', $phone)
                        ->orWhere('phone', $phone)
                        ->orWhere('whatsapp', $phone);
                })
                ->exists();
            if ($exists) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.phone_exist'),
                        'error' => __('member.phone_exist'),
                    ],
                    400
                );
            }
            $tbl_member->update([
                'member_name' => $request->input('phone'),
                'member_login' => $request->input('phone'),
                'phone' => $request->input('phone'),
                'whatsapp' => $request->input('phone'),
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.edit_success'),
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
     * add new shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function newshop(Request $request)
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
            'manager_id' => 'required|integer',
            'shop_name' => 'required|string|max:255',
            'login' => 'required|string|max:255',
            'shop_pass' => 'required|string|max:255',
            'area_code' => 'required|string|max:10',
            'principal' => 'required|numeric|gt:1.00',
            'lowestbalance' => 'nullable|numeric|gt:1.00',
            'can_deposit' => 'required|integer',
            'can_withdraw' => 'required|integer',
            'can_create' => 'required|integer',
            'can_block' => 'required|integer',
            'can_income' => 'required|integer',
            'can_view_credential' => 'required|integer',
            'no_withdrawal_fee' => 'required|integer',
            'read_clear' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_login', $request->input('login'))->exists();
            if ($tbl_shop) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('shop.exist'),
                        'error' => __('shop.exist'),
                    ],
                    400
                );
            }
            $tbl_shop = Shop::create([
                'shop_login' => $request->input('login'),
                'shop_pass' => encryptPassword( $request->input('shop_pass') ),
                'shop_name' => $request->input('shop_name'),
                'area_code' => $request->input('area_code'),
                'principal' => $request->input('principal'),
                'can_deposit' => $request->input('can_deposit'),
                'can_withdraw' => $request->input('can_withdraw'),
                'can_create' => $request->input('can_create'),
                'can_block' => $request->input('can_block'),
                'can_income' => $request->input('can_income'),
                'can_view_credential' => $request->input('can_view_credential'),
                'no_withdrawal_fee' => $request->input('no_withdrawal_fee'),
                'read_clear' => $request->input('read_clear'),
                'balance' => $request->input('principal'),
                'lowestbalance' => $request->input('principal') * 0.5,
                'lowestbalance_on' => now(),
                'manager_id' => $request->input('manager_id'),
                'agent_id' => $tbl_manager->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_manager->update([
                'balance' => $tbl_manager->balance - $request->input('principal'),
                'updated_on' => now(),
            ]);
            LogCreateAccount( $tbl_manager, "manager", $tbl_shop->shop_name, $request );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('shop.shop_added_successfully'),
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
     * view shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewshop(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->with('Areas', 'Manager')
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
            $tbl_permission = \Permissionhelper::list(
                'manager',
                $tbl_shop->shop_id,
                'shop',
            );
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_shop,
                    'totalsubmanager' => count($tbl_permission),
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
     * reveal shop password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revealshoppassword(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = DB::table('tbl_shop')->where('shop_id', $request->input('shop_id'))->first();
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
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'password' => decryptPassword( $tbl_shop->shop_pass ),
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
     * Change shop password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopchangepassword(Request $request)
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
            'manager_id' => 'required|integer',
            'shop_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
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
                'shop_pass' => encryptPassword( $request->input('newpassword') ),
                'updated_on' => now(),
            ]);
            $updatedRow = $tbl_shop->fresh();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.passwordchangesuccess'),
                    'error' => "",
                    'data' => $updatedRow,
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
     * pin shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shoppin(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shoppin = Shoppin::where('manager_id', $request->input('manager_id'))
                                    ->where('shop_id', $request->input('shop_id'))
                                    ->orderBy('created_on', 'desc')
                                    ->first();
            if ( $tbl_shoppin ) {
                $tbl_shoppin->update([
                    'created_on' => now(),
                ]);
                $tbl_shoppin = $tbl_shoppin->fresh();
            } else {
                $shoppin_id = (string) $request->input('manager_id') . "-" . (string) $request->input('shop_id');
                $newdata = [
                    'shoppin_id' => $shoppin_id,
                    'manager_id' => $request->input('manager_id'),
                    'shop_id' => $request->input('shop_id'),
                    'created_on' => now(),
                ];
                DB::table('tbl_shoppin')->insert($newdata);
                $tbl_shoppin = Shoppin::where('manager_id', $request->input('manager_id'))
                                        ->where('shop_id', $request->input('shop_id'))
                                        ->first();
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.pin_success'),
                    'error' => "",
                    'data' => $tbl_shoppin,
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
     * unpin shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopunpin(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shoppin = Shoppin::where('manager_id', $request->input('manager_id'))
                                    ->where('shop_id', $request->input('shop_id'))
                                    ->orderBy('created_on', 'desc')
                                    ->first();
            if ( !$tbl_shoppin ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('shop.no_data_found'),
                        'error' => __('shop.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_shoppin->delete();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.unpin_success'),
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
     * list shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shoplist(Request $request)
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
            'manager_id' => 'required|integer',
            'status' => 'nullable|integer',
            'delete' => 'nullable|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            // $tbl_shop = Shop::where( 'manager_id', $request->input('manager_id') );
            $tbl_shop = Shop::where( 'area_code', $tbl_manager->area_code );
            if ( $request->filled('status') ) {
                $tbl_shop = $tbl_shop->where( 'status', $request->input('status') );
            }
            if ( $request->filled('delete') ) {
                $tbl_shop = $tbl_shop->where( 'delete', $request->input('delete') );
            }
            $tbl_shop = $tbl_shop->with('Areas.Countries', 'Areas.States');
            $tbl_shop = $tbl_shop->orderBy('created_on', 'desc');
            $tbl_shop = $tbl_shop->get();
            $tbl_shoppin = Shoppin::where('manager_id', $request->input('manager_id'))
                                    ->orderBy('created_on', 'desc')
                                    ->get();
            $shoppin_ids = $tbl_shoppin->pluck('shop_id')->toArray();
            $tbl_shop = $tbl_shop->
                filter(function ($shop) use ($tbl_manager) {
                    $tbl_permission = \Permissionhelper::view(
                        $tbl_manager->manager_id,
                        'manager',
                        $shop->shop_id,
                        'shop',
                    );
                    return !empty($tbl_permission);
                })
                ->map(function ($shop) use ($shoppin_ids, $tbl_manager) {
                    $shop->isMainManager = $tbl_manager->manager_id === $shop->manager_id;
                    $shop->pinned = in_array($shop->shop_id, $shoppin_ids) ? 1 : 0;
                    $tbl_member = Member::where( 'shop_id', $shop->shop_id )
                                ->where('status', 1)
                                ->where('delete', 0)
                                ->get();
                    $tbl_gamemember = Gamemember::where( 'shop_id', $shop->shop_id )
                                ->where('status', 1)
                                ->where('delete', 0)
                                ->get();
                    $shop->totalmember = $tbl_member->count();
                    $shop->totalplayer = $tbl_gamemember->count();
                    $totalincome = (float) $shop->balance - (float) $shop->principal;
                    $shop->totalincome = ReverseDecimal($totalincome);
                    return $shop;
                });
            $tbl_shop = $tbl_shop->sortByDesc(function ($shop) {
                return [
                    $shop->pinned,
                    strtotime($shop->created_on)
                ];
            })->values();
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
     * detail list shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function detailshoplist(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->with('Areas.Countries', 'Areas.States')
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where( 'manager_id', $request->input('manager_id') )
                            ->with('Agent', 'Areas.States')
                            ->orderBy('created_on', 'desc')
                            ->get();
            $shoplist = $tbl_shop->map(function ($shop) {
                return [
                    'shop_name' => $shop->shop_name,
                    'prefix' => $shop->prefix,
                    'state_name' => optional(optional($shop->Areas)->States)->state_name,
                    'agent_name' => optional($shop->Agent)->agent_name,
                    'status' => $shop->status,
                    'lastlogin_on' => $shop->lastlogin_on,
                    'created_on' => $shop->created_on,
                    'updated_on' => $shop->updated_on,
                ];
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $shoplist,
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
     * edit shop amount.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopamount(Request $request)
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
            'manager_id' => 'required|integer',
            'shop_id' => 'required|integer',
            'principal' => 'required|numeric|gt:1.00',
            'lowestbalance' => 'nullable|numeric|gt:1.00',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            if ( $request->filled('lowestbalance') ) {
                if ( $request->input('principal') < $request->input('lowestbalance') ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('shop.principalmustgreaterlowestbalance'),
                            'error' => __('shop.principalmustgreaterlowestbalance'),
                        ],
                        401
                    );
                }
            }
            $tbl_shop->update([
                'principal' => $request->input('principal'),
                'lowestbalance' => $request->filled('lowestbalance') ? 
                                   $request->input('lowestbalance') :
                                   $request->input('principal') * 0.5,
                'lowestbalance_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_shopcredit = Shopcredit::create([
                'manager_id' => $request->input('manager_id'),
                'shop_id' => $request->input('shop_id'),
                'type' => "shopcredit.limit",
                'amount' => $request->input('principal'),
                'before_balance' => $tbl_shop->principal,
                'after_balance' => $request->input('principal'),
                'submit_on' => now(),
                'agent_id' => $tbl_manager->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('shop.principal_changed_success'),
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
     * clearaccount shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearaccount(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            $clearaccount = (float) $tbl_shop->principal - (float) $tbl_shop->balance;
            if ( $clearaccount == 0.0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_clearaccount'),
                        'error' => __('manager.no_clearaccount'),
                    ],
                    401
                );
            }
            $tbl_shopcredit = Shopcredit::create([
                'manager_id' => $request->input('manager_id'),
                'shop_id' => $request->input('shop_id'),
                'type' => "shopcredit.clear",
                'amount' => $clearaccount,
                'before_balance' => $tbl_shop->balance,
                'after_balance' => $tbl_shop->principal,
                'submit_on' => now(),
                'agent_id' => $tbl_manager->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_shop->update([
                'balance' => $tbl_shop->principal,
                'updated_on' => now(),
            ]);
            $tbl_manager->increment('balance', $clearaccount, [
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_shopcredit,
                    'status' => true,
                    'message' => __('manager.clearaccount_success'),
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
     * permission shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissionshop(Request $request)
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
            'manager_id' => 'required|integer',
            'shop_id' => 'required|integer',
            'can_deposit' => 'required|integer',
            'can_withdraw' => 'required|integer',
            'can_create' => 'required|integer',
            'can_block' => 'required|integer',
            'can_income' => 'required|integer',
            'can_view_credential' => 'required|integer',
            'no_withdrawal_fee' => 'required|integer',
            'read_clear' => 'required|integer',
            'alarm' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            $tbl_shop->update([
                'can_deposit' => $request->input('can_deposit'),
                'can_withdraw' => $request->input('can_withdraw'),
                'can_create' => $request->input('can_create'),
                'can_block' => $request->input('can_block'),
                'can_income' => $request->input('can_income'),
                'can_view_credential' => $request->input('can_view_credential'),
                'no_withdrawal_fee' => $request->input('no_withdrawal_fee'),
                'read_clear' => $request->input('read_clear'),
                'alarm' => $request->input('alarm'),
                'updated_on' => now(),
            ]);
            $updatedRow = $tbl_shop->fresh();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('shop.permission_changed_success'),
                    'error' => "",
                    'data' => $updatedRow,
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
     * open shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function openshop(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            if ($tbl_shop->delete === 1 ) {
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
                'status' => 1,
                'updated_on' => now(),
            ]);
            $updatedRow = $tbl_shop->fresh();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('shop.close_success'),
                    'error' => "",
                    'data' => $updatedRow,
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
     * close shop.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeshop(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            if ($tbl_shop->delete === 1 ) {
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
                'status' => 0,
                'updated_on' => now(),
            ]);
            $updatedRow = $tbl_shop->fresh();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('shop.close_success'),
                    'error' => "",
                    'data' => $updatedRow,
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
     * close shop reason.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeshopreason(Request $request)
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
            'manager_id' => 'required|integer',
            'shop_id' => 'required|integer',
            'reason' => 'nullable|string|max:10000', //关闭理由
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
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
            if ($tbl_shop->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shopcredit = Shopcredit::create([
                'manager_id' => $request->input('manager_id'),
                'shop_id' => $request->input('shop_id'),
                'type' => "shopcredit.userdelete",
                'amount' => $tbl_shop->balance,
                'before_balance' => $tbl_shop->balance,
                'after_balance' => 0.00,
                'submit_on' => now(),
                'agent_id' => $tbl_manager->agent_id,
                'reason' => $request->input('reason'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_manager->increment('balance', $tbl_shop->balance, [
                'updated_on' => now(),
            ]);
            $tbl_shop->update([
                'balance' => 0.00,
                'status' => 0,
                'reason' => $request->input('reason'),
                'updated_on' => now(),
            ]);
            $tbl_shop = $tbl_shop->fresh();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('shop.close_success'),
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
     * transaction list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shoptransactionlist(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->where('delete', 0)
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
                    break;
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
                $query = DB::table( $transaction['table'] )->where('agent_id', $tbl_manager->agent_id);
                $query->where('shop_id', $request->input('shop_id'))
                      ->where('delete', 0);
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
                    if ( $transaction['table'] === 'tbl_gamepoint' ) {
                        $table->player = Gamemember::where('gamemember_id', $table->gamemember_id )
                                        ->with('Game','Provider')
                                        ->first();
                        if ( $table->player ) {
                            $tbl_member = Member::where('member_id', optional($table->player)->member_id )
                                            ->first();
                            $table->phone = optional($tbl_member)->phone;
                            $table->player->balance = number_format((float)$table->player->balance, 2, '.', '');
                        }
                    }
                    if ( $transaction['table'] === 'tbl_credit' ) {
                        $tbl_member = Member::where('member_id', $table->member_id )
                                        ->first();
                        $table->phone = optional($tbl_member)->phone;
                        $isqr = $table->isqr === 1 ? "qr": "";
                        if ( is_null( $table->shop_id ) && $table->isqr === 0 ) {
                            $table->title = __('credit.'.$table->type);
                        } else {
                            $table->title = __('credit.shop'.$table->type.$isqr);
                        }
                    }
                    if ( $transaction['table'] === 'tbl_shopcredit' ) {
                        $tbl_user = User::where('user_id', $table->user_id )
                                        ->first();
                        $tbl_manager = Manager::where('manager_id', $table->manager_id )
                                              ->first();
                        $tbl_shop = Shop::where('shop_id', $table->shop_id )
                                        ->first();
                        $table->prefixadmin = optional($tbl_user)->prefix;
                        $table->prefixmanager = optional($tbl_manager)->prefix;
                        $table->prefixshop = optional($tbl_shop)->prefix;
                        $table->title = __($table->type);
                        $table->isMainManager = $tbl_manager->manager_id === $tbl_shop->manager_id;
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
     * Reset player Password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetplayerpassword(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_gamemember = Gamemember::where( 'gamemember_id', $request->input('gamemember_id') )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
            if (!$tbl_gamemember) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            $password = generatePasswordAbFormat();
            $tbl_gamemember->update([
                'pass' => encryptPassword( $password ),
                'updated_on' => now(),
            ]);
            $tbl_gamemember = $tbl_gamemember->fresh();
            $tbl_gamemember->balance = number_format((float)$tbl_gamemember->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.passwordchangesuccess'),
                    'error' => "",
                    'data' => $tbl_gamemember,
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
     * point history.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pointlist(Request $request)
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
            'manager_id' => 'required|integer',
            // 'type' => 'required|string|in:all,shop,member',
            'transaction' => 'nullable|string|in:bonus,reward,reload,withdraw',
            'status' => 'nullable|in:1,0',
            'startdate' => 'nullable|date',
            'enddate' => 'nullable|date|after_or_equal:startdate',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('status', 1)
                            ->where('delete', 0)
                            ->where('manager_id', $request->input('manager_id'))
                            ->get();
            $shopids = $tbl_shop->pluck('shop_id')->toArray();
            $tbl_member = Member::where('status', 1)
                                ->where('delete', 0)
                                ->whereIn('shop_id', $shopids )
                                ->get();
            $memberids = $tbl_member->pluck('member_id')->toArray();
            $tbl_gamemember = Gamemember::where('status', 1)
                                        ->where('delete', 0)
                                        ->whereIn('member_id', $memberids )
                                        ->get();
            $gamememberids = $tbl_gamemember->pluck('gamemember_id')->toArray();
            $tbl_gamepoint = Gamepoint::where('delete', 0)
                                      ->where(function ($q) use ($gamememberids, $shopids) {
                                          if (!empty($gamememberids)) {
                                              $q->whereIn('gamemember_id', $gamememberids);
                                          }
                                          if (!empty($shopids)) {
                                              $q->orWhereIn('shop_id', $shopids);
                                          }
                                      })
                                      ->whereIn('gamemember_id', $gamememberids )
                                      ->with('Shop', 'Gamemember', 'Gamemember.Member');
            if ($request->filled('transaction')) {
                $tbl_gamepoint->where('type', $request->input('transaction') );
            }
            if ($request->filled('status')) {
                $tbl_gamepoint->where('status', $request->input('status') );
            }
            $tbl_gamepoint = queryBetweenDateEloquent($tbl_gamepoint, $request, 'created_on');
            $tbl_gamepoint->orderBy('created_on', 'desc');
            $tbl_gamepoint = $tbl_gamepoint->get();
            $tbl_gamepoint = $tbl_gamepoint->map(function ($gamepoint) {
                $gamepoint->amount = number_format((float)$gamepoint->amount, 2, '.', '');
                $gamepoint->before_balance = number_format((float)$gamepoint->before_balance, 2, '.', '');
                $gamepoint->after_balance = number_format((float)$gamepoint->after_balance, 2, '.', '');
                return $gamepoint;
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_gamepoint,
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
     * game log history.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function gameloglist(Request $request)
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
            'manager_id' => 'required|integer',
            'startdate' => 'nullable|date',
            'enddate' => 'nullable|date|after_or_equal:startdate',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
            'shop_id' => 'nullable|integer',
            'provider_id' => 'nullable|integer',
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
        try{
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $page = $request->filled('page') ? $request->input('page') : 1;
            $limit = $request->filled('limit') ? $request->input('limit') : 20;
            $shop_ids = Shop::where('manager_id', $request->input('manager_id'));
            if ( $request->filled('shop_id') ) {
                $shop_ids = $shop_ids->where('shop_id', $request->input('shop_id'));
            }
            $shop_ids = $shop_ids->pluck('shop_id')->toArray();
            $member_ids = Member::whereIn('shop_id', $shop_ids);
            if ($request->filled('search')) {
                $searchTerm = $request->input('search');
                $member_ids->where(function ($q) use ($searchTerm) {
                    $q->where('member_id', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('member_login', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('member_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('full_name', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('wechat', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('facebook', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('telegram', 'LIKE', '%' . $searchTerm . '%');
                });
            }
            $member_ids = $member_ids->pluck('member_id')
                                     ->toArray();
            $gamemember_ids = Gamemember::whereIn('member_id', $member_ids);
            if ( $request->filled('provider_id') ) {
                $gamemember_ids = $gamemember_ids->where('provider_id', $request->input('provider_id'));
            }
            $gamemember_ids = $gamemember_ids->pluck('gamemember_id')->toArray();
            $query = Gamelog::where('delete', 0 )
                            ->whereIn('gamemember_id', $gamemember_ids)
                            ->with('Gamemember','Gamemember.Provider','Gamemember.Member');
            if ( $request->filled('startdate') || $request->filled('enddate') )
            {
                $query = queryBetweenDateEloquent($query, $request, 'created_on');
            }
            $query->orderBy('startdt', 'desc');
            $gamelogpaginated = $query->paginate(
                $limit,
                ['*'],
                'page',
                $page
            );
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
     * manager permission list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissionlist(Request $request)
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
            'manager_id' => 'required|integer',
            'shop_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->where('area_code', $tbl_manager->area_code)
                            ->where('status', 1)
                            ->where('delete', 0)
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
            $managerlist = [];
            $managerlistpaginated = null;
            $page = $request->filled('page') ? $request->input('page') : 1;
            $limit = $request->filled('limit') ? $request->input('limit') : 20;
            $managerlistpaginated = \Permissionhelper::listpaginated( 
                'manager',
                $tbl_shop->shop_id,
                'shop',
                1,1,1,
                $page, $limit 
            );
            if ( is_null($managerlistpaginated ) ) {
                return sendEncryptedJsonResponse([
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $managerlist,
                    'pagination' => $managerlistpagination,
                ], 200);
            }
            $managerlist = $managerlistpaginated->getCollection()
                ->map(function ($manager) {
                    $data = $manager->toArray();
                    if (isset($data['Manager'])) {
                        $data['SubManager'] = $data['Manager'];
                        unset($data['Manager']);
                    }
                    return $data;
                })
                ->values();
            $managerlistpagination = $managerlistpaginated ? [
                'total' => $managerlistpaginated->total(),
                'perpage' => $managerlistpaginated->perPage(),
                'currentpage' => $managerlistpaginated->currentPage(),
                'totalpages' => $managerlistpaginated->lastPage(),
                'hasnextpage' => $managerlistpaginated->hasMorePages(),
                'haspreviouspage' => $managerlistpaginated->currentPage() > 1,
            ] : null;
            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'data' => $managerlist,
                'pagination' => $managerlistpagination,
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
     * manager permission search.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissionsearch(Request $request)
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
            'manager_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                  ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->where('area_code', $tbl_manager->area_code)
                            ->where('status', 1)
                            ->where('delete', 0)
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
            $searchTerm = $request->input('search');
            $managerpermission = Manager::where(function($q) use ($searchTerm) {
                    $q->where('manager_id', 'LIKE', "%$searchTerm%")
                    ->orWhere('manager_login', 'LIKE', "%$searchTerm%")
                    ->orWhere('manager_name', 'LIKE', "%$searchTerm%");
                })
                ->where('area_code', $tbl_manager->area_code)
                ->first();
            if (!$managerpermission) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ],
                    400
                );
            }
            $maxpermissions = \Permissionhelper::list(
                'manager',
                $tbl_shop->shop_id,
                'shop',
            );
            if ( count( $maxpermissions ) >= 50 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.permission_max',['max'=>50]),
                        'error' => __('manager.permission_max',['max'=>50]),
                    ],
                    400
                );
            }
            $existpermission = \Permissionhelper::view( 
                $managerpermission->manager_id,
                'manager',
                $tbl_shop->shop_id,
                'shop',
            );
            if ($existpermission) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.permission_exist'),
                        'error' => __('manager.permission_exist'),
                    ],
                    400
                );
            }
            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'data' => $managerpermission,
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
     * manager permission add.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissionadd(Request $request)
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
            'manager_id' => 'required|integer',
            'shop_id' => 'required|integer',
            'permission_id' => 'required|integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->where('area_code', $tbl_manager->area_code)
                            ->where('status', 1)
                            ->where('delete', 0)
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
            $maxpermissions = \Permissionhelper::list(
                'manager',
                $tbl_shop->shop_id,
                'shop',
            );
            if ( count( $maxpermissions ) >= 50 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.permission_max',['max'=>50]),
                        'error' => __('manager.permission_max',['max'=>50]),
                    ],
                    400
                );
            }
            $existpermission = \Permissionhelper::view( 
                $request->input('permission_id'),
                'manager',
                $tbl_shop->shop_id,
                'shop',
            );
            if ($existpermission) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('manager.permission_exist'),
                        'error' => __('manager.permission_exist'),
                    ],
                    400
                );
            }
            $tbl_permission = \Permissionhelper::add(
                $request->input('permission_id'),
                'manager',
                $tbl_shop->shop_id,
                'shop',
            );
            $tbl_permission = $tbl_permission->load('PermissionUser','PermissionTarget');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('manager.permission_added_successfully'),
                    'error' => "",
                    'data' => $tbl_permission,
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
     * manager permission delete.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissiondelete(Request $request)
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
            'manager_id' => 'required|integer',
            'shop_id' => 'required|integer',
            'permission_ids' => 'required|array|min:1',
            'permission_ids.*' => 'integer',
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
            $tbl_manager = Manager::where('manager_id', $request->input('manager_id'))
                                  ->first();
            if (!$tbl_manager) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.noexist'),
                        'error' => __('messages.noexist'),
                    ],
                    400
                );
            }
            if ($tbl_manager->status !== 1 || $tbl_manager->delete === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))
                            ->where('area_code', $tbl_manager->area_code)
                            ->where('status', 1)
                            ->where('delete', 0)
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
            $permission_ids = $request->input('permission_ids');
            $tbl_permission = \Permissionhelper::viewmultiple( 
                $permission_ids,
                'manager',
                $tbl_shop->shop_id,
                'shop',
            );
            if ( empty($tbl_permission) ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => true,
                        'message' => __('manager.no_data_found'),
                        'error' => __('manager.no_data_found'),
                    ], 
                    400
                ); 
            }
            foreach ($tbl_permission as $key => $permission) {
                $permission->delete();
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('manager.permission_deleted_successfully'),
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
}
