<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Gameplatform;
use App\Models\Gamelog;
use App\Models\Gamebookmark;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class GameController extends Controller
{

    /**
     * game type list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function type(Request $request, string $type)
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
        ]);
        try {
            $checkuser = CheckAvailabilityUser( $request->input('user_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
                );
            }
            $tbl_gametype = Gametype::where( 'status', 1 )
                                    ->where( 'delete', 0 )
                                    ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_gametype,
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
     * game list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request, string $type)
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
            'type' => 'nullable|integer',
            'isBookmark' => 'nullable|boolean',
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
            $lang = $request->header('Accept-Language');
            $query = Game::query()->with('Gameplatform','gameType','Provider')
                        ->where( 'status', 1 )
                        ->where( 'delete', 0 )
                        ->where('provider_id', $request->input('provider_id') );
            if ($request->filled('type')) {
                $query->where('type', $request->input('type') );
            }
            $tbl_game = $query->get();
            $tbl_game = $tbl_game->map(function ($game) use ($request,$type,$lang) {
                if ( $type === "member" )
                {
                    $tbl_gamebookmark = Gamebookmark::where( 'member_id', $request->input('member_id') )
                                                    ->where( 'game_id', $game->game_id)
                                                    ->where( 'status', 1 )
                                                    ->where( 'delete', 0 )
                                                    ->first();
                    $game->isBookmark = $tbl_gamebookmark ? 1 : 0;
                    $game->gamebookmark_id = $tbl_gamebookmark ? $tbl_gamebookmark->gamebookmark_id : null;
                }
                if ( $lang === "zh") {
                    $game->icon = $game->icon_zh ? $game->icon_zh : $game->icon;
                }
                unset($game->icon_zh);
                return $game;
            });
            if ($request->filled('isBookmark')) {
                $tbl_game = $tbl_game->filter(function ($game) use ($request) {
                    return $game->isBookmark == $request->input('isBookmark');
                });
                $tbl_game = $tbl_game->sortBy([
                    ['isBookmark', 'desc'],
                    ['game_id', 'asc'],
                ])->values();
            }
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_game,
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
     * Select tbl_game.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function view(Request $request, string $type)
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
            'game_id' => 'required|integer',
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
            $tbl_game = Game::where( 'game_id', $request->input('game_id') )
                            ->where( 'status', 1 )
                            ->where( 'delete', 0 )
                            ->with('Gameplatform','gameType')
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
            $response = null;
            switch ($tbl_game->gameplatform_id) {
                case 1003: //Mega
                    $response = \Mega::download();
                    if ( !is_null($response['error']) ) {
                        return sendEncryptedJsonResponse(
                            [
                                'status' => false,
                                'message' => __('messages.unexpected_error'),
                                'error' => $response['error'],
                            ],
                            500
                        );
                    }
                    $response['result'] = 'https://m.mega57.com';
                    break;
                case 1004: //Jili
                    $response['result'] = null;
                    break;
                default:
                    break;
            }
            $tbl_game->game_name = __($tbl_game->Gameplatform->gameplatform_name.".".$tbl_game->game_name);
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'game' => $tbl_game,
                    'downloadurl' => $response['result'],
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
     * cronjob tbl_gamemember.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function playercronjob(Request $request)
    {
        try {
            $tbl_player = Gamemember::with('Member','Game', 'Game.gameType','Gameplatform')
                                    ->where('status', 1)
                                    ->where('delete', 0)
                                    ->get();
            return [
                'status' => true,
                'message' => __('messages.list_success'),
                'error' => "",
                'player' => $tbl_player,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * cronjob tbl_gamelog.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function gamelogcronjob(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gamemember_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return [
                'status' => false,
                'message' => __('messages.unvalidation'),
                'error' => $validator->errors(),
            ];
        }
        try {
            return \Gamehelper::gamelog( $request, $request->input('gamemember_id') );
        } catch (\Illuminate\Database\QueryException $e) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }

}
