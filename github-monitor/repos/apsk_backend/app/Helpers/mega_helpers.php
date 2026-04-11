<?php

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
use App\Models\Gamelog;
use App\Models\Gameerror;
use App\Models\Megacallback;
use App\Models\Provider;
use Carbon\Carbon;

class Mega
{
    private static string $agentLoginId = "Mega1-5844";
    private static string $sn = "ld00";
    private static string $secretCode = "uiK+QYBIl39bzWz6xzMmZlFgOLs=";
    private static string $url = "https://mgapi-ali.yidaiyiluclub.com/mega-cloud/api/";

    private static function neworderid(int $length = 15)
    {
        do {
            $orderid = '';
            $orderid .= random_int(1, 9);
            for ($i = 1; $i < $length; $i++) {
                $orderid .= random_int(0, 9);
            }
        } while (Gamepoint::where('orderid', $orderid)->exists());
        return $orderid;
    }

    private static function method( $method )
    {
        switch ($method) {
            case 'register':
                return "open.mega.user.create";
                break;
            case 'view':
                return "open.mega.user.get";
                break;
            case 'enable':
                return "open.mega.user.enable";
                break;
            case 'disable':
                return "open.mega.user.disable";
                break;
            case 'block':
                return "open.mega.user.block";
                break;
            case 'unblock':
                return "open.mega.user.unblock";
                break;
            case 'status':
                return "open.mega.user.isOnline";
                break;
            case 'balance':
                return "open.mega.balance.get";
                break;
            case 'transfer':
                return "open.mega.balance.transfer";
                break;
            case 'transfer.auto':
                return "open.mega.balance.auto.transfer.out";
                break;
            case 'transfer.record':
                return "open.mega.balance.transfer.query";
                break;
            case 'logout':
                return "open.mega.user.logout";
                break;
            case 'game.log':
                return "open.mega.player.game.log.url.get";
                break;
            case 'download':
                return "open.mega.app.url.download";
                break;
            case 'player.report':
                return "open.mega.player.total.report";
                break;
            case 'agent.report':
                return "open.mega.agent.total.report";
                break;
            case 'game.order':
                return "open.mega.game.order.page";
                break;
            default:
                return "";
                break;
        }
    }

    private static function digest( $params, $method )
    {
        switch ($method) {
            case 'open.mega.user.create':
                return md5( $params['random'] . self::$sn . self::$secretCode  );
                break;
            case 'open.mega.user.get':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.user.enable':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.user.disable':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.user.block':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.user.unblock':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.user.isOnline':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.balance.get':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.balance.transfer':
                return md5( $params['random'] . self::$sn . $params["loginId"] . $params['amount'] . self::$secretCode );
                break;
            case 'open.mega.balance.auto.transfer.out':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.balance.transfer.query':
                return md5( $params['random'] . self::$sn . self::$secretCode );
                break;
            case 'open.operator.user.login':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.user.logout':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.player.game.log.url.get':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            case 'open.mega.app.url.download':
                return md5( $params['random'] . self::$sn . self::$secretCode );
                break;
            case 'open.mega.player.total.report':
                return md5( $params['random'] . self::$sn . self::$agentLoginId . self::$secretCode );
                break;
            case 'open.mega.agent.total.report':
                return md5( $params['random'] . self::$sn . self::$agentLoginId . self::$secretCode );
                break;
            case 'open.mega.game.order.page':
                return md5( $params['random'] . self::$sn . $params["loginId"] . self::$secretCode );
                break;
            default:
                return "";
                break;
        }
    }

    private static function buildparams($params, $method)
    {
        $params["random"] = (string) round(microtime(true) * 1000);
        $params["digest"] = self::digest( $params, $method );
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];
    }

    private static function logError($url, $params, $response)
    {
        Gameerror::create([
            'api'        => $url,
            'request'    => json_encode($params, JSON_UNESCAPED_SLASHES),
            'response'   => is_string($response) ? $response : 
                            (json_encode($response, JSON_UNESCAPED_SLASHES) ?: '[Invalid JSON]'),
            'status'     => 1,
            'delete'     => 0,
            'created_on' => now(),
            'updated_on' => now(),
        ]);
    }

    private static function curl($params, $method)
    {
        $jsonPayload = self::buildparams($params, $method);
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post( self::$url, $jsonPayload)->json();
            if (!is_array($response)) {
                self::logError(self::$url.$method, $params, [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $response,
                    'code' => 500
                ]);
            } elseif (!is_null($response['error'])) {
                self::logError(self::$url.$method, $params, [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $response['error'],
                    'code' => 500
                ]);
            }
            return $response;
        } catch (\Exception $e) {
            self::logError( self::$url.$method, $params, [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
                'code' => 500
            ]);
            return [
                'error' => __('messages.unexpected_error'),
                'message' => $e->getMessage(),
            ];
        }
    }

    public static function download()
    {
        $params["agentLoginId"] = self::$agentLoginId;
        $params["sn"] = self::$sn;
        $response = self::curl($params, self::method( "download" ) );
        return $response;
    }

    public static function balance( $tbl_player )
    {
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $response = self::curl($params, self::method( "balance" ) );
        return $response;
    }

    public static function providerlist()
    {
        $tbl_provider = Provider::where('status', 1 )
                                ->where('delete', 0 )
                                ->where('gameplatform_id', 1003 )
                                ->first();
        if (!$tbl_provider) {
            return [
                'status' => false,
                'message' => __('provider.no_data_found'),
                'error' => __('provider.no_data_found'),
                'code' => 500
            ];
        }
        $response = self::download();
        if ( !is_null($response['error']) ) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['error'],
                'code' => 500
            ];
        }
        if ( $tbl_provider->download !== 'https://m.mega57.com' ) {
            $tbl_provider->update([
                'download' => 'https://m.mega57.com',
                'updated_on' => now(),
            ]);
        }
        $response['status'] = true;
        $response['message'] = __('messages.list_success');
        $response['error'] = "";
        $response['code'] = 200;
        return $response;
    }

    public static function register( $player_name, $player_pass )
    {
        $params["agentLoginId"] = self::$agentLoginId;
        $params["sn"] = self::$sn;
        $params['nickname'] = $player_name;
        $response = self::curl($params, self::method( "register" ) );
        if ( !is_null($response['error']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['error'],
                'code'    => 500
            ];
        }
        if ( !isset( $response['result'] ) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => isset( $response['error'] ) ? $response['error'] : null,
                'code'    => 500
            ];
        }
        $response['status'] = true;
        $response['message'] = __('messages.add_success');
        $response['error'] = "";
        $response['code'] = 200;
        $response['uid'] = $response['result']['userId'];
        $response['loginId'] = $response['result']['loginId'];
        $response['password'] = $player_pass;
        $response['paymentpin'] = null;
        return $response;
    }

    public static function view($tbl_player)
    {
        $deeplink['android'] = "lobbymegarelease://?account=".$tbl_player->loginId.
                                "&password=".decryptPassword( $tbl_player->pass );
        $deeplink['ios'] = "lobbymegarelease://?account=".$tbl_player->loginId.
                            "&password=".decryptPassword( $tbl_player->pass );
        $balance = self::balance( $tbl_player );
        if ( !is_null($balance['error']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $balance['error'],
                'code'    => 500
            ];
        }
        if ( !isset( $balance['result'] ) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => isset( $balance['error'] ) ? $balance['error'] : null,
                'code'    => 500
            ];
        }
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $response = self::curl($params, self::method( "view" ) );
        if ( !is_null($response['error']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['error'],
                'code'    => 500
            ];
        }
        if ( !isset( $response['result'] ) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => isset( $response['error'] ) ? $response['error'] : null,
                'code'    => 500
            ];
        }
        $status = $response['result']['userStatus'] > 0 ? 1 : 0;
        $player = [];
        if (!is_null($status) && $tbl_player->status !== $status) {
            $player['status'] = $status;
            if ($status === 0)
            {
                $player['delete'] = 1;
            }
        }
        if (!is_null($balance['result']) && $tbl_player->balance !== $balance['result']) {
            $player['balance'] = $balance['result'];
        }
        if (!empty($player)) {
            if ( isset( $player['balance'] ) ) {
                $player['has_balance'] = $player['balance'] > 0 ? 1 : 0;
            }
            $tbl_player->update($player);
            $tbl_player = $tbl_player->fresh();
        }
        $response['status'] = true;
        $response['message'] = __('messages.list_success');
        $response['error'] = "";
        $response['code'] = 200;
        $response['data'] = $tbl_player->load('Member','Provider', 'Gameplatform');
        $response['deeplink'] = $deeplink;
        return $response;
    }

    public static function transfer($tbl_player, $amount, $ip )
    {
        // Round up (flooring) to 2 decimals
        $originalAmount = (float) $amount;
        $roundedAmount = floor($originalAmount * 100) / 100;
        $roundedAmount = (float) number_format($roundedAmount, 2, '.', '');
        $remain = $originalAmount - $roundedAmount;
        if ($roundedAmount === 0.00) {
            $response['status'] = true;
            $response['message'] = __('messages.withdraw_success');
            $response['error'] = "";
            $response['code'] = 200;
            $response['orderid'] = self::neworderid();
            $response['data'] = $tbl_player;
            $response['remain'] = 0.0000;
            return $response;
        }
        $orderid = self::neworderid();
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $params['amount'] = $roundedAmount;
        $params['checkBizId'] = "1";
        $params['ip'] = $ip;
        $response = self::curl($params, self::method( "transfer" ) );
        if ( !is_null($response['error']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['error'],
                'code'    => 500,
                'orderid' => $orderid,
                'remain'  => $amount,
            ];
        }
        $message = $amount > 0 ? __('messages.deposit_success') : __('messages.withdraw_success');
        return [
            'status'  => true,
            'message'  => $message,
            'error'  => "",
            'code'  => 200,
            'orderid'  => $orderid,
            'data'  => $tbl_player,
            'remain'  => $remain,
        ];
    }

    public static function enable($tbl_player, $enable)
    {
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $method = $enable === 1 ? "enable": "disable";
        $response = self::curl($params, self::method( $method ) );
        if ( !is_null($response['error']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['error'],
                'code'    => 500
            ];
        }
        $response['status'] = true;
        $response['message'] = $enable === 0 ? __('gamemember.gamemember_deleted_successfully') : "";
        $response['error'] = "";
        $response['code'] = 200;
        return $response;
    }

    public static function block(Request $request)
    {
        $tbl_player = Gamemember::where( 'gamemember_id', $request->input('gamemember_id') )
                                ->with( 'Game' )
                                ->where( 'delete', 0 )
                                ->first();
        if (!$tbl_player) {
            return [
                'status' => false,
                'message' => __('gamemember.player_no_found'),
                'error' => __('gamemember.player_no_found'),
            ];
        }
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $block = (int) $request->input('block') === 1 ? "block": "unblock";
        $response = self::curl($params, self::method( $block ) );
        return $response;
    }

    public static function status(Request $request)
    {
        $tbl_player = Gamemember::where( 'gamemember_id', $request->input('gamemember_id') )
                                ->with( 'Game' )
                                ->first();
        if (!$tbl_player) {
            return [
                'status' => false,
                'message' => __('gamemember.player_no_found'),
                'error' => __('gamemember.player_no_found'),
            ];
        }
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $response = self::curl($params, self::method( "status" ) );
        return $response;
    }

    public static function transferauto($tbl_player, $ip )
    {
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $params['checkBizId'] = "1";
        $params['ip'] = $ip;
        $response = self::curl($params, self::method( "transfer.auto" ) );
        $response['orderid'] = self::neworderid();
        return $response;
    }

    public static function transferrecord(Request $request, $tbl_player )
    {
        $params["agentLoginId"] = self::$agentLoginId;
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $params["startTime"] = $request->input('startdate');
        $params["endTime"] = $request->input('enddate');
        $params["timeZone"] = $request->filled('timezone') ? $request->input('timezone') : "2";
        $params["sortHint"] = $request->filled('sort') ? $request->input('sort') : "desc";
        $params["pageIndex"] = $request->filled('pageindex') ? $request->input('pageindex') : 1;
        $params["pageSize"] = $request->filled('pagesize') ? $request->input('pagesize') : 15;
        if ( $request->filled('etag') ) {
            $params["etag"] = $request->input('etag');
        }
        $response = self::curl($params, self::method( "transfer.record" ) );
        return $response;
    }

    public static function logout(Request $request, $tbl_player )
    {
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $response = self::curl($params, self::method( "logout" ) );
        return $response;
    }

    public static function gamelog(Request $request, $tbl_player )
    {
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $params["startTime"] = $request->input('startdate');
        $params["endTime"] = $request->input('enddate');
        $response = self::curl($params, self::method( "game.log" ) );
        return $response;
    }

    public static function playertotalreport(Request $request, $tbl_player )
    {
        $params["agentLoginId"] = self::$agentLoginId;
        $params["sn"] = self::$sn;
        $params["type"] = "1";
        $params["startTime"] = $request->input('startdate');
        $params["endTime"] = $request->input('enddate');
        $response = self::curl($params, self::method( "player.report" ) );
        return $response;
    }

    public static function agenttotalreport(Request $request, $tbl_player )
    {
        $params["agentLoginId"] = self::$agentLoginId;
        $params["sn"] = self::$sn;
        $params["type"] = "1";
        $params["startTime"] = $request->input('startdate');
        $params["endTime"] = $request->input('enddate');
        $response = self::curl($params, self::method( "agent.report" ) );
        return $response;
    }

    public static function gameorder( $tbl_player )
    {
        if ( $tbl_player->member->status !== 1 && $tbl_player->member->delete !== 0 ) {
            return [
                'status' => false,
                'message' => __('messages.profileinactive'),
                'error' => __('messages.profileinactive'),
                'code'    => 500
            ];
        }
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $params["startTime"] = Carbon::now()->setTimezone('Asia/Kuala_Lumpur')
                                     ->format('Y-m-d 00:00:00');
        $params["endTime"] = Carbon::now()->setTimezone('Asia/Kuala_Lumpur')
                                     ->format('Y-m-d 23:59:59');
        $params["pageIndex"] = 1;
        $params["pageSize"] = 10000;
        $response = self::curl($params, self::method( "game.order" ) );
        if ( !is_null($response['error']) ) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['error'],
                'code' => 500
            ];
        }
        $epsilon = 0.00001;
        $balance = $tbl_player->balance;
        $turnover = 0.00;
        foreach( $response['result']['items'] as $gamelog )
        {
            $betamount = (float) $gamelog['bet'];
            $winloss = (float) $gamelog['win'];
            if (abs($betamount) < $epsilon && abs($winloss) < $epsilon) {
                continue;
            }
            $before_balance = (float) $gamelog['beginBalance'];
            $after_balance = (float) $gamelog['endBalance'];
            if (abs($before_balance) < $epsilon && abs($after_balance) < $epsilon) {
                $before_balance = $balance;
                $after_balance = $balance + ($winloss - $betamount);
            }
            $balance = $after_balance;
            $existgamelog = Gamelog::where('gamelogtarget_id', $gamelog['id'] )
                                    ->where('gamemember_id', $tbl_player->gamemember_id )
                                    ->first();
            if ( !$existgamelog ) {
                $remark = $gamelog['gameName'];
                $tableid = (int) $gamelog['logDataType'] !== 1 && (int) $gamelog['logDataType'] !== 3
                        ? $gamelog['tableId']
                        : null;
                $tbl_gamelog = Gamelog::create([
                    'gamelogtarget_id' => $gamelog['id'],
                    'gamemember_id' => $tbl_player->gamemember_id,
                    'game_id' => 1,
                    'tableid' => $tableid,
                    'before_balance' => $before_balance,
                    'after_balance' => $after_balance,
                    'betamount' => $betamount,
                    'winloss' => $winloss,
                    'startdt' => $gamelog['createTime'],
                    'enddt' => $gamelog['createTime'],
                    'remark' => $remark,
                    'agent_id' => $tbl_player->member->agent_id,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                // vip 改成打码量
                $turnover += $betamount;
            }
        }
        if ( $turnover > 0 ) {
            // VIP Score
            $tbl_score = AddScore( $tbl_player->member, 'deposit', $turnover);
            // Commission
            $salestarget = AddCommission( $tbl_player->member, $turnover );
        }
        // $tbl_gamelog = Gamelog::where('gamemember_id', $tbl_player->gamemember_id );
        // $tbl_gamelog = queryBetweenDateEloquent($tbl_gamelog, $request, 'startdt');
        // $tbl_gamelog = $tbl_gamelog->orderBy('startdt', 'desc');
        // $tbl_gamelog = $tbl_gamelog->get();
        return [
            'status'  => true,
            'message' => __('messages.list_success'),
            'error'   => "",
            // 'gamelog' => $tbl_gamelog,
            'code'    => 200
        ];
    }

    public static function syncgamelog( $tbl_player)
    {
        $lastlogin_on = Carbon::parse($tbl_player->lastlogin_on);
        $startdt = $lastlogin_on->copy()->format('Y-m-d H:00:00');
        $enddt   = $lastlogin_on->copy()->format('Y-m-d H:59:59');
        $params['loginId'] = $tbl_player->loginId;
        $params["sn"] = self::$sn;
        $params["startTime"] = $startdt;
        $params["endTime"] = $startdt;
        $params["pageIndex"] = 1;
        $params["pageSize"] = 10000;
        $response = self::curl($params, self::method( "game.order" ) );
        if ( !isset($response['error']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500,
            ];
        }
        if ( !is_null($response['error']) ) {
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['error'],
                'code' => 500
            ];
        }
        $epsilon = 0.00001;
        $balance = $tbl_player->balance;
        $turnover = 0.00;
        $response['result']['items'] = array_filter($response['result']['items'], function($gamelog) use ($epsilon) {
            $betamount = (float) $gamelog['bet'];
            $winloss = (float) $gamelog['win'];
            return !(abs($betamount) < $epsilon && abs($winloss) < $epsilon);
        });
        $data = [];
        foreach ($response['result']['items'] as $gamelog) {
            $betamount = (float) $gamelog['bet'];
            $winloss = (float) $gamelog['win'];
            $before_balance = (float) $gamelog['beginBalance'];
            $after_balance = (float) $gamelog['endBalance'];
            if (abs($before_balance) < $epsilon && abs($after_balance) < $epsilon) {
                $before_balance = $balance;
                $after_balance = $balance + ($winloss - $betamount);
            }
            $balance = $after_balance;
            $gamelog['gamelogtarget_id'] = $gamelog['id'];
            $gamelog['gamemember_id'] = $tbl_player->gamemember_id;
            $gamelog['game_id'] = 1;
            $gamelog['tableid'] = (int) $gamelog['logDataType'] !== 1 && (int) $gamelog['logDataType'] !== 3
                        ? $gamelog['tableId']
                        : null;
            $gamelog['before_balance'] = $before_balance;
            $gamelog['after_balance'] = $after_balance;
            $gamelog['betamount'] = $betamount;
            $gamelog['winloss'] = $winloss;
            $gamelog['startdt'] = $gamelog['createTime'];
            $gamelog['enddt'] = $gamelog['createTime'];
            $gamelog['remark'] = $gamelog['gameName'];
            $gamelog['agent_id'] = optional($tbl_player->member)->agent_id;
            $gamelog['status'] = 1;
            $gamelog['delete'] = 0;
            $gamelog['created_on'] = now();
            $gamelog['updated_on'] = now();
            $data[] = $gamelog;
        }
        return [
            'status'  => true,
            'message' => __('messages.list_success'),
            'error'   => "",
            'data' => $data,
            'code'    => 200
        ];
    }
}
