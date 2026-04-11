<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Agent;
use App\Models\Chatboterror;
use Carbon\Carbon;

class Chatbot
{
    private static string $url = "http://chat-bo.dev-staging.uk/api/";
    private static string $support = "https://chat.dev-staging.uk/login?uid={uid}&pid={pid}";
    private static string $platform = "apsky";

    private static function curl($url, $params = [], $event = "chatbot.register")
    {
        try {
            $headers = [
                "Content-Type: application/json"
            ];
            $curl = curl_init( $url );
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($curl);
            curl_close($curl);
            if ($response === false) {
                $error = curl_error($curl);
                self::logError($url, $params, $error);
                return [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $error,
                ];
            }
            $response = json_decode($response, true);
            if ($response === null) {
                self::logError($url, $params, [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $response,
                ]);
                return [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $response,
                ];
            }
            if (!isset($response['isEnabled'])) {
                self::logError($url, $params, [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $response,
                ]);
                return [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => __('messages.unexpected_error'),
                ];
            }
            if ( !$response['isEnabled'] ) {
                self::logError($url, $params, [
                    'status' => false,
                    'message' => __('chatbot.fail',['event'=>$event]),
                    'error' => $response,
                ]);
                return [
                    'status' => false,
                    'message' => __('chatbot.fail',['event'=>$event]),
                    'error' => $response,
                ];
            }
            return [
                'status' => true,
                'message' => __('chatbot.success',['event'=>$event]),
                'error' => "",
                'data' => $response,
                'support' => str_replace(
                    ['{uid}', '{pid}'],
                    [$params['username'], $params['platform']],
                    self::$support
                ),
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            self::logError($url, $params, [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'),
                'error' => $e->getMessage(),
            ];
        }
    }

    private static function logError($url, $params, $response)
    {
        Chatboterror::create([
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

    public static function register($tbl_user)
    {
        $url = self::$url.'app/agent-account/register';
        $params = [
            'platform' => self::$platform,
            'nickname' => trim($tbl_user->user_name),
            'username' => trim($tbl_user->user_login),
            'password' => "P@ssword123",
        ];
        return self::curl($url, $params);
    }

}