<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Gameerror;
use App\Models\Gametype;
use App\Models\Gamebookmark;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Gamelog;
use App\Models\Gameplatform;
use App\Models\Member;
use App\Models\Shop;
use App\Models\Provider;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class GamememberController extends Controller
{
    /**
     * QR player scan.
     *
     * @param Request $request
     * @param string $type
     * @param string $gamemember_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function playerqrscan(Request $request, $gamemember_id)
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
                'gamemember_id' => $gamemember_id,
            ],
            [
                'gamemember_id' => 'required|string',
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
            $tbl_player = Gamemember::with('game', 'Game.gameType')
                ->where('gamemember_id', $gamemember_id)
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
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_player,
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
     * reveal player password.
     *
     * @param Request $request
     * @param string $type
     * @param string $gamemember_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function revealplayerpassword(Request $request, string $type)
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
            'user_id' => 'required|integer',
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
            $checkuser = CheckAvailabilityUser( $request->input('user_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_player = Gamemember::where( 'gamemember_id', $request->input('gamemember_id') )
                ->with('Member', 'Game', 'Shop')
                ->first();
            if (!$tbl_player) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('gamemember.player_no_found'),
                        'error' => __('gamemember.player_no_found'),
                        'data' => [],
                    ],
                    500
                );
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'password' => decryptPassword( $tbl_player->pass ),
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
     * add player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addplayer(Request $request, string $type)
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'nullable|integer',
            'provider_id' => 'required|integer',
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
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            if ( $type === "shop" ) {
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
                $phone = UniqueMalaysiaPhoneNumber();
                $member_login = $phone;
                $member_pass = generatePasswordAbPairFormat();
                $tbl_member = Member::create([
                    'member_login' => $member_login,
                    'member_pass' => encryptPassword( $member_pass ),
                    'member_name' => $member_login,
                    'area_code' => $tbl_shop->area_code,
                    'phone' => $phone,
                    'shop_id' => $request->input('shop_id'),
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            } else {
                if ( !$request->filled('member_id') ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => 'member_id'.__('messages.unvalidation'),
                            'error' => 'member_id'.__('messages.unvalidation'),
                        ],
                        422
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
            }
            $tbl_provider = Provider::where( 'provider_id', $request->input('provider_id') )
                ->where( 'status', 1 )
                ->where( 'delete', 0 )
                ->with('Gameplatform')
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
            $maxplayer = Gamemember::where('provider_id', $request->input('provider_id') )
                ->where('member_id', $tbl_member->member_id )
                ->where('status', 1)
                ->where('delete', 0)
                ->with('Member','Gameplatform')
                ->first();
            if ( $maxplayer ) {
                $maxplayer->balance = number_format((float)$maxplayer->balance, 2, '.', '');
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('gamemember.max_platform'),
                        'error' => __('gamemember.max_platform'),
                        'data' => $maxplayer,
                    ],
                    400
                );
            }
            // 一天之内只能创建一次
            if ( $type === "member" ) {
                $ex_player = Gamemember::where('member_id', $request->input('member_id'))
                    ->where('provider_id', $request->input('provider_id') )
                    ->whereBetween('created_on', [
                        Carbon::today()->startOfDay(),
                        Carbon::today()->endOfDay()
                    ])
                    ->orderBy('created_on', 'desc')
                    ->first();
                if ( $ex_player ) {
                    $ex_player->balance = number_format((float)$ex_player->balance, 2, '.', '');
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('gamemember.max_player_per_day'),
                            'error' => __('gamemember.max_player_per_day'),
                            'data' => $ex_player,
                        ],
                        400
                    );
                }
            }
            $player_name = generatePlayer();
            $response = \Gamehelper::create($tbl_provider, $player_name);
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
            $tbl_gamemember = Gamemember::create([
                'member_id' => $tbl_member->member_id,
                'gameplatform_id' => $tbl_provider->gameplatform_id,
                'provider_id' => $request->input('provider_id'),
                'uid' => $response['uid'],
                'loginId' => $response['loginId'],
                'login' => $response['password'] ? $response['loginId'] : null,
                'pass' => $response['password'] ? encryptPassword( $response['password'] ) : null,
                'name' => $player_name,
                'paymentpin' => $response['paymentpin'],
                'shop_id' => $type === "shop" ? $request->input('shop_id') : null,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_gamemember->load('Member');
            LogCreatePlayerAccount( $tbl_member, "member", $tbl_gamemember->name, $request );
            $tbl_gamemember->balance = number_format((float)$tbl_gamemember->balance, 2, '.', '');
            if ( $type === "shop" ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => true,
                        'message' => __('messages.add_success'),
                        'error' => "",
                        'data' => $tbl_gamemember,
                        'member_id' => $tbl_member->member_id,
                        'member_login' => $tbl_member->member_login,
                        'member_pass' => decryptPassword( $tbl_member->member_pass ),
                        'player_login' => $response['loginId'],
                        'player_pass' => $response['password'],
                    ],
                    201
                );
            } else {
                return sendEncryptedJsonResponse(
                    [
                        'status' => true,
                        'message' => __('messages.add_success'),
                        'error' => "",
                        'data' => $tbl_gamemember,
                        'player_login' => $response['loginId'],
                        'player_pass' => $response['password'],
                    ],
                    201
                );
            }
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
     * view player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewplayer(Request $request, string $type)
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'nullable|integer',
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
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $response = \Gamehelper::view( $request->input('gamemember_id') );
            return sendEncryptedJsonResponse(
                $response,
                $response['code']
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
     * login player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginplayer(Request $request)
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
            'game_id' => 'nullable|integer',
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
            if (!\Gamehelper::duplicaterequest( $request->input('gamemember_id'), $request )) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('messages.duplicaterequest'),
                    'error' => __('messages.duplicaterequest'),
                ], 400);
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
            $tbl_player = Gamemember::where('gamemember_id', $request->input('gamemember_id') )
                ->where('status', 1)
                ->where('delete', 0)
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
            $tbl_game = null;
            if ( $request->filled('game_id') ) {
                $tbl_game = Game::where( 'game_id', $request->input('game_id') )
                    ->where( 'status', 1 )
                    ->where( 'delete', 0 )
                    ->first();
                if (!$tbl_game) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('game.no_data_found'),
                            'error' => __('game.no_data_found'),
                        ],
                        400
                    );
                }
            }
            $response = \Gamehelper::login($request, $tbl_player, $tbl_game);
            if ($response['status']) {
                $tbl_player->update([
                    'lastlogin_on' => now(), 
                ]);
                $tbl_player = $tbl_player->fresh();
            }
            return sendEncryptedJsonResponse(
                $response,
                $response['code']
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

    public function loginplayerv2(Request $request)
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
            'game_id' => 'nullable|integer',
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
            if (!\Gamehelper::duplicaterequest( $request->input('gamemember_id'), $request )) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('messages.duplicaterequest'),
                    'error' => __('messages.duplicaterequest'),
                ], 400);
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
            $tbl_player = Gamemember::where('gamemember_id', $request->input('gamemember_id') )
                ->where('status', 1)
                ->where('delete', 0)
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
            $tbl_game = null;
            if ( $request->filled('game_id') ) {
                $tbl_game = Game::where( 'game_id', $request->input('game_id') )
                    ->where( 'status', 1 )
                    ->where( 'delete', 0 )
                    ->first();
                if (!$tbl_game) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('game.no_data_found'),
                            'error' => __('game.no_data_found'),
                        ],
                        400
                    );
                }
            }
            if ($tbl_member->balance > 0) {
                try {
                    DB::transaction(function () use ($request, &$tbl_member, $tbl_player) {
                        $tbl_member = Member::where('member_id', $tbl_member->member_id)
                            ->lockForUpdate()
                            ->first();

                        if ($tbl_member->balance <= 0) {
                            return;
                        }
                        $amount = (float) $tbl_member->balance;
                        $amount = floor($amount * 100) / 100;
                        $ip = $request->ip();
                        $depositResponse = \Gamehelper::deposit(
                            $tbl_player->gamemember_id,
                            $amount,
                            $ip
                        );
                        if (!$depositResponse['status']) {
                            return;
                        }
                        $transferAmount = $amount - $depositResponse['remain'];
                        $transferAmount = floor($transferAmount * 100) / 100;
                        Gamepoint::create([
                            'gamemember_id' => $tbl_player->gamemember_id,
                            'shop_id' => null,
                            'orderid' => $depositResponse['orderid'],
                            'type' => "reload",
                            'ip' => $ip,
                            'amount' => $transferAmount,
                            'before_balance' => $tbl_player->balance,
                            'after_balance' => $tbl_player->balance + $transferAmount,
                            'start_on' => now(),
                            'end_on' => now(),
                            'agent_id' => $tbl_member->agent_id,
                            'status' => 1,
                            'delete' => 0,
                            'created_on' => now(),
                            'updated_on' => now(),
                        ]);
                        $tbl_player->increment('balance', $transferAmount, [
                            'has_balance' => 1,
                            'updated_on' => now(),
                        ]);
                        $tbl_member->decrement('balance', $transferAmount, [
                            'updated_on' => now(),
                        ]);
                        LogDepositPlayer($tbl_member, "member", $tbl_player->gamemember_id, $request);
                    });
                } catch (\Throwable $e) {
                    return sendEncryptedJsonResponse([
                        'status' => false,
                        'message' => __('messages.unexpected_error'),
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            $response = \Gamehelper::login($request, $tbl_player, $tbl_game);
            if ($response['status']) {
                $tbl_player->update([
                    'lastlogin_on' => now(),
                ]);
                $tbl_player = $tbl_player->fresh();
            }
            return sendEncryptedJsonResponse([
                'status' => $response['status'],
                'message' => $response['message'],
                'error' => $response['error'] ?? '',
                'code' => $response['code'],
                'url' => $response['url'] ?? null,
                'member_credit' => $tbl_member->balance,
                'player_point'  => $tbl_player->balance,
            ], $response['code']);
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

    public function loginplayerv3(Request $request)
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
            'gamemember_id' => 'nullable|integer',
            'game_id' => 'nullable|integer',
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
            if (!\Gamehelper::duplicaterequest($request->input('gamemember_id'), $request)) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('messages.duplicaterequest'),
                    'error' => __('messages.duplicaterequest'),
                ], 400);
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
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }

            $ip = $request->ip();
            $errors = [];
            Gamemember::where('delete', 0)
                ->where('status', 1)
                ->where('member_id', $request->input('member_id'))
                ->where('has_balance', 1)
                ->chunk(200, function ($players) use ($request, $tbl_member, $ip, &$errors) {
                    foreach ($players as $player) {
                        $locked = Gamemember::where('gamemember_id', $player->gamemember_id)
                            ->where('has_balance', 1)
                            ->update([
                                'has_balance' => 2,
                                'updated_on' => now(),
                            ]);
                        if ($locked === 0) {
                            continue;
                        }
                        $view = \Gamehelper::view($player->gamemember_id);
                        if (!$view['status']) {
                            $errors[] = $view;
                            $player->update([
                                'has_balance' => 1,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $balance = (float) $view['data']->balance;
                        if ($balance <= 0) {
                            $player->update([
                                'has_balance' => 0,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $withdraw = \Gamehelper::withdraw(
                            $player->gamemember_id,
                            $balance,
                            $ip
                        );
                        if (!$withdraw['status']) {
                            $errors[] = $withdraw;
                            $player->update([
                                'has_balance' => 1,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $actualAmount  = $balance - $withdraw['remain'];
                        DB::transaction(function () use ($player, $actualAmount, $withdraw, $tbl_member, $request, $ip) {
                            $lockedMember = Member::where('member_id', $tbl_member->member_id)
                                ->lockForUpdate()
                                ->first();
                            Gamepoint::create([
                                'gamemember_id'  => $player->gamemember_id,
                                'shop_id'        => null,
                                'orderid'        => $withdraw['orderid'],
                                'type'           => 'withdraw',
                                'ip'             => $ip,
                                'amount'         => $actualAmount,
                                'before_balance' => $actualAmount,
                                'after_balance'  => 0,
                                'start_on'       => now(),
                                'end_on'         => now(),
                                'agent_id'       => $lockedMember->agent_id,
                                'status'         => 1,
                                'delete'         => 0,
                                'created_on'     => now(),
                                'updated_on'     => now(),
                            ]);
                            $lockedMember->increment('balance', $actualAmount, [
                                'updated_on' => now(),
                            ]);
                            $player->decrement('balance', $actualAmount, [
                                'has_balance' => 0,
                                'updated_on' => now(),
                            ]);
                            LogWithdrawPlayer(
                                $lockedMember,
                                'member',
                                $player->gamemember_id,
                                $request
                            );
                        });
                    }
                });
            if (!empty($errors)) {
                return sendEncryptedJsonResponse($errors[0], $errors[0]['code']);
            }
            $tbl_member = $tbl_member->fresh();
            $gamememberId = $request->input('gamemember_id');
            if (empty($gamememberId) || $gamememberId == 0) {
                $tbl_provider = Provider::where('status', 1)
                    ->where('delete', 0)
                    ->where('provider_id', $request->input('provider_id') )
                    ->first();
                if (!$tbl_provider) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('provider.no_data_found'),
                            'error' => __('provider.no_data_found'),
                            'data' => $tbl_provider,
                        ],
                        404
                    );
                }
                try {
                    $tbl_player = DB::transaction(function () use ($tbl_member, $tbl_provider, $request) {
                        $playerExist = Gamemember::where('member_id', $tbl_member->member_id)
                            ->where('provider_id', $tbl_provider->provider_id)
                            ->lockForUpdate()
                            ->first();
                        if ($playerExist) {
                            return $playerExist;
                        }
                        // create new player
                        $player_name = generatePlayer();
                        $response = \Gamehelper::create($tbl_provider, $player_name);
                        if (!$response['status']) {
                            throw new \Exception($response['message']);
                        }
                        $newPlayer = Gamemember::create([
                            'member_id' => $tbl_member->member_id,
                            'gameplatform_id' => $tbl_provider->gameplatform_id,
                            'provider_id' => $tbl_provider->provider_id,
                            'uid' => $response['uid'],
                            'loginId' => $response['loginId'],
                            'login' => $response['password'] ? $response['loginId'] : null,
                            'pass' => $response['password'] ? encryptPassword($response['password']) : null,
                            'name' => $player_name,
                            'paymentpin' => $response['paymentpin'],
                            'balance' => 0,
                            'has_balance' => 0,
                            'status' => 1,
                            'delete' => 0,
                            'created_on' => now(),
                            'updated_on' => now(),
                        ]);
                        LogCreatePlayerAccount($tbl_member, "member", $newPlayer->name, $request);
                        return $newPlayer;
                    });
                } catch (\Throwable $e) {
                    return sendEncryptedJsonResponse([
                        'status' => false,
                        'message' => __('messages.unexpected_error'),
                        'error' => $e->getMessage(),
                    ], 500);
                }
            } else {
                $tbl_player = Gamemember::where('gamemember_id', $gamememberId)
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->first();
                if (!$tbl_player) {
                    return sendEncryptedJsonResponse([
                        'status' => false,
                        'message' => __('gamemember.player_no_found'),
                        'error' => __('gamemember.player_no_found'),
                    ], 400);
                }
            }
            $tbl_game = null;
            if ($request->filled('game_id')) {
                $tbl_game = Game::where('game_id', $request->input('game_id'))
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->first();
                if (!$tbl_game) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('game.no_data_found'),
                            'error' => __('game.no_data_found'),
                        ],
                        400
                    );
                }
            }
            if ($tbl_member->balance > 0) {
                try {
                    DB::transaction(function () use ($request, &$tbl_member, $tbl_player) {
                        $tbl_member = Member::where('member_id', $tbl_member->member_id)
                            ->lockForUpdate()
                            ->first();

                        if ($tbl_member->balance <= 0) {
                            return;
                        }
                        $amount = (float)$tbl_member->balance;
                        $amount = floor($amount * 100) / 100;
                        $ip = $request->ip();
                        $depositResponse = \Gamehelper::deposit(
                            $tbl_player->gamemember_id,
                            $amount,
                            $ip
                        );
                        if (!$depositResponse['status']) {
                            throw new \Exception('Deposit failed: ' . ($depositResponse['message'] ?? 'Unknown error'));
                        }
                        $transferAmount = $amount - $depositResponse['remain'];
                        $transferAmount = floor($transferAmount * 100) / 100;
                        Gamepoint::create([
                            'gamemember_id' => $tbl_player->gamemember_id,
                            'shop_id' => null,
                            'orderid' => $depositResponse['orderid'],
                            'type' => "reload",
                            'ip' => $ip,
                            'amount' => $transferAmount,
                            'before_balance' => $tbl_player->balance,
                            'after_balance' => $tbl_player->balance + $transferAmount,
                            'start_on' => now(),
                            'end_on' => now(),
                            'agent_id' => $tbl_member->agent_id,
                            'status' => 1,
                            'delete' => 0,
                            'created_on' => now(),
                            'updated_on' => now(),
                        ]);
                        $tbl_player->increment('balance', $transferAmount, [
                            'has_balance' => 1,
                            'updated_on' => now(),
                        ]);
                        $tbl_member->decrement('balance', $transferAmount, [
                            'updated_on' => now(),
                        ]);
                        LogDepositPlayer($tbl_member, "member", $tbl_player->gamemember_id, $request);
                    });
                } catch (\Throwable $e) {
                    return sendEncryptedJsonResponse([
                        'status' => false,
                        'message' => __('messages.unexpected_error'),
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            $response = \Gamehelper::login($request, $tbl_player, $tbl_game);
            if ($response['status']) {
                $tbl_player->update([
                    'lastlogin_on' => now(),
                ]);
                $tbl_player = $tbl_player->fresh();
            }
            return sendEncryptedJsonResponse([
                'status' => $response['status'],
                'message' => $response['message'],
                'error' => $response['error'] ?? '',
                'code' => $response['code'],
                'url' => $response['url'] ?? null,
                'member_credit' => $tbl_member->balance,
                'player_point' => $tbl_player->balance,
            ], $response['code']);
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
     * changepassword player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changepasswordplayer(Request $request)
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
            $tbl_player = Gamemember::where('gamemember_id', $request->input('gamemember_id') )
                            ->where('status', 1)
                            ->where('delete', 0)
                            ->with('Game', 'Game.Gameplatform')
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
            $response = \Gamehelper::view( $tbl_player );
            if (!$response['status']) {
                return sendEncryptedJsonResponse(
                    [
                        'status'   => false,
                        'message'  => $response['message'],
                        'error'    => $response['error'],
                    ],
                    $response['code']
                );
            }
            $tbl_player = \Gamehelper::changepassword( $tbl_player, $request->input('password') );
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.passwordchangesuccess'),
                    'error' => "",
                    'data' => $tbl_player,
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
     * list player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function playerlist(Request $request, string $type)
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'required|integer',
            'provider_id' => 'required|integer',
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
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
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
            $tbl_provider = Provider::where('status', 1)
                ->where('delete', 0)
                ->where('provider_id', $request->input('provider_id') )
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
            $tbl_player = Gamemember::with('Gameplatform', 'Provider')
                ->where('member_id', $request->input('member_id'))
                ->where('provider_id', $request->input('provider_id') )
                ->where('status', 1)
                ->where('delete', 0)
                ->get();
            $tbl_player = $tbl_player->map(function ($player) {
                $player->balance = number_format((float)$player->balance, 2, '.', '');
                return $player;
            });
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_player,
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
     * transfer list player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferlist(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse([
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return sendEncryptedJsonResponse([
                'status' => false,
                'message' => __('messages.unvalidation'),
                'error' => $validator->errors(),
            ], 422);
        }

        try {
            $tbl_member = Member::where('member_id', $request->member_id)->first();

            if (!$tbl_member) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('member.no_data_found'),
                    'error' => __('member.no_data_found'),
                ], 400);
            }

            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('messages.profileinactive'),
                    'error' => __('messages.profileinactive'),
                ], 401);
            }

            $tbl_provider = Provider::where('status', 1)
                ->where('delete', 0)
                ->get();

            if ($tbl_provider->isEmpty()) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('provider.no_data_found'),
                    'error' => __('provider.no_data_found'),
                    'data' => [],
                ], 500);
            }

            $userGameMembers = Gamemember::where('member_id', $request->member_id)
                ->where('status', 1)
                ->where('delete', 0)
                ->where('has_balance', 1)
                ->get()
                ->keyBy('provider_id');

            foreach ($tbl_provider as $key => $provider) {
                if (!$userGameMembers->has($provider->provider_id)) {
                    $tbl_provider[$key]['player'] = [];
                    continue;
                }

                $tbl_player = $userGameMembers[$provider->provider_id];

                $response = \Gamehelper::view($tbl_player->gamemember_id);

                $tbl_player = $tbl_player->fresh();
                $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
                if (!$response['status']) {
                    $tbl_provider[$key]['player'] = $tbl_player;
                    continue;
                }
                $tbl_provider[$key]['player'] = $tbl_player;
            }

            return sendEncryptedJsonResponse([
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'data' => $tbl_provider,
            ], 200);

        } catch (\Exception $e) {
            return sendEncryptedJsonResponse([
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * reload player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reloadplayer(Request $request, string $type)
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'required|integer',
            'gamemember_id' => 'required|integer',
            'amount' => 'required|numeric|gt:1.00',
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
            if (!\Gamehelper::duplicaterequest( $request->input('gamemember_id'), $request )) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('messages.duplicaterequest'),
                    'error' => __('messages.duplicaterequest'),
                ], 400);
            }
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            $amount = (float) $request->input('amount');
            $tbl_credit = null;
            if ( $tbl_member->balance - $amount < 0 ) {
                if ( $type === "shop") {
                    $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
                    if ( $tbl_shop->balance - $amount < 0 ) {
                        return sendEncryptedJsonResponse(
                            [
                                'status' => false,
                                'message' => __('role.shop')." ".__('messages.insufficient'),
                                'error' => __('role.shop')." ".__('messages.insufficient'),
                            ],
                            403
                        );
                    }
                    $tbl_credit = Credit::create([
                        'member_id' => $tbl_member->member_id,
                        'shop_id' => $request->input('shop_id'),
                        'payment_id' => 1,
                        'type' => "deposit",
                        'amount' => $amount,
                        'before_balance' => $tbl_member->balance,
                        'after_balance' => $amount,
                        'submit_on' => now(),
                        'agent_id' => $tbl_shop->agent_id,
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                    $tbl_shop->decrement('balance', $amount - $tbl_member->balance, [
                        'updated_on' => now(),
                    ]);
                    $tbl_member->update([
                        'balance' => $amount,
                        'updated_on' => now(),
                    ]);
                    $tbl_member = $tbl_member->fresh();
                } else {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('role.member')." ".__('messages.insufficient'),
                            'error' => __('role.member')." ".__('messages.insufficient'),
                        ],
                        403
                    );
                }
            }
            $ip = $request->filled('ip') ? $request->input('ip') : $request->ip();
            $response = \Gamehelper::deposit( $request->input('gamemember_id'), $amount, $ip );
            if (!$response['status']) {
                return sendEncryptedJsonResponse(
                    $response,
                    $response['code']
                );
            }
            $tbl_player = $response['data'];
            $tranferamount = $amount - $response['remain'];
            $tbl_gamepoint = Gamepoint::create([
                'gamemember_id' => $request->input('gamemember_id'),
                'shop_id' => $request->filled('shop_id') ? $request->input('shop_id') : null,
                'orderid' => $response['orderid'],
                'type' => "reload",
                'ip' => $ip,
                'amount' => $tranferamount,
                'before_balance' => $tbl_player->balance,
                'after_balance' => $tbl_player->balance + $tranferamount,
                'start_on' => now(),
                'end_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_player->increment('balance', $tranferamount, [
                'has_balance' => 1,
                'updated_on' => now(),
            ]);
            $tbl_player = $tbl_player->fresh();
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            $tbl_member->decrement('balance', $tranferamount, [
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => $response['status'],
                    'message' => __('messages.deposit_success'),
                    'error' => "",
                    'credit' => $tbl_credit,
                    'point' => $tbl_gamepoint,
                    'member' => $tbl_member,
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
     * withdraw player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawplayer(Request $request, string $type)
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'required|integer',
            'gamemember_id' => 'required|integer',
            'amount' => 'required|numeric|gt:1.00',
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
            if (!\Gamehelper::duplicaterequest( $request->input('gamemember_id'), $request )) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('messages.duplicaterequest'),
                    'error' => __('messages.duplicaterequest'),
                ], 400);
            }
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
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
            $amount = (float) $request->input('amount');
            $ip = $request->filled('ip') ? $request->input('ip') : $request->ip();
            $response = \Gamehelper::withdraw( $request->input('gamemember_id'), $amount, $ip );
            if (!$response['status']) {
                return sendEncryptedJsonResponse(
                    $response,
                    $response['code']
                );
            }
            $tbl_player = $response['data'];
            $tranferamount = $amount - $response['remain'];
            $tbl_gamepoint = Gamepoint::create([
                'gamemember_id' => $request->input('gamemember_id'),
                'shop_id' => $request->filled('shop_id') ? $request->input('shop_id') : null,
                'orderid' => $response['orderid'],
                'type' => "withdraw",
                'ip' => $ip,
                'amount' => $tranferamount,
                'before_balance' => $tbl_player->balance,
                'after_balance' => $tbl_player->balance - $tranferamount,
                'start_on' => now(),
                'end_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_player->decrement('balance', $tranferamount, [
                'has_balance' => 0,
                'updated_on' => now(),
            ]);
            $tbl_player = $tbl_player->fresh();
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            $tbl_member->increment('balance', $tranferamount, [
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => $response['status'],
                    'message' => __('messages.withdraw_success'),
                    'error' => "",
                    'point' => $tbl_gamepoint,
                    'member' => $tbl_member,
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
     * delete player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteplayer(Request $request, string $type)
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
            'manager_id' => 'nullable|integer',
            'shop_id' => 'nullable|integer',
            'member_id' => 'nullable|integer',
            'gamemember_id' => 'required|integer',
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
            if ( !$request->filled($type.'_id') ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => $type.'_id'.__('messages.unvalidation'),
                        'error' => $type.'_id'.__('messages.unvalidation'),
                    ],
                    422
                );
            }
            $checkuser = CheckAvailabilityUser( $request->input($type.'_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_player = Gamemember::where( 'gamemember_id', $request->input('gamemember_id') )
                ->with('Member', 'Game', 'Shop')
                ->first();
            if (!$tbl_player) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('gamemember.player_no_found'),
                        'error' => __('gamemember.player_no_found'),
                        'data' => [],
                    ],
                    500
                );
            }
            if ($type === "member") {
                $member_id = $request->input('member_id');
            } else {
                $member_id = $tbl_player->member_id;
            }
            $tbl_member = Member::where('member_id', $member_id)->first();
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
            $ip = $request->filled('ip') ? $request->input('ip') : $request->ip();
            $response = \Gamehelper::delete( $request->input('gamemember_id'), $ip );
            if (!$response['status']) {
                return sendEncryptedJsonResponse(
                    $response,
                    $response['code']
                );
            }
            $tbl_player = $response['data'];
            $tbl_player->update([
                'status' => 0,
                'delete' => 1,
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'status' => $response['status'],
                    'status' => true,
                    'message' => __(
                        'gamemember.gamemember_deleted_successfully',
                        ['gamemember_id' => $request->input('gamemember_id')]
                    ),
                    'error' => "",
                    'member' => $tbl_member,
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
     * transfer point player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferpoint(Request $request)
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
            'gamemember_id_from' => 'required|integer',
            'gamemember_id_to' => 'required|integer',
            'amount' => 'required|numeric|gt:1.00',
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
            $amount = (float) $request->input('amount');
            $ip = $request->filled('ip') ? $request->input('ip') : $request->ip();
            $responsefrom = \Gamehelper::withdraw(
                $request->input('gamemember_id_from'),
                $amount,
                $ip
            );
            if (!$responsefrom['status']) {
                return sendEncryptedJsonResponse(
                    $responsefrom,
                    $responsefrom['code']
                );
            }
            $tbl_player_from = $responsefrom['data'];
            $tranferamount = $amount - $responsefrom['remain'];
            $tbl_gamepoint_from = Gamepoint::create([
                'gamemember_id' => $request->input('gamemember_id_from'),
                'orderid' => $response['orderid'],
                'type' => "withdraw",
                'ip' => $ip,
                'amount' => $tranferamount,
                'before_balance' => $tbl_player_from->balance,
                'after_balance' => $tbl_player_from->balance - $tranferamount,
                'start_on' => now(),
                'end_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_player_from->decrement('balance', $tranferamount, [
                'has_balance' => 0,
                'updated_on' => now(),
            ]);
            $responseto = \Gamehelper::deposit(
                $request->input('gamemember_id_to'),
                $tranferamount,
                $ip
            );
            if (!$responseto['status']) {
                return sendEncryptedJsonResponse(
                    $responseto,
                    $responseto['code']
                );
            }
            $tbl_player_to = $responseto['data'];
            $tranferamount -= $responseto['remain'];
            $tbl_gamepoint_to = Gamepoint::create([
                'gamemember_id' => $request->input('gamemember_id_to'),
                'orderid' => $response['orderid'],
                'type' => "reload",
                'ip' => $ip,
                'amount' => $tranferamount,
                'before_balance' => $tbl_player_to->balance,
                'after_balance' => $tbl_player_to->balance + $tranferamount,
                'start_on' => now(),
                'end_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_player_to->increment('balance', $tranferamount, [
                'has_balance' => 1,
                'updated_on' => now(),
            ]);
            $tbl_gamepoint_from->amount = number_format((float)$tbl_gamepoint_from->amount, 2, '.', '');
            $tbl_gamepoint_from->before_balance = number_format((float)$tbl_gamepoint_from->before_balance, 2, '.', '');
            $tbl_gamepoint_from->after_balance = number_format((float)$tbl_gamepoint_from->after_balance, 2, '.', '');
            $tbl_gamepoint_to->amount = number_format((float)$tbl_gamepoint_to->amount, 2, '.', '');
            $tbl_gamepoint_to->before_balance = number_format((float)$tbl_gamepoint_to->before_balance, 2, '.', '');
            $tbl_gamepoint_to->after_balance = number_format((float)$tbl_gamepoint_to->after_balance, 2, '.', '');
            $tbl_player_from = $tbl_player_from->fresh();
            $tbl_player_from->balance = number_format((float)$tbl_player_from->balance, 2, '.', '');
            $tbl_player_to = $tbl_player_to->fresh();
            $tbl_player_to->balance = number_format((float)$tbl_player_to->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.deposit_success'),
                    'error' => "",
                    'pointfrom' => $tbl_gamepoint_from,
                    'playerfrom' => $tbl_player_from,
                    'pointto' => $tbl_gamepoint_to,
                    'playerto' => $tbl_player_to,
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
     * transfer out player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferout(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse([
                'status' => false,
                'message' => __('messages.Unauthorized'),
                'error' => __('messages.Unauthorized'),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'ip'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return sendEncryptedJsonResponse([
                'status' => false,
                'message' => __('messages.unvalidation'),
                'error'   => $validator->errors(),
            ], 422);
        }

        if (!\Gamehelper::duplicaterequest($request->input('member_id'), $request)) {
            return sendEncryptedJsonResponse([
                'status'  => false,
                'message' => __('messages.duplicaterequest'),
                'error'   => __('messages.duplicaterequest'),
            ], 400);
        }

        try {
            return DB::transaction(function () use ($request) {
                $tbl_member = Member::where('member_id', $request->input('member_id'))
                    ->lockForUpdate()
                    ->first();

                if (!$tbl_member) {
                    return sendEncryptedJsonResponse([
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ], 400);
                }
                if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1) {
                    return sendEncryptedJsonResponse([
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ], 401);
                }

                $ip = $request->filled('ip')
                    ? $request->input('ip')
                    : $request->ip();

                $gamesToWithdraw = Gamepoint::select('gp.gamemember_id')
                    ->from('tbl_gamepoint as gp')
                    ->join('tbl_gamemember as gm', 'gp.gamemember_id', '=', 'gm.gamemember_id')
                    ->leftJoin('tbl_gamepoint as w', function ($join) {
                        $join->on('w.gamemember_id', '=', 'gp.gamemember_id')
                            ->where('w.type', 'withdraw')
                            ->where('w.status', 1)
                            ->whereColumn('w.gamepoint_id', '>', 'gp.gamepoint_id');
                    })
                    ->where('gm.member_id', $tbl_member->member_id)
                    ->where('gp.type', 'reload')
                    ->where('gp.status', 1)
                    ->where('gm.status', 1)
                    ->where('gm.delete', 0)
                    ->whereNull('w.gamepoint_id')
                    ->orderBy('gp.gamepoint_id', 'DESC')
                    ->get();

                $totalWithdrawn = 0;
                $tbl_gamepoints = [];
                $tbl_players    = [];

                foreach ($gamesToWithdraw as $row) {

                    $gamememberId = $row->gamemember_id;

                    $viewResponse = \Gamehelper::view($gamememberId);

                    if (!$viewResponse['status'] || empty($viewResponse['data'])) {
                        continue; // skip if provider fails
                    }

                    $player = $viewResponse['data'];

                    if ($player->balance <= 0) {
                        continue;
                    }

                    $withdrawResponse = \Gamehelper::withdraw(
                        $gamememberId,
                        $player->balance,
                        $ip
                    );
                    $amountToWithdraw = $player->balance - $withdrawResponse['remain'];

                    $gamepoint = Gamepoint::create([
                        'gamemember_id'  => $gamememberId,
                        'orderid'        => $withdrawResponse['orderid'],
                        'type'           => "withdraw",
                        'ip'             => $ip,
                        'amount'         => $amountToWithdraw,
                        'before_balance' => $player->balance,
                        'after_balance'  => $player->balance - $amountToWithdraw,
                        'start_on'       => now(),
                        'end_on'         => now(),
                        'agent_id'       => $tbl_member->agent_id,
                        'status'         => $withdrawResponse['status'] ? 1 : -1,
                        'delete'         => 0,
                        'created_on'     => now(),
                        'updated_on'     => now(),
                    ]);

                    if (!$withdrawResponse['status']) {
                        continue;
                    }

                    $gamepoint->amount = number_format((float)$gamepoint->amount, 2, '.', '');
                    $gamepoint->before_balance = number_format((float)$gamepoint->before_balance, 2, '.', '');
                    $gamepoint->after_balance = number_format((float)$gamepoint->after_balance, 2, '.', '');
                    $tbl_gamepoints[] = $gamepoint;

                    $totalWithdrawn += $amountToWithdraw;

                    Gamemember::where('gamemember_id', $gamememberId)
                        ->update([
                            'balance'    => 0,
                            'updated_on' => now(),
                        ]);
                    $player = Gamemember::find($gamememberId);
                    $player->balance = number_format((float)$player->balance, 2, '.', '');
                    $tbl_players[] = $player;
                }

                if ($totalWithdrawn > 0) {
                    $tbl_member->increment('balance', $totalWithdrawn, [
                        'updated_on' => now(),
                    ]);
                }

                return sendEncryptedJsonResponse([
                    'status'  => true,
                    'message' => __('messages.withdraw_success'),
                    'error'   => "",
                    'point'   => $tbl_gamepoints,
                    'member'  => $tbl_member->fresh(),
                    'player'  => $tbl_players,
                ], 200);

            }, 5);

        } catch (\Throwable $e) {
            return sendEncryptedJsonResponse([
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * reload player all.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reloadplayerout(Request $request)
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
            if (!\Gamehelper::duplicaterequest( $request->input('gamemember_id'), $request )) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('messages.duplicaterequest'),
                    'error' => __('messages.duplicaterequest'),
                ], 400);
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
            if ( $tbl_member->balance <= 0 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('role.member')." ".__('messages.insufficient'),
                        'error' => __('role.member')." ".__('messages.insufficient'),
                    ],
                    403
                );

            }
            $ip = $request->filled('ip') ? $request->input('ip') : $request->ip();
            $response = \Gamehelper::deposit(
                $request->input('gamemember_id'),
                $tbl_member->balance,
                $ip
            );
            if (!$response['status']) {
                return sendEncryptedJsonResponse(
                    $response,
                    $response['code']
                );
            }
            $tbl_player = $response['data'];
            $tranferamount = $tbl_member->balance - $response['remain'];
            $tbl_gamepoint = Gamepoint::create([
                'gamemember_id' => $request->input('gamemember_id'),
                'shop_id' => null,
                'orderid' => $response['orderid'],
                'type' => "reload",
                'ip' => $ip,
                'amount' => $tranferamount,
                'before_balance' => $tbl_player->balance,
                'after_balance' => $tbl_player->balance + $tranferamount,
                'start_on' => now(),
                'end_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_player->increment('balance', $tranferamount, [
                'has_balance' => 1,
                'updated_on' => now(),
            ]);
            $tbl_player = $tbl_player->fresh();
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            $tbl_member->decrement('balance', $tranferamount, [
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            LogDepositPlayer( $tbl_member, "member", $request->input('gamemember_id'), $request);
            return sendEncryptedJsonResponse(
                [
                    'status' => $response['status'],
                    'message' => __('messages.deposit_success'),
                    'error' => "",
                    'point' => $tbl_gamepoint,
                    'member' => $tbl_member,
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
     * withdraw player all.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawplayerout(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse([
                'status'  => false,
                'message' => __('messages.Unauthorized'),
                'error'   => __('messages.Unauthorized'),
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'member_id'     => 'required|integer',
            'gamemember_id' => 'required|integer',
            'ip'            => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse([
                'status'  => false,
                'message' => __('messages.unvalidation'),
                'error'   => $validator->errors(),
            ], 422);
        }
        if (!\Gamehelper::duplicaterequest($request->input('gamemember_id'), $request)) {
            return sendEncryptedJsonResponse([
                'status'  => false,
                'message' => __('messages.duplicaterequest'),
                'error'   => __('messages.duplicaterequest'),
            ], 400);
        }
        try {
            return DB::transaction(function () use ($request) {
                $tbl_member = Member::where('member_id', $request->input('member_id'))
                    ->lockForUpdate()
                    ->first();
                if (!$tbl_member) {
                    return sendEncryptedJsonResponse([
                        'status'  => false,
                        'message' => __('member.no_data_found'),
                        'error'   => __('member.no_data_found'),
                    ], 400);
                }
                if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1) {
                    return sendEncryptedJsonResponse([
                        'status'  => false,
                        'message' => __('messages.profileinactive'),
                        'error'   => __('messages.profileinactive'),
                    ], 401);
                }
                $ip = $request->filled('ip')
                    ? $request->input('ip')
                    : $request->ip();

                $viewResponse = \Gamehelper::view($request->input('gamemember_id'));
                if (!$viewResponse['status'] || empty($viewResponse['data'])) {
                    return sendEncryptedJsonResponse(
                        $viewResponse,
                        $viewResponse['code'] ?? 400
                    );
                }
                $tbl_player = $viewResponse['data'];
                if (!$tbl_player) {
                    return sendEncryptedJsonResponse([
                        'status'  => false,
                        'message' => __('gamemember.player_not_found'),
                        'error'   => __('gamemember.player_not_found'),
                    ], 400);
                }
                if ($tbl_player->has_balance !== 1 || $tbl_player->balance <= 0) {
                    $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
                    return sendEncryptedJsonResponse([
                        'status'  => true,
                        'message' => __('messages.withdraw_success'),
                        'error'   => "",
                        'point'   => [],
                        'member'  => $tbl_member,
                        'player'  => $tbl_player,
                    ], 200);
                }

                $withdrawResponse = \Gamehelper::withdraw(
                    $tbl_player->gamemember_id,
                    $tbl_player->balance,
                    $ip
                );
                if (!$withdrawResponse['status']) {
                    return sendEncryptedJsonResponse(
                        $withdrawResponse,
                        $withdrawResponse['code'] ?? 400
                    );
                }
                $providerPlayer = $withdrawResponse['data'];
                $amountToWithdraw = $tbl_player->balance - $withdrawResponse['remain'];

                // verify provider balance after withdraw
                $verifyResponse = \Gamehelper::view($request->input('gamemember_id'));
                if (!$verifyResponse['status'] || empty($verifyResponse['data'])) {
                    return sendEncryptedJsonResponse([
                        'status' => false,
                        'message' => 'Unable to verify provider balance',
                        'error' => 'Provider verification failed'
                    ], 500);
                }
                if ($verifyResponse['data']->balance > 0.005) {
                    return sendEncryptedJsonResponse([
                        'status' => false,
                        'message' => 'Provider withdraw failed',
                        'error' => 'Provider still has balance'
                    ], 500);
                }

                $tbl_gamepoint = Gamepoint::create([
                    'gamemember_id'  => $tbl_player->gamemember_id,
                    'shop_id'        => null,
                    'orderid'        => $withdrawResponse['orderid'],
                    'type'           => "withdraw",
                    'ip'             => $ip,
                    'amount'         => $tbl_player->balance,
                    'before_balance' => $tbl_player->balance,
                    'after_balance'  => $tbl_player->balance - $amountToWithdraw,
                    'start_on'       => now(),
                    'end_on'         => now(),
                    'agent_id'       => $tbl_member->agent_id,
                    'status'         => 1,
                    'delete'         => 0,
                    'created_on'     => now(),
                    'updated_on'     => now(),
                ]);
                $tbl_member->increment('balance', $amountToWithdraw, [
                    'updated_on' => now(),
                ]);
                $tbl_player->decrement('balance', $amountToWithdraw, [
                    'has_balance' => 0,
                    'updated_on' => now(),
                ]);
                $tbl_player = $tbl_player->fresh();
                $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
                LogWithdrawPlayer(
                    $tbl_member,
                    "member",
                    $tbl_player->gamemember_id,
                    $request
                );
                return sendEncryptedJsonResponse([
                    'status'  => true,
                    'message' => __('messages.withdraw_success'),
                    'error'   => "",
                    'point'   => $tbl_gamepoint,
                    'member'  => $tbl_member->fresh(),
                    'player'  => $tbl_player,
                ], 200);
            }, 5); // retry 5 times on deadlock
        } catch (\Throwable $e) {
            return sendEncryptedJsonResponse([
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * player profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
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
            'provider_id' => 'required|integer',
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
            if (!\Prefix::duplicaterequest($request->input('member_id'), $request)) {
                return sendEncryptedJsonResponse([
                    'status'  => false,
                    'message' => __('messages.duplicaterequest'),
                    'error'   => __('messages.duplicaterequest'),
                ], 400);
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
            $tbl_provider = Provider::where('status', 1)
                ->where('delete', 0)
                ->where('provider_id', $request->input('provider_id') )
                ->first();
            if (!$tbl_provider) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('provider.no_data_found'),
                        'error' => __('provider.no_data_found'),
                        'data' => $tbl_provider,
                    ],
                    404
                );
            }
            $ip = $request->ip();
            $errors = [];
            Gamemember::where('delete', 0)
                ->where('status', 1)
                ->where('member_id', $request->input('member_id'))
                ->where('has_balance', 1)
                ->chunk(200, function ($allplayer) use ($request, $tbl_member, $ip, &$errors) {
                    foreach ($allplayer as $player) {
                        // 🔒 Lock to prevent double withdraw
                        $locked = Gamemember::where('gamemember_id', $player->gamemember_id)
                            ->where('has_balance', 1)
                            ->update([
                                'has_balance' => 2, // processing
                                'updated_on' => now(),
                            ]);
                        if ($locked === 0) {
                            continue;
                        }
                        $view = \Gamehelper::view($player->gamemember_id);
                        if (!$view['status']) {
                            $errors[] = $view;
                            $player->update([
                                'has_balance' => 1,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $balance = (float) $view['data']->balance;
                        if ($balance <= 0) {
                            $player->update([
                                'has_balance' => 0,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $withdraw = \Gamehelper::withdraw(
                            $player->gamemember_id,
                            $balance,
                            $ip
                        );
                        if (!$withdraw['status']) {
                            $errors[] = $withdraw;
                            // rollback lock
                            $player->update([
                                'has_balance' => 1,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $balance = $balance - $withdraw['remain'];
                        DB::transaction(function () use ($player, $balance, $withdraw, $tbl_member, $request, $ip) {
                            Gamepoint::create([
                                'gamemember_id'  => $player->gamemember_id,
                                'shop_id'        => null,
                                'orderid'        => $withdraw['orderid'],
                                'type'           => 'withdraw',
                                'ip'             => $ip,
                                'amount'         => $balance,
                                'before_balance' => $balance,
                                'after_balance'  => 0,
                                'start_on'       => now(),
                                'end_on'         => now(),
                                'agent_id'       => $tbl_member->agent_id,
                                'status'         => 1,
                                'delete'         => 0,
                                'created_on'     => now(),
                                'updated_on'     => now(),
                            ]);
                            $tbl_member->increment('balance', $balance, [
                                'updated_on' => now(),
                            ]);
                            $player->decrement('balance', $balance, [
                                'has_balance' => 0,
                                'updated_on' => now(),
                            ]);
                            LogWithdrawPlayer(
                                $tbl_member,
                                'member',
                                $player->gamemember_id,
                                $request
                            );
                        });
                    }
                });
            if (!empty($errors)) {
                return sendEncryptedJsonResponse(
                    $errors[0],
                    $errors[0]['code']
                );
            }
            $tbl_player = Gamemember::where('delete', 0)
                ->where('provider_id', $request->input('provider_id') )
                ->where('member_id', $request->input('member_id'))
                ->first();
            if ( !$tbl_player ) {
                $player_name = generatePlayer();
                $response = \Gamehelper::create($tbl_provider, $player_name);
                if (!$response['status']) {
                    return sendEncryptedJsonResponse(
                        $response,
                        $response['code']
                    );
                }
                $tbl_player = Gamemember::create([
                    'member_id' => $request->input('member_id'),
                    'gameplatform_id' => $tbl_provider->gameplatform_id,
                    'provider_id' => $request->input('provider_id'),
                    'uid' => $response['uid'],
                    'loginId' => $response['loginId'],
                    'login' => $response['password'] ? $response['loginId'] : null,
                    'pass' => $response['password'] ? encryptPassword( $response['password'] ) : null,
                    'name' => $player_name,
                    'paymentpin' => $response['paymentpin'],
                    'balance' => 0.0000,
                    'has_balance' => 0,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                LogCreatePlayerAccount( $tbl_member, "member", $tbl_player->name, $request );
            } else {
                $response = \Gamehelper::view( $tbl_player->gamemember_id );
                if (!$response['status']) {
                    return sendEncryptedJsonResponse(
                        $response,
                        $response['code']
                    );
                }
                $tbl_player = $response['data'];
            }
            if (
                $tbl_member->balance > 0
            ) {
                try {
                    DB::transaction(function () use (
                        $request,
                        $tbl_player,
                        $ip,
                        &$tbl_member
                    ) {
                        // lock member row
                        $tbl_member = Member::where('member_id', $tbl_member->member_id)
                            ->lockForUpdate()
                            ->first();
                        // Re-check AFTER locking
                        if ($tbl_member->balance <= 0) {
                            return;
                        }
                        $amount = $tbl_member->balance;
                        // call provider
                        $response = \Gamehelper::deposit(
                            $tbl_player->gamemember_id,
                            $amount,
                            $ip
                        );
                        if (!$response['status']) {
                            return sendEncryptedJsonResponse(
                                $response,
                                $response['code']
                            );
                        }
                        $tranferamount = $amount - $response['remain'];
                        Gamepoint::create([
                            'gamemember_id' => $tbl_player->gamemember_id,
                            'shop_id' => null,
                            'orderid' => $response['orderid'],
                            'type' => "reload",
                            'ip' => $ip,
                            'amount' => $tranferamount,
                            'before_balance' => $tbl_player->balance,
                            'after_balance' => $tbl_player->balance + $tranferamount,
                            'start_on' => now(),
                            'end_on' => now(),
                            'agent_id' => $tbl_member->agent_id,
                            'status' => $response['status'],
                            'delete' => 0,
                            'created_on' => now(),
                            'updated_on' => now(),
                        ]);
                        $tbl_player->increment('balance', $tranferamount, [
                            'has_balance' => 1,
                            'updated_on' => now(),
                        ]);
                        $tbl_member->decrement('balance', $tranferamount, [
                            'updated_on' => now(),
                        ]);
                        LogDepositPlayer($tbl_member, "member", $tbl_player->gamemember_id, $request);
                    });
                } catch (\Exception $e) {
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
            $tbl_game = null;
            $response = [];
            switch ($tbl_provider->platform_type) {
                case 'Web-Single':
                    $response['status'] = true;
                    $response['message'] = __('messages.list_success');
                    $response['error'] = "";
                    $response['code'] = 200;
                    $response['url'] = "";
                    $response['game'] = Game::where( 'provider_id', $request->input('provider_id') )
                        ->where( 'status', 1 )
                        ->where( 'delete', 0 )
                        ->get();
                    break;
                default:
                    $response = \Gamehelper::login($request, $tbl_player, $tbl_game);
                    $response['game'] = [];
                    break;
            }
            if ($response['status']) {
                $tbl_player->update([
                    'lastlogin_on' => now(), 
                ]);
                $tbl_player = $tbl_player->fresh();
            }
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            $response['player'] = $tbl_player;
            $response['platform_type'] = $tbl_provider->platform_type;
            return sendEncryptedJsonResponse(
                $response,
                $response['code']
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $response = [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
                500,
            ];
            $tbl_gameerror = Gameerror::create([
                'api' => '/member/player/list/add/reload/login',
                'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                'response' => json_encode($response, JSON_UNESCAPED_SLASHES),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
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
    
    public function profilev2(Request $request)
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
            'provider_id' => 'required|integer',
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
            if (!\Prefix::duplicaterequest($request->input('member_id'), $request)) {
                return sendEncryptedJsonResponse([
                    'status'  => false,
                    'message' => __('messages.duplicaterequest'),
                    'error'   => __('messages.duplicaterequest'),
                ], 400);
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
            $tbl_provider = Provider::where('status', 1)
                ->where('delete', 0)
                ->where('provider_id', $request->input('provider_id') )
                ->first();
            if (!$tbl_provider) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('provider.no_data_found'),
                        'error' => __('provider.no_data_found'),
                        'data' => $tbl_provider,
                    ],
                    404
                );
            }
            $ip = $request->ip();
            $errors = [];
            Gamemember::where('delete', 0)
                ->where('status', 1)
                ->where('member_id', $request->input('member_id'))
                ->where('has_balance', 1)
                ->chunk(200, function ($allplayer) use ($request, $tbl_member, $ip, &$errors) {
                    foreach ($allplayer as $player) {
                        // 🔒 Lock to prevent double withdraw
                        $locked = Gamemember::where('gamemember_id', $player->gamemember_id)
                            ->where('has_balance', 1)
                            ->update([
                                'has_balance' => 2, // processing
                                'updated_on' => now(),
                            ]);
                        if ($locked === 0) {
                            continue;
                        }
                        $view = \Gamehelper::view($player->gamemember_id);
                        if (!$view['status']) {
                            $errors[] = $view;
                            $player->update([
                                'has_balance' => 1,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $balance = (float) $view['data']->balance;
                        if ($balance <= 0) {
                            $player->update([
                                'has_balance' => 0,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $withdraw = \Gamehelper::withdraw(
                            $player->gamemember_id,
                            $balance,
                            $ip
                        );
                        if (!$withdraw['status']) {
                            $errors[] = $withdraw;
                            // rollback lock
                            $player->update([
                                'has_balance' => 1,
                                'updated_on' => now(),
                            ]);
                            continue;
                        }
                        $balance = $balance - $withdraw['remain'];
                        DB::transaction(function () use ($player, $balance, $withdraw, $tbl_member, $request, $ip) {
                            Gamepoint::create([
                                'gamemember_id'  => $player->gamemember_id,
                                'shop_id'        => null,
                                'orderid'        => $withdraw['orderid'],
                                'type'           => 'withdraw',
                                'ip'             => $ip,
                                'amount'         => $balance,
                                'before_balance' => $balance,
                                'after_balance'  => 0,
                                'start_on'       => now(),
                                'end_on'         => now(),
                                'agent_id'       => $tbl_member->agent_id,
                                'status'         => 1,
                                'delete'         => 0,
                                'created_on'     => now(),
                                'updated_on'     => now(),
                            ]);
                            $tbl_member->increment('balance', $balance, [
                                'updated_on' => now(),
                            ]);
                            $player->decrement('balance', $balance, [
                                'has_balance' => 0,
                                'updated_on' => now(),
                            ]);
                            LogWithdrawPlayer(
                                $tbl_member,
                                'member',
                                $player->gamemember_id,
                                $request
                            );
                        });
                    }
                });
            if (!empty($errors)) {
                return sendEncryptedJsonResponse(
                    $errors[0],
                    $errors[0]['code']
                );
            }
            $tbl_player = Gamemember::where('delete', 0)
                ->where('provider_id', $request->input('provider_id') )
                ->where('member_id', $request->input('member_id'))
                ->first();
            if ( !$tbl_player ) {
                $player_name = generatePlayer();
                $response = \Gamehelper::create($tbl_provider, $player_name);
                if (!$response['status']) {
                    return sendEncryptedJsonResponse(
                        $response,
                        $response['code']
                    );
                }
                $tbl_player = Gamemember::create([
                    'member_id' => $request->input('member_id'),
                    'gameplatform_id' => $tbl_provider->gameplatform_id,
                    'provider_id' => $request->input('provider_id'),
                    'uid' => $response['uid'],
                    'loginId' => $response['loginId'],
                    'login' => $response['password'] ? $response['loginId'] : null,
                    'pass' => $response['password'] ? encryptPassword( $response['password'] ) : null,
                    'name' => $player_name,
                    'paymentpin' => $response['paymentpin'],
                    'balance' => 0.0000,
                    'has_balance' => 0,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                LogCreatePlayerAccount( $tbl_member, "member", $tbl_player->name, $request );
            } else {
                $response = \Gamehelper::view( $tbl_player->gamemember_id );
                if (!$response['status']) {
                    return sendEncryptedJsonResponse(
                        $response,
                        $response['code']
                    );
                }
                $tbl_player = $response['data'];
            }
            if ($tbl_member->balance > 0 && in_array($tbl_provider->platform_type, ['Web-Lobby', 'App'])) {
                try {
                    DB::transaction(function () use (
                        $request,
                        $tbl_player,
                        $ip,
                        &$tbl_member
                    ) {
                        // lock member row
                        $tbl_member = Member::where('member_id', $tbl_member->member_id)
                            ->lockForUpdate()
                            ->first();
                        // Re-check AFTER locking
                        if ($tbl_member->balance <= 0) {
                            return;
                        }
                        $amount = $tbl_member->balance;
                        // call provider
                        $response = \Gamehelper::deposit(
                            $tbl_player->gamemember_id,
                            $amount,
                            $ip
                        );
                        if (!$response['status']) {
                            return sendEncryptedJsonResponse(
                                $response,
                                $response['code']
                            );
                        }
                        $tranferamount = $amount - $response['remain'];
                        Gamepoint::create([
                            'gamemember_id' => $tbl_player->gamemember_id,
                            'shop_id' => null,
                            'orderid' => $response['orderid'],
                            'type' => "reload",
                            'ip' => $ip,
                            'amount' => $tranferamount,
                            'before_balance' => $tbl_player->balance,
                            'after_balance' => $tbl_player->balance + $tranferamount,
                            'start_on' => now(),
                            'end_on' => now(),
                            'agent_id' => $tbl_member->agent_id,
                            'status' => $response['status'],
                            'delete' => 0,
                            'created_on' => now(),
                            'updated_on' => now(),
                        ]);
                        $tbl_player->increment('balance', $tranferamount, [
                            'has_balance' => 1,
                            'updated_on' => now(),
                        ]);
                        $tbl_member->decrement('balance', $tranferamount, [
                            'updated_on' => now(),
                        ]);
                        LogDepositPlayer($tbl_member, "member", $tbl_player->gamemember_id, $request);
                    });
                } catch (\Exception $e) {
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
            $tbl_game = null;
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            $response = [];
            switch ($tbl_provider->platform_type) {
                case 'Web-Single':
                    $response = [
                        'status' => true,
                        'message' => __('messages.list_success'),
                        'error' => '',
                        'code' => 200,
                        'url' => '',
                        'member_credit' => $tbl_member->balance,
                        'player_point' => $tbl_player->balance,
                    ];
                    break;
                default:
                    $loginResponse = \Gamehelper::login($request, $tbl_player, $tbl_game);
                    if (!$loginResponse['status']) {
                        return sendEncryptedJsonResponse($loginResponse, $loginResponse['code']);
                    }
                    $response = [
                        'status' => true,
                        'message' => __('messages.login_success'),
                        'error' => '',
                        'code' => 200,
                        'url' => $loginResponse['url'] ?? null,
                        'member_credit' => $tbl_member->balance,
                        'player_point' => $tbl_player->balance,
                    ];
                    break;
            }
            if ($response['status']) {
                $tbl_player->update([
                    'lastlogin_on' => now(),
                ]);
                $tbl_player = $tbl_player->fresh();
            }
            $tbl_player->balance = number_format((float)$tbl_player->balance, 2, '.', '');
            $response['player'] = $tbl_player;
            $response['platform_type'] = $tbl_provider->platform_type;
            return sendEncryptedJsonResponse(
                $response,
                $response['code']
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $response = [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
                500,
            ];
            $tbl_gameerror = Gameerror::create([
                'api' => '/member/player/list/add/reload/login',
                'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                'response' => json_encode($response, JSON_UNESCAPED_SLASHES),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
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
     * shop reload player.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopreloadplayerprovider(Request $request)
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
            'provider_id' => 'required|integer',
            'amount' => 'required|numeric|gt:1.00',
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
            if (!\Gamehelper::duplicaterequest( $request->input('gamemember_id'), $request )) {
                return sendEncryptedJsonResponse([
                    'status' => false,
                    'message' => __('messages.duplicaterequest'),
                    'error' => __('messages.duplicaterequest'),
                ], 400);
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
            $tbl_provider = Provider::where( 'provider_id', $request->input('provider_id') )
                ->where( 'status', 1 )
                ->where( 'delete', 0 )
                ->with('Gameplatform')
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
            $tbl_player = Gamemember::where('provider_id', $request->input('provider_id') )
                ->where('member_id', $tbl_member->member_id )
                ->where('status', 1)
                ->where('delete', 0)
                ->with('Member','Gameplatform')
                ->first();
            if ( !$tbl_player ) {
                $player_name = generatePlayer();
                $response = \Gamehelper::create($tbl_provider, $player_name);
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
                $tbl_player = Gamemember::create([
                    'member_id' => $tbl_member->member_id,
                    'gameplatform_id' => $tbl_provider->gameplatform_id,
                    'provider_id' => $request->input('provider_id'),
                    'uid' => $response['uid'],
                    'loginId' => $response['loginId'],
                    'login' => $response['password'] ? $response['loginId'] : null,
                    'pass' => $response['password'] ? encryptPassword( $response['password'] ) : null,
                    'name' => $player_name,
                    'paymentpin' => $response['paymentpin'],
                    'shop_id' => $type === "shop" ? $request->input('shop_id') : null,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            } else {
                $response = \Gamehelper::view( $tbl_player->gamemember_id );
                if ( !$response['status'] ) {
                    return sendEncryptedJsonResponse(
                        $response,
                        $response['code']
                    );
                }
                $tbl_player = $response['data'];
            }
            $ip = $request->filled('ip') ? $request->input('ip') : $request->ip();
            $amount = (float) $request->input('amount');
            $tbl_credit = null;
            if ( $tbl_member->balance - $amount < 0 ) {
                $tbl_shop = Shop::where('shop_id', $request->input('shop_id'))->first();
                if ( $tbl_shop->balance - $amount < 0 ) {
                    return sendEncryptedJsonResponse(
                        [
                            'status' => false,
                            'message' => __('role.shop')." ".__('messages.insufficient'),
                            'error' => __('role.shop')." ".__('messages.insufficient'),
                        ],
                        403
                    );
                }
                $tbl_credit = Credit::create([
                    'member_id' => $tbl_member->member_id,
                    'shop_id' => $request->input('shop_id'),
                    'payment_id' => 1,
                    'type' => "deposit",
                    'amount' => $amount,
                    'before_balance' => $tbl_member->balance,
                    'after_balance' => $amount,
                    'submit_on' => now(),
                    'agent_id' => $tbl_shop->agent_id,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                $tbl_shop->decrement('balance', $amount - $tbl_member->balance, [
                    'updated_on' => now(),
                ]);
                $tbl_member->update([
                    'balance', $amount,
                    'updated_on' => now(),
                ]);
                $tbl_member = $tbl_member->fresh();
            }
            $response = \Gamehelper::deposit( $tbl_player->gamemember_id, $amount, $ip );
            if (!$response['status']) {
                return sendEncryptedJsonResponse(
                    $response,
                    $response['code']
                );
            }
            $tbl_player = $response['data'];
            $tranferamount = $amount - $response['remain'];
            $tbl_gamepoint = Gamepoint::create([
                'gamemember_id' => $tbl_player->gamemember_id,
                'shop_id' => $request->input('shop_id'),
                'orderid' => $response['orderid'],
                'type' => "reload",
                'ip' => $ip,
                'amount' => $tranferamount,
                'before_balance' => $tbl_player->balance,
                'after_balance' => $tbl_player->balance + $tranferamount,
                'start_on' => now(),
                'end_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            $tbl_player->increment('balance', $tranferamount, [
                'has_balance' => 1,
                'updated_on' => now(),
            ]);
            $tbl_member->decrement('balance', $tranferamount, [
                'updated_on' => now(),
            ]);
            $tbl_member = $tbl_member->fresh();
            $tbl_member->balance = number_format((float)$tbl_member->balance, 2, '.', '');
            return sendEncryptedJsonResponse(
                [
                    'status' => $response['status'],
                    'message' => __('messages.deposit_success'),
                    'error' => "",
                    'credit' => $tbl_credit,
                    'point' => $tbl_gamepoint,
                    'member' => $tbl_member,
                    'player' => $tbl_player->fresh(),
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
