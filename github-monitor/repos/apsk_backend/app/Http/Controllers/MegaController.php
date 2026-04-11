<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Events\MegaEvent;
use App\Models\Credit;
use App\Models\Member;
use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Megacallback;
use App\Models\Gameerror;
use Carbon\Carbon;

class MegaController extends Controller
{

    public function callback(Request $request)
    {
        try {
            $jsonpayload = preg_replace('/^json=/', '', $request->getContent() );
            // Megacallback::create([
            //     // 'response' => json_encode($request->all()),
            //     'response' => $jsonpayload,
            //     'error' => $request->has('error') ? 1 : 0,
            //     'created_on' => now(),
            //     'updated_on' => now(),
            // ]);
            $datarequest = json_decode( $jsonpayload );
            if ( !$datarequest->params ) {
                Gameerror::create([
                    'api'        => "https://mgapi-ali.yidaiyiluclub.com/mega-cloud/api/open.operator.user.login",
                    'request'    => $jsonpayload,
                    'response'   => null,
                    'status'     => 1,
                    'delete'     => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                return [
                    'success'   => 0,
                    'errorCode' => "700",
                ];
            }
            if ( $datarequest->method === "open.operator.user.login" ) {
                return $this->login($datarequest);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::info('API Mega CallBack Request', $e->getMessage() );
            return [
                'success'   => 0,
                'errorCode' => 37101,
            ];
        }
    }

    public function login($datarequest)
    {
        $tbl_player = Gamemember::where( 'loginId', $datarequest->params->loginId )
                                ->with( 'Game' )
                                ->first();
        if (!$tbl_player) {
            return [
                'success'   => 0,
                'errorCode' => 21102,
            ];
        }
        if ( $datarequest->params->password !== decryptPassword( $tbl_player->pass ) ) {
            return [
                'success'   => 0,
                'errorCode' => 2218,
            ];
        }
        if ($tbl_player->status !== 1 || $tbl_player->delete === 1) {
            return [
                'success'   => 0,
                'errorCode' => 2217,
            ];
        }
        $token = issueApiTokens($tbl_player, "gamemember");
        return [
            'id' => $datarequest->id,
            'result' => [
                "success" => "1",
                "sessionId" => (string) round(microtime(true) * 1000),
                "msg" => "登录成功",
            ],
            'error' => null,
            'jsonrpc' => '2.0',
        ];
    }
}
