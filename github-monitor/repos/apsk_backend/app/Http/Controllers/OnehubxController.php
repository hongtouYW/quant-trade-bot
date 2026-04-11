<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Gameplatform;
use App\Models\Gamelog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OnehubxController extends Controller
{
    /**
     * game list.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request, string $type)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
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
            $response = \Onehubx::gamelist();
            foreach ($response['data'] as $key => $game) {
                $tbl_game = Game::where( 'gametarget_id', $game['game_id'] )
                                ->where( 'gameplatform_id', 1005 )
                                ->first();
                if ( $tbl_game )
                {
                    continue;
                }
                $game_name = $game['game_name'];
                $game_name = str_replace(' ', '', $game_name );
                $game_name = str_replace('-', '', $game_name );
                $game_name = strtolower( $game_name );
                $tbl_game = Game::create([
                    'gameplatform_id' => 1005,
                    'gametarget_id' => $game['game_id'],
                    'game_name' => $game_name,
                    'icon' => $game['game_thumbnail'],
                    'type' => $game['type'],
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }
            $tbl_game = Game::where( 'gameplatform_id', 1005)->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $response,
                    'game' => $tbl_game,
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
