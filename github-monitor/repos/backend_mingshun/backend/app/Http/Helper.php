<?php

namespace App\Http;

use App\Models\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Log as ModelsLog;
use App\Models\Project;
use App\Models\ProjectRules;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;

class Helper
{
    public static function sendCurlRequest($url, $body, $header, $logMsg)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, trim($url));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);

        $ip = self::getIp();
        $primaryIP = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            Log::channel('api_error')->info("Response False--". $ip. "--". $primaryIP. "--" .$httpCode. "--" . $response . "--" . curl_error($ch) . "--" . $url . "--" . json_encode($body) . "--" . json_encode($header));
            return curl_error($ch);
        }else{
            Log::channel('send_api')->info($logMsg . "--" . $ip. "--" . $primaryIP. "--" .$httpCode. "--" . $response . "--" . curl_error($ch) . "--" . $url . "--" . json_encode($body) . "--" . json_encode($header));
        }
        // Close cURL resource
        curl_close($ch);

        return array($response,$primaryIP,$httpCode);
    }

    public static function sendCurlGetRequest($url, $header, $logMsg)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);

        $ip = self::getIp();
        $primaryIP = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            Log::channel('api_error')->info("Response False--". $ip. "--". $primaryIP. "--" .$httpCode. "--" . $response . "--" . curl_error($ch) . "--" . $url  . "--" . json_encode($header));
            return curl_error($ch);
        }else{
            Log::channel('send_api')->info($logMsg . "--" . $ip. "--" . $primaryIP. "--" .$httpCode. "--" . $response . "--" . curl_error($ch) . "--" . $url . "--" . json_encode($header));
        }

        curl_close($ch);

        return json_decode($response);
    }

    public static function sendResourceRequest($url, $body, $header, $logMsg = '')
    {
        list($response, $ip, $code) = self::baseSendRequest($url, $body, $header, $logMsg);
        return $response;
    }

    public static function sendResourceRequestNoError($url, $body, $header, $logMsg = '')
    {
        list($response, $ip, $code) = self::baseSendRequest($url, $body, $header, $logMsg, 0);
        return $response;
    }

    public static function sendResourceIpRequest($url, $body, $header, $logMsg = '')
    {
        list($response, $ip, $code) = self::baseSendRequest($url, $body, $header, $logMsg);
        return array($response, $ip);
    }

    public static function sendResourceIpCodeRequest($url, $body, $header, $logMsg = '')
    {
        list($response, $ip, $code) = self::baseSendRequest($url, $body, $header, $logMsg);
        return array($response, $ip, $code);
    }

    public static function baseSendRequest($url, $body, $header, $logMsg = '', $error = 1)
    {
        $header[] = 'Referer: https://ms.minggogogo.com/';
        $header[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36';
        list($response, $ip, $code) = self::sendCurlRequest($url, $body, $header, $logMsg);
        $response_decode = json_decode($response);
        if($error){
            if (($response_decode->code ?? 0) != 200) {
                Log::channel('api_error')->info($logMsg . " response not 200--" .$response . "--" . $url . "--" . json_encode($body) . "--" . json_encode($header));
            }
        }
        return array($response, $ip, $code);
    }

    public static function isEnglishName($name)
    {
        return preg_match('/^[A-Za-z0-9]+$/', str_replace(' ', '', $name));
    }

    public static function formatBytes($bytes, $precision = 2)
    {
        if (!is_numeric($bytes)) {
            return $bytes;
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $base = log($bytes, 1024);
        $index = min((int) $base, count($units) - 1);
        $value = round(pow(1024, $base - floor($base)), $precision);
        return $value .  $units[$index];
    }

    public static function isValidJson($string) {
        if (!is_string($string)) return false; 

        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    public static function getIp()
    {
        return request()->ip() ?? '';
    }

    public static function saveLog($data, $type, $className, $id)
    {
        ModelsLog::create([
            'type' => $type,
            'user' => Auth::user()->id ?? 0,
            'data' => json_encode($data),
            'model' => $className,
            'target_id' => $id,
            'ip' => Helper::getIp()
        ]);
    }

    public static function removePoweredByEditor($inputString){
        $pattern = '/<p[^>]*data-f-id\s*=\s*["\']pbf["\'][^>]*>.*?<\/p>/i';
        $outputString = preg_replace($pattern, '', $inputString);
        
        return $outputString;
    }

    public static function resendSubtitle($videoChoose){
        $project = Project::findOrFail($videoChoose->project_id);
        $project_server = $project->servers->first();
        if(!$project_server){
            throw new \Exception('项目设置错误');
        }
        $data = [
            'videoId' => $videoChoose->video->uid . "__" . $videoChoose->id,
            'receiver' => [
                'username' => $project_server->username,
                'host' => $project_server->ip,
                'port' => (int)$project_server->port,
                'identifier' => $videoChoose->video?->uid . "__" . $videoChoose->id,
                'path' => $project_server->absolute_path,
            ]
        ];
        $domain = Config::where('key','subtitle_domain')->first()->value;
        $response = Helper::sendResourceRequest(
            $domain . '/subtitle/resend',
            json_encode($data),
            array('Content-Type: application/json'),
            'Sync Video Subtitle'
        );
        $response_decode = json_decode($response);
        if ((($response_decode->code ?? 0) != 200) || (($response_decode->msg ?? '') != 'success')) {
            if(($response_decode->code ?? 0) == 600){
                $videoChoose = Helper::retrySubtitle($videoChoose);
            }else{
                $videoChoose->status = 12;
                if($response_decode->msg ?? ''){
                    $videoChoose->sync_callback_msg = $response_decode->msg;
                }else{
                    $videoChoose->sync_callback_msg = 'Subtitle Sync Error';
                }
            }
        }else{
            $videoChoose->status = 9;
        }
        
        return $videoChoose;
    }

    public static function retrySubtitle($videoChoose){
        $recut = 0;
        if(!$videoChoose->cut_callback_success_msg){
             $recut = 1;
        }else{
            $rule = ProjectRules::findOrFail($videoChoose->project_rule_id);
            $project = $videoChoose->project;
            $data = [
                'videoId' => $videoChoose->video->uid . "__" . $videoChoose->id,
                'language' => $rule->subtitle_languages ? json_decode($rule->subtitle_languages) : null,
                'redis' => (string)$project->redis_db
            ];
            $domain = Config::where('key','subtitle_domain')->first()->value;
            $response = Helper::sendResourceRequest(
                $domain . '/subtitle/retry',
                json_encode($data),
                array('Content-Type: application/json'),
                'Retry Video Subtitle'
            );
            $response_decode = json_decode($response);
            if ((($response_decode->code ?? 0) != 200) || (($response_decode->msg ?? '') != 'success')) {
                $videoChoose->status = 10;
                if($response_decode->msg ?? ''){
                    $videoChoose->subtitle_callback_msg = $response_decode->msg;
                    if($response_decode->msg == 'info data is missing'){
                        $recut = 1;
                    }
                }else{
                    $videoChoose->subtitle_callback_msg = 'Subtitle Retry Error';
                }
            }else{
                $videoChoose->ai_at = now();
                $videoChoose->status = 2;
                $videoChoose->sync_callback_msg = '';
                $videoChoose->cut_callback_msg = '';
                $videoChoose->subtitle_callback_msg = '';
            }
        }
        
        if($recut){
            $videoChoose = VideoChoose::chageStatus($videoChoose, 2, 0);
        }

        return $videoChoose;
    }

    public static function randomString($length = 10){
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    public static function export(){
        $project_id = Project::SHORTSTORY;
        $startMonth = (int) ($_GET['month'] ?? Carbon::now()->subMonth()->month);
        $endMonth = (int) ($_GET['end_month'] ?? Carbon::now()->month);
        $projectId = (int) ($_GET['project_id'] ?? $project_id);
        $year = (int) ($_GET['year'] ?? Carbon::now()->year);

        $firstDay = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $lastDay = Carbon::create($year, $endMonth, 1)->endOfMonth();

        $durationRanges = [
            '0–3分钟',
            '4–5分钟',
            '6–15分钟',
            '16–30分钟',
            '31–60分钟',
            '60分钟以上',
        ];

        $months = collect(range($startMonth, $endMonth))
            ->map(fn($m) => str_pad($m, 2, '0', STR_PAD_LEFT))
            ->toArray();

        $table = [];
        foreach ($months as $month) {
            $table[$month] = array_merge(['月份' => $month], array_fill_keys($durationRanges, 0));
        }

        $videoChooses = VideoChoose::with('project')
            ->where('project_id', $projectId)
            ->whereBetween('created_at', [$firstDay, $lastDay])
            ->where('status', 7)
            ->get();
        foreach ($videoChooses as $videoChoose) {
            $cutAt = Carbon::parse($videoChoose->cut_at);
            $month = $cutAt->format('m');

            if($videoChoose->cut_callback_success_msg){
                $cut_callback_msg = $videoChoose->cut_callback_success_msg;
            }else{
                $cut_callback_msg = $videoChoose->cut_callback_msg;
            }
            if (str_contains($cut_callback_msg, '--')) {
                $cut_callback_msg = explode("--", $cut_callback_msg)[0] ?? '';
            }
            if (Helper::isValidJson($cut_callback_msg)) {
                $details = json_decode($cut_callback_msg);
                $duration = (int) $details->duration;

                $range = match (true) {
                    $duration < 180   => '0–3分钟',
                    $duration < 300   => '4–5分钟',
                    $duration < 900   => '6–15分钟',
                    $duration < 1800  => '16–30分钟',
                    $duration < 3600  => '31–60分钟',
                    default           => '60分钟以上',
                };

                if (isset($table[$month][$range])) {
                    $table[$month][$range]++;
                }
            }
        }
        $rows = array_values($table);

        // File path and name with start and end month
        Storage::disk('public')->makeDirectory('temp');
        $fileName = "视频时长统计_{$year}年{$startMonth}月至{$endMonth}月.csv";
        $filePath = storage_path('app/public/temp/' . $fileName);

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_merge(['月份'], $durationRanges))
            ->addRows($rows);

        return $fileName;
    }
}
