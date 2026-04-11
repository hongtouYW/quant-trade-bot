<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Gamelog;
use App\Models\Gamebookmark;
use App\Models\Gameplatform;
use App\Models\Gameplatformaccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class GameplatformController extends Controller
{
    /**
     * gameplatform list.
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
            $tbl_table = DB::table('tbl_'.$type)
                        ->where( $type.'_id', $request->input($type.'_id') )
                        ->first();
            $agent_id = !is_null( $tbl_table->agent_id ) ? $tbl_table->agent_id : 0;
            $gameplatform_ids = Gameplatformaccess::where( 'agent_id', $agent_id )
                    ->where('can_use', 1)
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->pluck('gameplatform_id')
                    ->toArray();
            $tbl_gameplatform = Gameplatform::where( 'status', 1 )
                                            ->where( 'delete', 0 )
                                            ->whereIn('gameplatform_id', $gameplatform_ids)
                                            ->get();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_gameplatform,
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
