<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Credit;
use App\Models\Member;
use App\Models\Game;
use App\Models\Gametype;
use App\Models\Gamemember;
use App\Models\Gamepoint;
use App\Models\Gamelog;
use App\Models\Gameerror;
use App\Models\Jilicallback;
use Carbon\Carbon;

class Jili
{
    private static string $AgentId = "TitanTR38_apsk_MYR";
    private static string $url = "https://uat-wb-api-2.kijl788du.com/api1/"; // UAT
    private static string $AgentKey = "e9f83b8bb2d6ffb23ccac0bae8784bdaa819e309"; // UAT
    // private static string $url = "https://wb-api-2.jiscc88.com/api1/"; // Production
    // private static string $AgentKey = "baaa2ed8ebbeb824262a6c144ee3fbaab167b7fa"; // Production
    // private static string $randomtext1 = "123456";
    // private static string $randomtext2 = "abcdef";
    private static string $randomtext1 = "000000";
    private static string $randomtext2 = "000000";
    private static string $md5string;

    // private static function Key($params, $dateStr)
    // {
    //     $keyG = md5($dateStr . self::$AgentId . self::$AgentKey);
    //     // $md5string = md5( http_build_query($params) . $keyG);
    //     $md5string = md5( str_replace('%3A', ':', http_build_query($params)) . $keyG);
    //     return self::$randomtext1 . $md5string . self::$randomtext2;
    // }

    private static function Key($params, $dateStr, $url)
    {
        $keyG = md5($dateStr . self::$AgentId . self::$AgentKey);

        // remove fields NOT part of signature
        unset($params['Key'], $params['dateStr'], $params['agentKey']);

        if ( $url === self::$url."GetUserBetRecordByTime") {
            $query =
                "StartTime={$params['StartTime']}" .
                "&EndTime={$params['EndTime']}" .
                "&Page={$params['Page']}" .
                "&PageLimit={$params['PageLimit']}" .
                "&Account={$params['Account']}" .
                "&AgentId=" . self::$AgentId;
        } else {
            $query = str_replace('%3A', ':', http_build_query($params));
        }

        $md5string = md5($query . $keyG);

        return self::$randomtext1 . $md5string . self::$randomtext2;
    }

    private static function curl($url, $params = [])
    {
        // $dateStr = Carbon::now('Etc/GMT+4')->format('ymd');
        // 用多明尼加 / 波多黎各的時區（UTC-4）
        $date = Carbon::now('America/Santo_Domingo');
        // 不補零 => y(2位年) . n(月無前導零) . j(日無前導零)
        // $dateStr = $date->format('y') . $date->format('n') . $date->format('j');
        $dateStr = $date->format('y') . $date->format('m') . $date->format('j');
        $params['AgentId'] = self::$AgentId;
        $params['Key'] = self::Key($params, $dateStr, $url);
        $params['dateStr'] = $dateStr;
        $params['agentKey'] = self::$AgentKey;
        // $queryString = is_array($params) ? http_build_query($params) : $params;
        $queryString = is_array($params) ? str_replace('%3A', ':', http_build_query($params)) : $params;
        $fullUrl = $url . '?' . $queryString;
        try {
            $headers = [
                "Content-Type: application/x-www-form-urlencoded"
            ];
            $curl = curl_init( $url );
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($curl, CURLOPT_POSTFIELDS, !empty($params)
                ? str_replace('%3A', ':', http_build_query($params))
                : ""
            );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($curl);
            if ($response === false) {
                $error = curl_error($curl);
                curl_close($curl);
                self::logError($url, $params, $error);
                return [
                    'url' => $fullUrl,
                    'success' => false,
                    'message' => __('messages.unexpected_error') .":". $error,
                ];
            }
            curl_close($curl);
            $response = safe_json_decode($response);
            if (is_null($response) || !isset($response['ErrorCode'])) {
                self::logError($url, $params, $response ?? '[Invalid JSON]');
                return [
                    'url' => $fullUrl,
                    'success' => false,
                    'message' => __('messages.unexpected_error'),
                ];
            }
            if ( $response['ErrorCode'] !== 0 ) {
                self::logError($url, $params, $response);
                return [
                    'url' => $fullUrl,
                    'success' => false,
                    'message' => __('messages.unexpected_error') .":". $response['Message'],
                ];
            }
            $response['url'] = $fullUrl;
            $response['params'] = $params;
            return $response;
        } catch (\Illuminate\Database\QueryException $e) {
            self::logError($url, $params, [
                'ErrorCode' => 2,
                'Message' => $e->getMessage(),
                'Data' => null,
            ]);
            return [
                'url' => $fullUrl,
                'success' => false,
                'message' => __('messages.unexpected_error') .":". $e->getMessage(),
            ];
        }
    }

    private static function logError($url, $params, $response)
    {
        Gameerror::create([
            'api'        => $url,
            'request'    => json_encode($params, JSON_UNESCAPED_SLASHES),
            // 'response'   => is_string($response) ? $response : json_encode($response),
            'response'   => is_string($response) ? $response :
                (json_encode($response, JSON_UNESCAPED_SLASHES) ?: '[Invalid JSON]'),
            'status'     => 1,
            'delete'     => 0,
            'created_on' => now(),
            'updated_on' => now(),
        ]);
    }

    private static function lang($lang)
    {
        switch ($lang) {
            case 'en':
                return "en-US";
                break;
            case 'zh':
                return "zh-CN";
                break;
            default:
                return "en-US";
                break;
        }
    }

    private static function newloginId(int $length = 15)
    {
        $response = null;
        do {
            $loginId = '';
            $loginId .= random_int(1, 9);
            for ($i = 1; $i < $length; $i++) {
                $loginId .= random_int(0, 9);
            }
            $send['Accounts'] = $loginId;
            $response = self::curl( self::$url."GetMemberInfo", $send);
            if ( !isset( $response['ErrorCode'] ) ) {
                $response['ErrorCode'] = 1;
                $response['Message'] = $response['Message'];
                $response['loginId'] = null;
                return $response;
            }
            if ( !isset( $response['Data'][0]['Status'] ) ) {
                $response['ErrorCode'] = 1;
                $response['Message'] = 'Status Fail';
                $response['loginId'] = null;
                return $response;
            }
            $status = (int) $response['Data'][0]['Status'] ?? null;
            if ( !$status )
            {
                $response['ErrorCode'] = 1;
                $response['Message'] = 'Get Status Fail';
                $response['loginId'] = null;
                $response['RealStatus'] = $status;
                return $response;
            }
        } while ( $status === 1 || $status === 2 || $response['ErrorCode'] === 101 );
        $response['ErrorCode'] = 0;
        $response['Message'] = 'Success';
        $response['uid'] = $loginId;
        $response['loginId'] = $loginId;
        return $response;
    }

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

    private static function gametype($gametype)
    {
        switch ($gametype) {
            case 1:
                return 2;
                break;
            case 2:
                return 18;
                break;
            case 3:
                return 19;
                break;
            case 5:
                return 12;
                break;
            case 8:
                return 21;
                break;
            default:
                return 21;
                break;
        }
    }

    public static function gamelist( $tbl_provider )
    {
        $response = self::curl( self::$url."GetGameList");
        if ( !isset($response['ErrorCode']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( $response['ErrorCode'] !== 0 ) {
            return [
                'status'  => false,
                'message' => $response['Message'],
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        if ( !$response['Data'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        $allgames = [];
        foreach ($response['Data'] as $data) {
            $allgames[] = [
                'gameplatform_id' => 1004,
                'game_name'       => $data['name']['en-US'],
                'provider_id'     => $tbl_provider->provider_id,
                'gametarget_id'   => $data['GameId'],
                'icon'            => null,
                'type'            => self::gametype($data['GameCategoryId']),
            ];
        }
        $response['status'] = true;
        $response['message'] = __('messages.list_success');
        $response['error'] = "";
        $response['data'] = $allgames;
        $response['code'] = 200;
        return $response;
        // $editGames = [];
        // foreach ($response['Data'] as $data) {
        //     $tbl_game = Game::where('gameplatform_id', 1004)
        //                     ->where('provider_id', $provider_id )
        //                     ->where('gametarget_id', $data['GameId'] )
        //                     ->first();
        //     if ( $tbl_game ) {
        //         $editGames = [];
        //         if ( $tbl_game->gametarget_id !== $data['GameId'] ) {
        //             $editGames['gametarget_id'] = $data['GameId'];
        //         }
        //         // if ( $tbl_game->status !== (int) $data['game_status'] ) {
        //         //     $editGames['status'] = $data['game_status'] === "1" ? 1 : 0;
        //         // }
        //         if (!empty($newGames)) {
        //             $editGames['updated_on'] = now();
        //             $tbl_game->update($editGames);
        //         }
        //         continue;
        //     }
        //     $newGames[] = [
        //         'gameplatform_id' => 1004,
        //         'game_name'       => $data['name']['en-US'],
        //         'provider_id'     => $provider_id,
        //         'gametarget_id'   => $data['GameId'],
        //         'icon'            => 'assets/img/game/WL.png',
        //         'type'            => self::gametype($data['GameCategoryId']),
        //         'status'          => 0,
        //         'delete'          => 0,
        //         'created_on'      => now(),
        //         'updated_on'      => now(),
        //     ];
        // }
        // if (!empty($newGames)) {
        //     Game::insert($newGames);
        // }
        // $response['status'] = true;
        // $response['message'] = __('messages.list_success');
        // $response['error'] = "";
        // $response['code'] = 200;
        // return $response;
    }

    public static function register()
    {
        $response = self::newloginId();
        if ( !$response['loginId'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['Message'],
                'code'    => 500
            ];
        }
        $loginId = $response['loginId'];
        $send['Account'] = $response['loginId'];
        $response = self::curl( self::$url."CreateMember", $send);
        if ( !isset($response['ErrorCode']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['error'],
                'code'    => 500
            ];
        }
        if ( $response['ErrorCode'] !== 0 ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        $response['status'] = true;
        $response['message'] = __('messages.add_success');
        $response['error'] = "";
        $response['code'] = 200;
        $response['uid'] = $loginId;
        $response['loginId'] = $loginId;
        $response['password'] = null;
        $response['paymentpin'] = null;
        return $response;
    }

    public static function view($tbl_player)
    {
        $send['Accounts'] = $tbl_player->loginId;
        $response = self::curl( self::$url."GetMemberInfo", $send);
        if ( !isset( $response['ErrorCode'] ) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( $response['ErrorCode'] !== 0 ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        if ( !isset( $response['Data'][0]['Status'] ) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        $player = [];
        if (!is_null($response['Data'][0]['Balance']) && $tbl_player->balance !== $response['Data'][0]['Balance']) {
            $player['balance'] = $response['Data'][0]['Balance'];
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
        $response['deeplink'] = null;
        return $response;
    }

    public static function login(Request $request, $tbl_player, $tbl_game)
    {
        $lang = self::lang( $request->getPreferredLanguage() );
        $send['Account'] = $tbl_player->loginId;
        $send['GameId'] = $tbl_game->gametarget_id;
        $send['Lang'] = $lang !== "" ? $lang: "en-US";
        $response = self::curl( self::$url."LoginWithoutRedirect", $send);
        if ( !isset( $response['ErrorCode'] ) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500,
                'response'=> $response
            ];
        }
        if ( $response['ErrorCode'] !== 0 ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        return [
            'status'  => true,
            'message' => __('messages.login_success'),
            'error'   => "",
            'code'    => 200,
            'url'     => $response['Data'],
        ];
        // $response['status'] = true;
        // $response['message'] = __('messages.login_success');
        // $response['error'] = "";
        // $response['code'] = 200;
        // $response['url'] = $response['Data'];
        // return $response;
    }

    public static function transfer($tbl_player, $amount, $type = 1)
    {
        if ((float) $amount === 0.0000) {
            $response['status'] = true;
            $response['message'] = __('messages.withdraw_success');
            $response['error'] = "";
            $response['code'] = 200;
            $response['orderid'] = self::neworderid();
            $response['data'] = $tbl_player;
            $response['remain'] = 0.0000;
            return $response;
        }
        // 转账类型
        // 1: 从 游戏商 转移额度到 平台商 (不看 amount 值，全部转出)
        // 2: 从 平台商 转移额度到 游戏商
        // 3: 从 游戏商 转移额度到 平台商
        $orderid = self::neworderid();
        switch ($type) {
            case 1:
                $message = __('messages.withdraw_success');
                break;
            case 2:
                $message = __('messages.deposit_success');
                break;
            case 3:
                $message = __('messages.withdraw_success');
                break;
            default:
                return [
                    'status'  => false,
                    'message' => "invalid TransferType",
                    'error' => "invalid TransferType",
                    'code'    => 400,
                    'orderid' => $orderid,
                    'remain'  => $amount,
                ];
                break;
        }
        $send['Account'] = $tbl_player->loginId;
        $send['TransactionId'] = (string) $orderid;
        $send['Amount'] = $amount;
        $send['TransferType'] = $type;
        $response = self::curl( self::$url."ExchangeTransferByAgentId", $send);
        $logFile = storage_path('logs/jili_debug.log');
        file_put_contents($logFile, "jili transfer: " . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
        if ( !isset( $response['ErrorCode'] ) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500,
                'orderid' => $orderid,
                'remain'  => $amount,
            ];
        }
        if ( $response['ErrorCode'] !== 0 ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['Message'],
                'code'    => 500,
                'orderid' => $orderid,
                'remain'  => $amount,
            ];
        }
        return [
            'status'  => true,
            'message'  => $message,
            'error'  => "",
            'code'  => 200,
            'orderid'  => $orderid,
            'data'  => $tbl_player,
            'remain'  => 0.0000,
        ];
    }

    public static function kick($tbl_player)
    {
        $send['Account'] = $tbl_player->uid;
        $response = self::curl( self::$url."KickMember", $send);
        $logFile = storage_path('logs/jili_debug.log');
        file_put_contents($logFile, "Kick Response: " . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);
        return $response;
    }

    public static function kickall(Request $request)
    {
        $tbl_game = Game::where( 'game_id', $request->input('game_id') )
            ->where( 'gameplatform_id', 1004 )
            ->where( 'status', 1 )
            ->where( 'delete', 0 )
            ->first();
        if (!$tbl_game) {
            return [
                'status' => false,
                'message' => __('game.no_data_found'),
                'error' => __('game.no_data_found'),
            ];
        }
        $send['GameId'] = $tbl_game->gametarget_id;
        $response = self::curl( self::$url."KickMemberAll", $send);
        return $response;
    }

    public static function gamelog( $tbl_player )
    {
        if ( $tbl_player->member->status !== 1 && $tbl_player->member->delete !== 0 ) {
            return [
                'status' => false,
                'message' => __('messages.profileinactive'),
                'error' => __('messages.profileinactive'),
                'code'    => 500
            ];
        }
        $send["StartTime"] = Carbon::now('America/Santo_Domingo')->subMinutes(60)->format('Y-m-d\TH:i:s');
        $send["EndTime"] = Carbon::now('America/Santo_Domingo')->format('Y-m-d\TH:i:s');
        $send['Page'] = 1;
        $send['PageLimit'] = 10000;
        $send['Account'] = $tbl_player->loginId;
        $response = self::curl( self::$url."GetUserBetRecordByTime", $send);
        if ( !isset( $response['ErrorCode'] ) )
        {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500,
                'response'=> $response
            ];
        }
        if ( $response['ErrorCode'] !== 0 )
        {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        $epsilon = 0.00001;
        $balance = $tbl_player->balance;
        $turnover = 0.00;
        foreach( $response['Data']['Result'] as $gamelog )
        {
            $betamount = (float) $gamelog['Turnover'];
            $winloss = (float) $gamelog['PayoffAmount'];
            if (abs($betamount) < $epsilon && abs($winloss) < $epsilon) {
                continue;
            }
            $before_balance = (float) $gamelog['Balance'] + $gamelog['Turnover'] - $gamelog['PayoffAmount'];
            $after_balance = (float) $gamelog['Balance'];
            if (abs($before_balance) < $epsilon && abs($after_balance) < $epsilon) {
                $before_balance = $balance;
                $after_balance = $balance + ($winloss - $betamount);
            }
            $balance = $after_balance;
            $existgamelog = Gamelog::where('gamelogtarget_id', $gamelog['WagersId'] )
                ->where('gamemember_id', $tbl_player->gamemember_id )
                ->first();
            if ( !$existgamelog ) {
                $tbl_game = Game::where('gametarget_id', $gamelog['GameId'] )
                    ->where('gameplatform_id', 1004 )
                    ->first();
                $remark = $tbl_game ? $tbl_game->game_name : null;
                $tbl_gamelog = Gamelog::create([
                    'gamelogtarget_id' => $gamelog['WagersId'],
                    'gamemember_id' => $tbl_player->gamemember_id,
                    'game_id' => $tbl_game ? $tbl_game->game_id : null,
                    'tableid' => null,
                    'before_balance' => $before_balance,
                    'after_balance' => $after_balance,
                    'betamount' => $betamount,
                    'winloss' => $winloss,
                    'startdt' => Carbon::parse( $gamelog['WagersTime'] )
                        ->setTimezone('Asia/Kuala_Lumpur')
                        ->format('Y-m-d H:i:s'),
                    'enddt' => Carbon::parse( $gamelog['PayoffTime'] )
                        ->setTimezone('Asia/Kuala_Lumpur')
                        ->format('Y-m-d H:i:s'),
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

    public static function syncgamelog($tbl_player)
    {
        $lastlogin_on = Carbon::parse($tbl_player->lastlogin_on);
        $lastloginSD = $lastlogin_on->copy()->setTimezone('America/Santo_Domingo');
        $startdt = $lastloginSD->copy()->format('Y-m-d\TH:00:00');
        $enddt   = $lastloginSD->copy()->format('Y-m-d\TH:59:59');
        $result = [];
        $send = [
            'Account' => $tbl_player->loginId,
            'Page' => 1,
            'PageLimit' => 10000,
            'StartTime' => $startdt,
            'EndTime'   => $enddt,
        ];
        $response = self::curl(self::$url . "GetUserBetRecordByTime", $send);
        if ( !isset( $response['ErrorCode'] ) )
        {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500,
                'response'=> $response
            ];
        }
        if ( $response['ErrorCode'] !== 0 )
        {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        if ( empty($response['Data']['Result']) ) {
            return [
                'status'  => false,
                'message' => __('gamelog.no_data_found'),
                'error' => $response['Message'],
                'code'    => 500
            ];
        }
        $result = $response['Data']['Result'];
        $epsilon = 0.00001;
        $balance = $tbl_player->balance;
        $turnover = 0.00;
        $result = array_filter($result, function($gamelog) use ($epsilon) {
            $betamount = (float) $gamelog['Turnover'];
            $winloss = (float) $gamelog['PayoffAmount'];
            return !(abs($betamount) < $epsilon && abs($winloss) < $epsilon);
        });
        $gametarget_ids = array_unique(array_column($result, 'GameId'));
        $games = Game::whereIn('gametarget_id', $gametarget_ids)
            ->where('gameplatform_id', 1004)
            ->get()
            ->keyBy('gametarget_id');
        $data = [];
        foreach ($result as $gamelog) {
            $betamount = (float) $gamelog['Turnover'];
            $winloss = (float) $gamelog['PayoffAmount'];
            $before_balance = (float) $tbl_player->balance + $gamelog['Turnover'] - $gamelog['PayoffAmount'];
            $after_balance = (float) $tbl_player->balance;
            if (abs($before_balance) < $epsilon && abs($after_balance) < $epsilon) {
                $before_balance = $balance;
                $after_balance = $balance + ($winloss - $betamount);
            }
            $balance = $after_balance;
            $tbl_game = $games[$gamelog['GameId']] ?? null;
            $remark = $tbl_game ? $tbl_game->game_name : null;
            $gamelog['gamelogtarget_id'] = $gamelog['WagersId'];
            $gamelog['gamemember_id'] = $tbl_player->gamemember_id;
            $gamelog['game_id'] = $tbl_game ? $tbl_game->game_id : null;
            $gamelog['tableid'] = null;
            $gamelog['before_balance'] = $before_balance;
            $gamelog['after_balance'] = $after_balance;
            $gamelog['betamount'] = $betamount;
            $gamelog['winloss'] = $winloss;
            $gamelog['startdt'] = Carbon::parse( $gamelog['WagersTime'] )
                ->setTimezone('Asia/Kuala_Lumpur')
                ->format('Y-m-d H:i:s');
            $gamelog['enddt'] = Carbon::parse( $gamelog['PayoffTime'] )
                ->setTimezone('Asia/Kuala_Lumpur')
                ->format('Y-m-d H:i:s');
            $gamelog['remark'] = $remark;
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