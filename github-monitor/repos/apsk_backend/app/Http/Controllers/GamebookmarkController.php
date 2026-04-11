<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Game;
use App\Models\Gamebookmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GamebookmarkController extends Controller
{
    /**
     * Search tbl_gamebookmark.
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
            'user_id' => 'required|integer',
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
            $checkuser = CheckAvailabilityUser( $request->input('user_id'), $type);
            if ( $checkuser ) {
                return sendEncryptedJsonResponse(
                    $checkuser,
                    401
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
            $tbl_gamebookmark = Gamebookmark::where('member_id', $request->input('member_id'))
                                            ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_gamebookmark,
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
     * Add tbl_gamebookmark.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request, string $type)
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
            'member_id' => 'required|integer',
            'game_id' => 'required|integer',
            'gamebookmark_name' => 'required|string|max:255',
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
            $tbl_gamebookmark = Gamebookmark::where('member_id', $request->input('member_id'))
                                            ->where('game_id', $request->input('game_id'))
                                            ->where( 'status', 1 )
                                            ->where( 'delete', 0 )
                                            ->first();
            if ( $tbl_gamebookmark ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('gamebookmark.gamebookmark_exist'),
                        'error' => __('gamebookmark.gamebookmark_exist'),
                    ],
                    400
                );
            }
            $tbl_gamebookmark = Gamebookmark::create([
                'gamebookmark_name' => $request->input('gamebookmark_name'),
                'game_id' => $request->input('game_id'),
                'member_id' => $request->input('member_id'),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_gamebookmark,
                    'status' => true,
                    'message' => __('messages.add_success'),
                    'error' => "",
                ],
                201
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
     * Delete tbl_gamebookmark.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request, string $type)
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
            'gamebookmark_id' => 'required|integer',
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
            $tbl_gamebookmark = Gamebookmark::where('gamebookmark_id', $request->input('gamebookmark_id'))
                                            ->first();
            if ( !$tbl_gamebookmark ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('gamebookmark.no_data_found'),
                        'error' => __('gamebookmark.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_gamebookmark->update([
                'status' => 0,
                'delete' => 1,
                'updated_on' => now(),
            ]);
            return sendEncryptedJsonResponse(
                [
                    'data' => $tbl_gamebookmark,
                    'status' => true,
                    'message' => __('messages.delete_success'),
                    'error' => "",
                ],
                201
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
