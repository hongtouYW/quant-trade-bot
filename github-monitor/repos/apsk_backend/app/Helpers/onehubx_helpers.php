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
use App\Models\Onehubxcallback;
use App\Models\Provider;
use Carbon\Carbon;

class Onehubx
{
    private static string $username = "hubx290";
    private static string $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZG1pbl9hY2Nlc3Nfa2V5X2FkbWluX2lkIjoyOTAyNSwiYWRtaW5fYWNjZXNzX2tleV9pZCI6NTMzLCJpYXQiOjE3NTg2OTExNDF9.RMeHphhqvPQ9N8fm3JJBuSIzbQupEViBt2tmztqKxjM";
    private static string $secret = "UK342TOSPLOBSVLTV3EYSC6Z";
    private static string $url = "https://agent-api.netplay.vip/api"; //Production
    private static int $provider_id = 1;

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

    private static function method($method)
    {
        switch ($method) {
            case 'providerlist':
                return '/v1/agent/player/getProviderList';
                break;
            case 'gamelist':
                return '/v1/agent/player/getGameList';
                break;
            case 'create':
                return '/v1/agent/player/create';
                break;
            case 'playerlist':
                return '/v1/agent/player/getPlayerList';
                break;
            case 'reload':
                return '/v1/agent/player/reload';
                break;
            case 'withdraw':
                return '/v1/agent/player/withdraw';
                break;
            case 'playerprofile':
                return '/v1/agent/player/getPlayerProfile';
                break;
            case 'resetpaymentpin':
                return '/v1/agent/player/resetPaymentPin';
                break;
            case 'enableplayer':
                return '/v1/agent/player/enable';
                break;
            case 'disableplayer':
                return '/v1/agent/player/disable';
                break;
            case 'generategameurl':
                return '/v1/agent/player/generateGameURL';
                break;
            case 'gamelog':
                return '/v1/agent/player/getGameLog';
                break;
            case 'winloseagentreport':
                return '/v1/agent/report/getWinLoseAgentReport';
                break;
            case 'winloseplayerreport':
                return '/v1/agent/report/getWinLosePlayerReport';
                break;
            case 'companyreport':
                return '/v1/agent/report/getCompanyReport';
                break;
            case 'memberbusinessreport':
                return '/v1/agent/report/getCashAgentReport';
                break;
            case 'playerbusinessreport':
                return '/v1/agent/report/getCashPlayerReport';
                break;
            case 'shareholderreport':
                return '/v1/agent/report/getShareholderReport';
                break;
            case 'providerreport':
                return '/v1/agent/report/getProviderReport';
                break;
            case 'checktransaction':
                return '/v1/agent/player/checkTransaction';
                break;
            case 'accountreport':
                return '/v1/agent/player/getAccountReport';
                break;
            case 'allprovidercategory':
                return '/v1/agent/player/getAllProviderCategory';
                break;
            case 'resetpassword':
                return '/v1/agent/player/resetPassword';
                break;
            case 'gamelogseamless':
                return '/v1/agent/player/getSeamlessGameLog';
                break;
            default:
                return '';
                break;
        }
    }
    
    private static function language()
    {
        $language = request()->header('Accept-Language');
        switch ($language) {
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

    private static function gametype($game_type)
    {
        switch ($game_type) {
            case '1': //Slot
                return 2;
                break;
            case '2': //Table
                return 21;
                break;
            case '3': //Lobby
                return 19;
                break;
            case '5': //Fish
                return 12;
                break;
            case '8': //Table
                return 21;
                break;
            default:
                return 21;
                break;
        }
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

    private static function curl($method, $param = '{}')
    {
        // ---Generate OneHubX signature: HMAC-SHA256(body, secret)---
        $jsonBody = is_array($param) ? json_encode($param, JSON_UNESCAPED_SLASHES) : $param;
        $signature = hash_hmac('sha256', $jsonBody, self::$secret);
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => self::$url.$method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $jsonBody, //Changed array to json to be used in post fields
                CURLOPT_HTTPHEADER => array(
                    'User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36',
                    'Authorization: Bearer '.self::$token,
                    'signature: '. $signature,
                    'Content-Type: application/json'
                ),
            ));
            $response = curl_exec($curl);
            $response = safe_json_decode($response);
            curl_close($curl);
            if ( !isset($response['status']) ) {
                self::logError( self::$url.$method, $param, [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $response,
                    'code' => 500
                ]);
                return [
                    'status'  => false,
                    'message' => __('messages.unexpected_error'),
                    'error'   => __('messages.unexpected_error'),
                    'code'    => 500
                ];
            }
            if ( !$response['status'] ) {
                self::logError( self::$url.$method, $param, [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $response['message'],
                    'code' => 500
                ]);
                return [
                    'status'  => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $response['message'],
                    'code'    => 500
                ];
            }
            return $response;
        } catch (\Exception $e) {
            $response = [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
            self::logError(self::$url.$method, $param, $response);
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }

    private static function timestamp()
    {
        return round(microtime(true) * 1000); // milliseconds (data signature doc: let currentTimestamp = moment().format("x"))
    }

    public static function resetpaymentpin($tbl_player)
    {
        $param = [
            "username"      => $tbl_player->loginId,
            "timestamp"     => self::timestamp()
        ];
        $response = self::curl( self::method('resetpaymentpin'), $param);
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500,
                'paymentpin' => null,
            ];
        }
        $response['status'] = true;
        $response['message'] = $response['message'];
        $response['error'] = "";
        $response['code'] = 200;
        $response['paymentpin'] = $response['data']['player_payment_pin'];
        return $response;
    }

    public static function providerlist()
    {
        $param = [
            'timestamp' => self::timestamp(),
        ];
        $response = self::curl( self::method('providerlist'), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        if ( is_null($response['data']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        foreach( $response['data'] as $key => $data ) {
            $tbl_provider = Provider::where('providertarget_id', $data['provider_id'] )
                                    ->where('gameplatform_id', 1005 )
                                    ->first();
            if ( $data['platform'] === "App-Single" ) {
                continue;
            }
            if ( $tbl_provider ) {
                $provider = [];
                if ( $tbl_provider->provider_category !== $data['provider_category'] ) {
                    $provider['provider_category'] = $data['platform'] === "App" ? "app" : strtolower( $data['provider_category'] );
                }
                if ( $tbl_provider->download !== $data['provider_download_link'] ) {
                    $provider['download'] = $data['provider_download_link'];
                }
                if ( $tbl_provider->platform_type !== $data['platform'] ) {
                    $provider['platform_type'] = $data['platform'];
                }
                if (!empty($provider)) {
                    $provider['updated_on'] = now();
                    $tbl_provider->update($provider);
                }
            } else {
                $tbl_provider = Provider::create([
                    'gameplatform_id' => 1005,
                    'providertarget_id' => $data['provider_id'],
                    'provider_name' => $data['provider_name'],
                    'provider_category' => $data['platform'] === "App" ? "app" : strtolower( $data['provider_category'] ),
                    'download' => $data['provider_download_link'],
                    'platform_type' => $data['platform'],
                    // 'status' => $data['provider_status'] === "ACTIVE" ? 1 : 0,
                    'status' => 0,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }
        }
        $response['status'] = true;
        $response['message'] = __('messages.list_success');
        $response['error'] = "";
        $response['code'] = 200;
        return $response;
    }

    public static function gamelist( $tbl_provider )
    {
        $response = self::providerlist();
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        $param = [
            'timestamp'   => self::timestamp(),
            'provider_id' => $tbl_provider->providertarget_id,
        ];
        $response = self::curl( self::method('gamelist'), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => $response['message'],
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        if ( is_null($response['data']) ) {
            return [
                'status'  => false,
                'message' => $response['message'],
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        $allgames = [];
        foreach ($response['data'] as $data) {
            $allgames[] = [
                'gameplatform_id' => 1005,
                'game_name'       => $data['game_name'],
                'provider_id'     => $tbl_provider->provider_id,
                'gametarget_id'   => $data['game_id'],
                'icon'            => $data['game_thumbnail'],
                'type'            => self::gametype($data['game_type']),
            ];
        }
        $response['status'] = true;
        $response['message'] = __('messages.list_success');
        $response['error'] = "";
        $response['data'] = $allgames;
        $response['code'] = 200;
        return $response;
        // $editGames = [];
        // foreach ($response['data'] as $data) {
        //     $tbl_game = Game::where('gameplatform_id', 1005)
        //                     ->where('provider_id', $provider_id )
        //                     ->where('gametarget_id', $data['game_id'] )
        //                     ->first();
        //     if ( $tbl_game ) {
        //         $editGames = [];
        //         if ( $tbl_game->gametarget_id !== $data['game_id'] ) {
        //             $editGames['gametarget_id'] = $data['game_id'];
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
        //         'gameplatform_id' => 1005,
        //         'game_name'       => $data['game_name'],
        //         'provider_id'     => $provider_id,
        //         'gametarget_id'   => $data['game_id'],
        //         'icon'            => $data['game_thumbnail'],
        //         'type'            => self::gametype($data['game_type']),
        //         // 'status'          => $data['game_status'] === "1" ? 1 : 0,
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

    public static function register( $tbl_provider, $amount = 0.00)
    {
        $param = [
            "player_provider_id" => $tbl_provider->providertarget_id,
            "amount"      => $amount,
            "timestamp"   => self::timestamp()
        ];
        $response = self::curl( self::method('create'), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        $response['status'] = true;
        $response['message'] = __('messages.add_success');
        $response['error'] = "";
        $response['code'] = 200;
        $response['uid'] = isset( $response['data']['username'] ) ? $response['data']['username'] : null;
        $response['loginId'] = isset( $response['data']['username'] ) ? $response['data']['username'] : null;
        $response['password'] = isset( $response['data']['password'] ) ? $response['data']['password'] : null;
        $response['paymentpin'] = isset( $response['data']['paymentPin'] ) ? $response['data']['paymentPin'] : null;
        return $response;
    }

    public static function view($tbl_player)
    {
        $param = [
            "username"      => $tbl_player->loginId,
            "timestamp"     => self::timestamp()
        ];
        $response = self::curl( self::method('playerprofile'), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        if ( !isset( $response['data'] ) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['message'],
                'code'    => 500
            ];
        }
        $player = [];
        if (!is_null($response['data']['balance']) &&
            (float) $tbl_player->balance !== (float) $response['data']['balance'] ) {
            $player['balance'] = $response['data']['balance'];
        }
        if (!empty($player)) {
            if ( isset( $player['balance'] ) ) {
                $player['has_balance'] = $player['balance'] > 0 ? 1 : 0;
            }
            $tbl_player->update($player);
            $tbl_player = $tbl_player->fresh();
        }
        $player = $tbl_player->load('Member','Provider', 'Gameplatform');
        $deeplink = null;
        switch ($tbl_player->Provider->provider_name) {
            case 'MEGA888':
                $deeplink['android'] = "lobbymegarelease://?account=".$tbl_player->loginId.
                                        "&password=".decryptPassword( $tbl_player->pass );
                $deeplink['ios'] = "lobbymegarelease://?account=".$tbl_player->loginId.
                                    "&password=".decryptPassword( $tbl_player->pass );
                break;
            case 'PUSSY888':
                $deeplink['android'] = "pussy888://pussy888.com/?user=".$tbl_player->loginId.
                                        "&password=".decryptPassword( $tbl_player->pass );
                $deeplink['ios'] = "pussy888://pussy888.com/?user=".$tbl_player->loginId.
                                    "&password=".decryptPassword( $tbl_player->pass );
                break;
            case 'SUNCITY':
                $deeplink['android'] = "evo888android://lobbyevoandroid?account=".$tbl_player->loginId.
                                        "&password=".decryptPassword( $tbl_player->pass );
                $deeplink['ios'] = "evo888ios://lobbyevoios?account=".$tbl_player->loginId.
                                    "&password=".decryptPassword( $tbl_player->pass );
                break;
            case 'EVO888-APP':
                $deeplink['android'] = "evo888android://lobbyevoandroid?account=".$tbl_player->loginId.
                                        "&password=".decryptPassword( $tbl_player->pass );
                $deeplink['ios'] = "evo888ios://lobbyevoios?account=".$tbl_player->loginId.
                                    "&password=".decryptPassword( $tbl_player->pass );
                break;
            case '918KISS':
                $deeplink['android'] = "lobbykiss://lobbykiss?account=".$tbl_player->loginId.
                                        "&password=".decryptPassword( $tbl_player->pass );
                $deeplink['ios'] = "lobbykissgame://account=".$tbl_player->loginId.
                                    "&password=".decryptPassword( $tbl_player->pass );
                break;
            default:
                break;
        }
        $response['status'] = true;
        $response['message'] = __('messages.list_success');
        $response['error'] = "";
        $response['code'] = 200;
        $response['data'] = $tbl_player;
        $response['deeplink'] = $deeplink;
        return $response;
    }

    public static function login($tbl_player, $tbl_game = null)
    {
        $param = [
            "username"      => $tbl_player->loginId,
            "password"      => decryptPassword( $tbl_player->pass ),
            "timestamp"     => self::timestamp(),
            "language"      => self::language(),
        ];
        $tbl_provider = Provider::where( 'provider_id', $tbl_player->provider_id )
                        ->where('status', 1)
                        ->where('delete', 0)
                        ->first();
        if (!$tbl_provider) {
            return [
                'status' => false,
                'message' => __('provider.no_data_found'),
                'error' => __('provider.no_data_found'),
                'code' => 500
            ];
        }
        switch ($tbl_provider->platform_type) {
            case 'Web-Single':
                $param["gameID"] = (string) $tbl_game->gametarget_id;
                break;
            case 'Web-Lobby':
                // $param["lobbyURL"] = $tbl_player->download;
                break;
            case 'App':
                return [
                    'status' => true,
                    'message' => __('messages.login_success'),
                    'error' => '',
                    'code' => 200,
                    'url' => $tbl_provider->download,
                ];
                break;
            case 'App-Single':
                break;
            default:
                break;
        }
        $param["lobbyURL"] = config('app.urldownload');
        $response = self::curl( self::method('generategameurl'), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        return [
            'status'  => true,
            'message' => __('messages.login_success'),
            'error'   => "",
            'code'    => 200,
            'url'     => $response['data'],
        ];
        // $response['status'] = true;
        // $response['message'] = __('messages.login_success');
        // $response['error'] = "";
        // $response['code'] = 200;
        // $response['url'] = $response['data'];
        // return $response;
    }

    public static function transfer($tbl_player, $amount, $type)
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
        $method = $type == 'reload' ? "reload" : "withdraw";
        $param['username'] = $tbl_player->loginId;
        $param['timestamp'] = self::timestamp();
        if ( $method === "withdraw" ) {
            $param['amount'] = abs( (float) $roundedAmount );
            $paymentpin = self::resetpaymentpin($tbl_player);
            if ( !isset($paymentpin['status']) ) {
                return [
                    'status'  => false,
                    'message' => $paymentpin['message'],
                    'error'   => __('messages.unexpected_error'),
                    'code'    => 500,
                    'orderid' => $orderid,
                    'remain'  => $amount,
                ];
            }
            $player['paymentpin'] = $paymentpin['paymentpin'];
            $tbl_player->update($player);
            $tbl_player = $tbl_player->fresh();
            $param['payment_pin'] = $tbl_player->paymentpin;
        } else {
            $param['amount'] = $roundedAmount;
        }
        $response = self::curl( self::method($method), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500,
                'orderid' => $orderid,
                'remain'  => $amount,
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500,
                'orderid' => $orderid,
                'remain'  => $amount,
            ];
        }
        if ( is_null($response['data']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500,
                'orderid' => $orderid,
                'remain'  => $amount,
            ];
        }
        if ( isset($response['data']['transactionID']) ) {
            $orderid = $response['data']['transactionID'];
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
        $method = $enable === 1 ? "enableplayer": "disableplayer";
        $param = [
            "username"      => $tbl_player->loginId,
            "timestamp"     => self::timestamp()
        ];
        $response = self::curl( self::method($method), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500
            ];
        }
        $response['status'] = true;
        $response['message'] = $enable === 0 ? __('gamemember.gamemember_deleted_successfully') : "";
        $response['error'] = "";
        $response['code'] = 200;
        return $response;
    }

    public static function playerlist($current = 0, $pageSize = 10000)
    {
        $param = [
            "current"     => $current,
            "pageSize"    => $pageSize,
            "timestamp"   => self::timestamp()
        ];
        return self::curl( self::method('playerlist'), $param);
    }

    public static function gamelog($tbl_player, $logdt = null, $current = 0, $pageSize = 10000 )
    {
        if ( $tbl_player->member->status !== 1 && $tbl_player->member->delete !== 0 ) {
            return [
                'status' => false,
                'message' => __('messages.profileinactive'),
                'error' => __('messages.profileinactive'),
                'code'    => 500
            ];
        }
        $param = [
            "current"                       => $current,
            "pageSize"                      => $pageSize,
            "username"                      => $tbl_player->loginId,
            "player_game_log_bet_datetime"  => !is_null($logdt) ? $logdt : Carbon::now()->format('Y-m-d'),
            "timestamp"                     => self::timestamp()
        ];
        $response = self::curl( self::method('gamelog'), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['message'],
                'code'    => 500,
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['message'],
                'code'    => 500,
            ];
        }
        if ( empty( $response['data'] ) ) {
            return [
                'status'  => false,
                'message' => __('gamemember.no_data_found'),
                'error'   => $response['message'],
                'code'    => 500
            ];
        }
        $epsilon = 0.00001;
        $balance = $tbl_player->balance;
        $turnover = 0.00;
        foreach( $response['data'] as $gamelog )
        {
            $betamount = (float) $gamelog['player_game_log_bet_amount'];
            $winloss = (float) $gamelog['player_game_log_win_amount'];
            if (abs($betamount) < $epsilon && abs($winloss) < $epsilon) {
                continue;
            }
            $before_balance = (float) $gamelog['player_game_log_begin_balance'];
            $after_balance = (float) $gamelog['player_game_log_end_balance'];
            if (abs($before_balance) < $epsilon && abs($after_balance) < $epsilon) {
                $before_balance = $balance;
                $after_balance = $balance + ($winloss - $betamount);
            }
            $balance = $after_balance;
            $existgamelog = Gamelog::where('gamelogtarget_id', $gamelog['player_game_log_id'] )
                                    ->where('gamemember_id', $tbl_player->gamemember_id )
                                    ->first();
            if ( !$existgamelog ) {
                $remark = $gamelog['player_game_log_game_name'];
                $tbl_gamelog = Gamelog::create([
                    'gamelogtarget_id' => $gamelog['player_game_log_id'],
                    'gamemember_id' => $tbl_player->gamemember_id,
                    'game_id' => null,
                    'tableid' => null,
                    'before_balance' => $before_balance,
                    'after_balance' => $after_balance,
                    'betamount' => $betamount,
                    'winloss' => $winloss,
                    'startdt' => Carbon::parse( $gamelog['player_game_log_bet_datetime'] )
                                        ->setTimezone('Asia/Kuala_Lumpur')
                                        ->format('Y-m-d H:i:s'),
                    'enddt' => Carbon::parse( $gamelog['player_game_log_bet_datetime'] )
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

    public static function winLoseMemberReport($current, $pageSize, $agentUsername, $providerCode, $roleName, $datetime)
    {
        $param = [
            "current"           => $current,
            "pageSize"          => $pageSize,
            "agentUsername"     => $agentUsername,
            "providerCode"      => $providerCode,
            "roleName"          => $roleName,
            "datetime"          => $datetime,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('winlosememberreport'), $param);
    }

    public static function winLosePlayerReport($current, $pageSize, $agentUsername, $providerCode, $roleName, $datetime)
    {
        $param = [
            "current"           => $current,
            "pageSize"          => $pageSize,
            "agentUsername"     => $agentUsername,
            "providerCode"      => $providerCode,
            "roleName"          => $roleName,
            "datetime"          => $datetime,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('winloseplayerreport'), $param);
    }

    public static function companyReport($current, $pageSize, $agentUsername, $datetime, $providerCode = null)
    {
        $param = [
            "current"           => $current,
            "pageSize"          => $pageSize,
            "agentUsername"     => $agentUsername,
            "providerCode"      => $providerCode,
            "datetime"          => $datetime,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('companyreport'), $param);
    }

    public static function memberBusinessReport($current, $pageSize, $agentUsername, $providerCode, $roleName, $datetime)
    {
        $param = [
            "current"           => $current,
            "pageSize"          => $pageSize,
            "agentUsername"     => $agentUsername,
            "providerCode"      => $providerCode,
            "roleName"          => $roleName,
            "datetime"          => $datetime,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('memberbusinessreport'), $param);
    }

    public static function playerBusinessReport($current, $pageSize, $agentUsername, $providerCode, $datetime)
    {
        $param = [
            "current"           => $current,
            "pageSize"          => $pageSize,
            "agentUsername"     => $agentUsername,
            "providerCode"      => $providerCode,
            "datetime"          => $datetime,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('playerbusinessreport'), $param);
    }

    public static function shareholderReport($current, $pageSize, $agentUsername, $providerCode, $datetime)
    {
        $param = [
            "current"           => $current,
            "pageSize"          => $pageSize,
            "agentUsername"     => $agentUsername,
            "providerCode"      => $providerCode,
            "datetime"          => $datetime,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('shareholderreport'), $param);
    }

    public static function providerReport($current, $pageSize, $providerCode, $datetime)
    {
        $param = [
            "current"           => $current,
            "pageSize"          => $pageSize,
            "providerCode"      => $providerCode,
            "datetime"          => $datetime,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('providerreport'), $param);
    }

    public static function checkTransaction($transactionId)
    {
        $param = [
            "transactionID"     => $transactionId,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('checktransaction'), $param);
    }

    public static function accountReport($username, $accountReportDatetime)
    {
        $param = [
            "username"                  => $username,
            "account_report_datetime"   => $accountReportDatetime,
            "timestamp"                 => self::timestamp()
        ];
        return self::curl( self::method('accountreport'), $param);
    }

    public static function allProviderCategory($providerId)
    {
        $param = [
            "provider_id"       => $providerId,
            "timestamp"         => self::timestamp()
        ];
        return self::curl( self::method('allprovidercategory'), $param);
    }

    public static function resetpassword($tbl_player)
    {
        $param['username'] = $tbl_player->loginId;
        $param['timestamp'] = self::timestamp();
        $response = self::curl( self::method('resetpassword'), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500,
            ];
        }
        if ( is_null($response['data']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error' => $response['message'],
                'code'    => 500,
            ];
        }
        $response['status'] = true;
        $response['message'] = __('messages.passwordchangesuccess');
        $response['error'] = "";
        $response['code'] = 200;
        $response['password'] = $response['data']['player_password'];
        $response['data'] = $tbl_player;
        return $response;
    }

    public static function syncgamelog($tbl_player)
    {
        $param = [
            "current"                       => 0,
            "pageSize"                      => 10000,
            "username"                      => $tbl_player->loginId,
            "player_game_log_bet_datetime"  => Carbon::parse($tbl_player->lastlogin_on)->format('Y-m-d'),
            "timestamp"                     => self::timestamp()
        ];
        // $response = self::curl( self::method('gamelogseamless'), $param);
        $response = self::curl( self::method('gamelog'), $param);
        if ( !isset($response['status']) ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => __('messages.unexpected_error'),
                'code'    => 500,
            ];
        }
        if ( !$response['status'] ) {
            return [
                'status'  => false,
                'message' => __('messages.unexpected_error'),
                'error'   => $response['message'],
                'code'    => 500,
            ];
        }
        if ( empty($response['data']) ) {
            return [
                'status'  => false,
                'message' => __('gamelog.no_data_found'),
                'error'   => $response['message'],
                'code'    => 400,
            ];
        }
        $gamelog_array = $response['data'];
        $epsilon = 0.00001;
        $balance = $tbl_player->balance;
        $turnover = 0.00;
        $gamelog_array = array_filter($gamelog_array, function($gamelog) use ($epsilon) {
            $betamount = (float) $gamelog['player_game_log_bet_amount'];
            $winloss = (float) $gamelog['player_game_log_win_amount'];
            return !(abs($betamount) < $epsilon && abs($winloss) < $epsilon);
        });
        $data = [];
        foreach ($gamelog_array as $gamelog) {
            $betamount = (float) $gamelog['player_game_log_bet_amount'];
            $winloss = (float) $gamelog['player_game_log_win_amount'];
            $before_balance = (float) $gamelog['player_game_log_begin_balance'];
            $after_balance = (float) $gamelog['player_game_log_end_balance'];
            if (abs($before_balance) < $epsilon && abs($after_balance) < $epsilon) {
                $before_balance = $balance;
                $after_balance = $balance + ($winloss - $betamount);
            }
            $balance = $after_balance;
            $gamelog['gamelogtarget_id'] = $gamelog['player_game_log_id'];
            $gamelog['gamemember_id'] = $tbl_player->gamemember_id;
            $gamelog['game_id'] = null;
            $gamelog['tableid'] = null;
            $gamelog['before_balance'] = $before_balance;
            $gamelog['after_balance'] = $after_balance;
            $gamelog['betamount'] = $betamount;
            $gamelog['winloss'] = $winloss;
            $gamelog['startdt'] = Carbon::parse( $gamelog['player_game_log_bet_datetime'] )
                                        ->setTimezone('Asia/Kuala_Lumpur')
                                        ->format('Y-m-d H:i:s');
            $gamelog['enddt'] = Carbon::parse( $gamelog['player_game_log_bet_datetime'] )
                                    ->setTimezone('Asia/Kuala_Lumpur')
                                    ->format('Y-m-d H:i:s');
            $gamelog['remark'] = $gamelog['player_game_log_game_name'];
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
