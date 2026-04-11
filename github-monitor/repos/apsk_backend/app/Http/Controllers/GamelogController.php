<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Gamemember;
use App\Models\Gameplatform;
use App\Models\Gamelog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class GamelogController extends Controller
{
    /**
     * Synchronize gamelog.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncgamelog(Request $request)
    {
        // $syncSecret = $request->header('X-Sync-Secret');
        // if (!$syncSecret || $syncSecret !== env('SYNC_SECRET')) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => __('messages.Unauthorized'),
        //         'error' => __('messages.Unauthorized'),
        //         'code' => 403,
        //     ], 403);
        // }
        // $acceptLang = $request->header('Accept-Language');
        // if ($acceptLang) {
        //     app()->setLocale($acceptLang);
        // } else {
        //     app()->setLocale('en');
        // }
        try {
            $console = [];
            $DaysAgo = Carbon::now()->startOfDay();
            Gamemember::with('Member')
            ->where('status', 1)
            ->where('delete', 0)
            ->whereNotNull('lastlogin_on')
            ->where('lastlogin_on', '>=', $DaysAgo) //prevent lag old record ignore
            ->where(function ($q) {
                $q->whereNull('lastsync_on') // case 1: never synced
                ->orWhereColumn('lastlogin_on', '>=', 'lastsync_on'); // case 2: login after sync
            })
            ->chunk(100, function($tbl_player) use (&$console) {
                $turnover = 0.0000;
                foreach ($tbl_player as $key => $player) {
                    $now = Carbon::now()->format('Y-m-d H:i:s');
                    $turnover = 0.00;
                    $player->update([
                        'lastsync_on' => Carbon::now()->subHour()->endOfHour(),
                        'updated_on' => now(),
                    ]);
                    $player = $player->fresh();
                    switch ($player->gameplatform_id) {
                        case 1003: //Mega
                            $response = \Mega::syncgamelog( $player );
                            break;
                        case 1004: //Jili
                            $response = \Jili::syncgamelog( $player );
                            break;
                        case 1005: //Onehubx
                            $response = \Onehubx::syncgamelog( $player );
                            break;
                        default:
                            $console[] = "[$now] - ".
                                        strval($player->gamemember_id).
                                        " : ".__('game.no_platform_found');
                            continue 2;
                            break;
                    }
                    if ( !$response['status'] ) {
                        $console[] = "[$now] - ".
                                    strval($player->gamemember_id).
                                    " : ".__('gamelog.no_data_found') .
                                    " - ". $response['error'];
                        continue;
                    }
                    if ( empty( $response['data'] ) ) {
                        $console[] = "[$now] - ".
                                    strval($player->gamemember_id).
                                    " : ".__('gamelog.no_data_found');
                        continue;
                    }
                    $response['data'] = collect($response['data'])
                        ->sortBy('startdt')
                        ->values()
                        ->all();
                    foreach( $response['data'] as $gamelog ) {
                        Gamelog::firstOrCreate(
                            [
                                'gamemember_id' => $player->gamemember_id,
                                'gamelogtarget_id' => $gamelog['gamelogtarget_id'],
                            ],
                            $gamelog
                        );
                        // vip 改成打码量
                        $turnover += $gamelog['betamount'];
                    }
                    if ( $turnover > 0 ) {
                        // VIP Score
                        $tbl_score = AddScore( $player->member, 'deposit', $turnover);
                        // Commission
                        $salestarget = AddCommission( $player->member, $turnover );
                    }
                    $console[] = "[$now] - ".
                                strval($player->gamemember_id).
                                " : ".__('gamelog.gamelog_added_successfully');
                }
            });
            return response()->json([
                'status' => true,
                'message' => __('gamelog.sync_complete'),
                'error' => "",
                'data' => $console,
                'code' => 200,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }
}
