<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:16
 */

namespace app\index\controller;
use app\index\model\Actor as ActorModel;
use app\index\model\Configs;
use app\index\model\Translate;
use app\index\model\Zimu;
// use app\index\model\Video as VideoModel;
use think\cache\driver\Redis;
use think\Db;

class Video extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Video();
        $tagList = model('tags')->field('id,name')->where('is_show','=',1)->order('sort desc')->select();
        $this->assign('tagList',$tagList);
    }

    public function index(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        // $where[] = ['mosaic','=',1];
        if(!empty($param['identifier'])){
            $param['identifier'] = trim($param['identifier']);
            $where[] =['identifier','=',$param['identifier']];
        }
        if(!empty($param['id'])){
            $param['id'] = trim($param['id']);
            $where[] =['id','=',$param['id']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['title','like','%'.$param['wd'].'%'];
        }
        if(!empty($param['mash'])){
            $param['mash'] = trim($param['mash']);
            $where[] =['mash','=',$param['mash']];
        }
        if(in_array($param['translated'],['0','1'],true)){
            if ($param['translated'] === '1') {
                $where[] = function ($query) {
                    $query->whereNotNull('title_en')->where('title_en', '<>', '');
                };
            } else {
                $where[] = function ($query) { // either null or empty string
                    $query->whereNull('title_en')->whereOr('title_en', '');
                };
            }
        }
        if (isset($param['zimu_status']) && $param['zimu_status'] !== '') {
            $where[] = ['zimu_status', '=', (int)$param['zimu_status']];
        }
        // if(in_array($param['zimu'],['0','1'],true)){
        //     if ($param['zimu'] === '1') {
        //         $where[] = function ($query) {
        //             $query->whereNotNull('zimu_zh')->where('zimu_zh', '<>', '');
        //         };
        //     } else {
        //         $where[] = function ($query) { // either null or empty string
        //             $query->whereNull('zimu_zh')->whereOr('zimu_zh', '');
        //         };
        //     }
        // }
        if(in_array($param['hotlist'],['0','1'],true)){
            $where[] = ['hotlist','eq',$param['hotlist']];
        }
        if(in_array($param['private'],['0','1','2','3'],true)){
            $where[] = ['private','=',$param['private']];
        }
        if(in_array($param['recommend'],['0','1'],true)){
            $where[] = ['recommend','eq',$param['recommend']];
        }
        if(in_array($param['status'],['0','1'],true)){
            $where[] = ['status','eq',$param['status']];
        }
        if(!empty($param['tag'])){
            $where['_string']  = "instr(CONCAT( ',', tags, ',' ),  ',".$param['tag'].",' )";
        }
        if (!empty($param['actor'])) {
            $search = trim($param['actor']);

            // if numeric or comma-separated IDs
            if (preg_match('/^[\d,]+$/', $search)) {
                $ids = explode(',', $search);
            } else {
                // lookup all actor ids with name LIKE
                $ids = ActorModel::where('name', 'like', "%{$search}%")->column('id');
            }

            if (!empty($ids)) {
                $conds = [];
                foreach ($ids as $id) {
                    $conds[] = "FIND_IN_SET({$id}, actor)";
                }
                // If $where already has a _string, append with AND
                if (!empty($where['_string'])) {
                    $where['_string'] .= " AND (" . implode(' OR ', $conds) . ")";
                } else {
                    $where['_string'] = implode(' OR ', $conds);
                }
            } else {
                $where[] = ['actor', '=', 0];
            }
        }


        $order='id desc';
        if(in_array($param['sort'],['1','2'],true)){

            switch ($param['sort']) {
                case 1:
                    $order='publish_date desc';
                    break;
                case 2:
                    $order='publish_date asc';
                    break;
                case 3:
                    $order = 'insert_time desc';
                    break;
                case 4:
                    $order = 'update_time desc';
                    break;
            }
        }
        $res = $this->model->listData($where,$order,$param['page'],$param['limit']);
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        return $this->fetch("index");
    }

    public function modify()
    {
        $this->checkPostRequest();
        $post=request()->post();
        $rule = [
            'id|ID'    => 'require',
            'field|字段' => 'require',
            'value|值'  => 'require',
        ];
        $this->validate($post, $rule);
        $row = $this->model->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }
        $data =  [];

        $data[$post['field']] = $post['value'];
        try {
            $row->save($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->model->update_last_admin($post['id']);
        $this->success('保存成功');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = request()->post();
            if(isset($param['groups']) && !is_array($param['groups'])) {
                $param['groups'] = [$param['groups']];
            }
            if(isset($param['hotlists']) && !is_array($param['hotlists'])) {
                $param['hotlists'] = [$param['hotlists']];
            }
            if(isset($param['tags'])){
                $param['tags'] = is_array($param['tags']) ? implode(',', $param['tags']) : $param['tags'];
            }
            $res = $this->model->saveData($param);
            $this->model->update_last_admin($param['id']);
            return $res['code'] > 1 ? $this->error($res['msg']) : $this->success($res['msg']);
        }

        $id          = input('id');
        $where       = [];
        $where['id'] = $id;
        $res         = $this->model->infoData($where);
        $info        = $res['info'];
        
        // Get current groups
        $info['current_group_ids'] = Db::name('video_group_details')
            ->where('video_id', $id)
            ->column('group_id');
        
        // Get current hotlist
        $info['current_hotlist_ids'] = Db::name('hotlist_category_details')
            ->where('video_id', $id)
            ->column('hotlist_category_id');

            
        // Get all groups for dropdown
        $this->assign([
            'info'        => $info,
            'all_groups'  => model('Group')->select(),
            'all_hotlist' => model('Hotlist')->select()
            // 'all_groups'  => model('Group')->where("is_show",1)->select(),
            // 'all_hotlist' => model('Hotlist')->where("is_show",1)->select()
        ]);
        
        return $this->fetch();
    }

    /*
     * 测试播放
     */
    public function testplay(){
        $url = input('url');
        $this->assign('url',$url);
        return $this->fetch();
    }

    /*
     * 翻译
     */
    public function translate(){
        $id=input("param.id");
        $result = Translate::translateVideo($id);
        $this->model->update_last_admin($id);
        if ($result){
            return json(["code"=>1,"msg"=>"翻译成功"]);
        }else{
            return json(["code"=>0,"msg"=>"翻译失败"]);
        }
    }

    /*
     * 更新字幕
     */
    public function updateZimu(){
        $id     = input("param.id");
        $result = Zimu::sendToThirdParty($id);
        $this->model->update_last_admin($id);
        if ($result){
            return json(["code"=>1,"msg"=>"字幕更新请求已发送"]);
        }else{
            return json(["code"=>0,"msg"=>"发送失败，请查看日志"]);
        }
    }

    
    /*
     * 提取字幕
     */
    public function transcribe()
    {
        $videoId = input('param.id');
        $result  = Zimu::sendTranscribeJob($videoId);
        return $result
            ? json(['code' => 1, 'msg' => '字幕提取中'])
            : json(['code' => 0, 'msg' => '字幕提取失败']);
    }

    public function downloadClip() {
        $url   = input('post.url');
        $start = input('post.start_time', '00:00:00');
        $end   = input('post.end_time',   '00:05:00');

        if (empty($url)) {
            return json(['code' => 0, 'msg' => '视频URL不能为空']);
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end)) {
            return json(['code' => 0, 'msg' => '时间格式错误，请使用 HH:MM:SS']);
        }

        // Ensure absolute URL for ffmpeg
        if (!preg_match('#^https?://#', $url)) {
            $base = rtrim(env('app.video_dl_url') ?: Configs::get('zimu_url'), '/');
            $url  = $base . '/' . ltrim($url, '/');
        }

        $tmpDir  = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? sys_get_temp_dir() : '/tmp';
        $output  = $tmpDir . DIRECTORY_SEPARATOR . 'clip_' . time() . '_' . rand(1000, 9999) . '.mp4';
        $duration = $this->calcDuration($start, $end);
        $cmd = sprintf(
            'ffmpeg -y -fflags +genpts+discardcorrupt -ss %s -i %s -t %s -c copy %s 2>&1',
            escapeshellarg($start),
            escapeshellarg($url),
            escapeshellarg($duration),
            escapeshellarg($output)
        );
        exec($cmd, $cmdOut, $ret);

        if ($ret !== 0 || !file_exists($output) || filesize($output) === 0) {
            return json(['code' => 0, 'msg' => 'ffmpeg处理失败: [ret=' . $ret . '] [cmd=' . $cmd . '] ' . implode(' | ', $cmdOut)]);
        }

        $filename = 'clip_' . str_replace(':', '-', $start) . '_to_' . str_replace(':', '-', $end) . '.mp4';
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($output));
        header('Content-Transfer-Encoding: binary');
        readfile($output);
        unlink($output);
        exit;
    }

    public function burnSubtitleProxy() {
        set_time_limit(0);
        ignore_user_abort(true);

        try {

        $videoUrl    = input('post.video_url', '');
        $subtitleUrl = input('post.subtitle_url', '');
        $start       = input('post.start', '00:00:00');
        $end         = input('post.end', '00:05:00');
        $delogo      = input('post.delogo', '');
        $videoId     = (int) input('post.video_id', 0);
        $videoTitle  = '';
        if ($videoId) {
            $v = Db::name('video')->where('id', $videoId)->value('title');
            if ($v) $videoTitle = $v;
        }

        if (empty($videoUrl)) {
            return json(['ok' => false, 'error' => '视频URL不能为空']);
        }

        // Call 3rd party burn service
        $apiUrl     = 'http://23.224.82.34:1233/subtitle/api/burn-subtitle-url';
        $postFields = ['videoUrl' => $videoUrl, 'start' => $start, 'end' => $end];
        if (!empty($subtitleUrl)) $postFields['subtitleUrl'] = $subtitleUrl;
        if (!empty($delogo))      $postFields['delogo']      = $delogo;

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 660);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return json(['ok' => false, 'error' => '请求失败: ' . $curlErr]);
        }

        $result = json_decode($response, true);
        if (!$result || empty($result['ok'])) {
            return json(['ok' => false, 'error' => '处理失败: ' . ($result['error'] ?? $response)]);
        }

        // Download output file from 3rd party to our server
        $remoteUrl = 'http://23.224.82.34:1233' . $result['url'];
        $burnDir   = ROOT_PATH . 'runtime/burn_output/';
        if (!is_dir($burnDir)) mkdir($burnDir, 0755, true);
        $filename  = 'burn_' . time() . '_' . rand(1000, 9999) . '.mp4';
        $outFile   = $burnDir . $filename;

        $ch2 = curl_init($remoteUrl);
        $fp  = fopen($outFile, 'wb');
        curl_setopt($ch2, CURLOPT_FILE, $fp);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch2);
        fclose($fp);
        curl_close($ch2);

        if (!file_exists($outFile) || filesize($outFile) === 0) {
            return json(['ok' => false, 'error' => '下载输出文件失败']);
        }

        $downloadUrl = url('video/downloadBurnedVideo') . '?filename=' . urlencode($filename);

        $pdo = new \PDO(
            "mysql:host=" . config('database.hostname') . ";port=" . config('database.hostport') . ";dbname=" . config('database.database') . ";charset=utf8mb4",
            config('database.username'),
            config('database.password')
        );
        $stmt = $pdo->prepare(
            "INSERT INTO burn_history (video_id, video_title, start_time, end_time, subtitle_label, wm_icon, wm_position, wm_delogo, filename, download_url, admin_by, create_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $videoId, $videoTitle, $start, $end,
            input('post.subtitle_label', ''),
            '', '', $delogo,
            $filename, $downloadUrl,
            (int) session('admin_id'), time(),
        ]);

        return json(['ok' => true, 'filename' => $filename, 'download_url' => $downloadUrl]);

        } catch (\Throwable $e) {
            return json(['ok' => false, 'error' => '服务器错误: ' . $e->getMessage()]);
        }
    }

    public function downloadBurnedVideo() {
        $filename = input('get.filename', '');
        if (empty($filename) || strpos($filename, '/') !== false || strpos($filename, '..') !== false) {
            return json(['ok' => false, 'error' => '无效文件名']);
        }

        $filePath = ROOT_PATH . 'runtime/burn_output/' . $filename;
        if (!file_exists($filePath)) {
            return json(['ok' => false, 'error' => '文件不存在或已过期']);
        }

        // Log the download
        $burnRow = Db::name('burn_history')->where('filename', $filename)->find();
        if ($burnRow) {
            Db::name('burn_download_log')->insert([
                'burn_history_id' => $burnRow['id'],
                'video_id'        => $burnRow['video_id'],
                'subtitle_label'  => $burnRow['subtitle_label'],
                'admin_by'        => session('admin_id') ?: 0,
                'download_time'   => time(),
            ]);
        }

        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function downloadVttClip() {
        $url   = input('get.url', '');
        $start = input('get.start', '00:00:00');
        $end   = input('get.end', '');
        $label = input('get.label', 'subtitle');

        if (empty($url)) {
            return json(['ok' => false, 'error' => 'URL不能为空']);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $content = curl_exec($ch);
        curl_close($ch);

        if (empty($content)) {
            return json(['ok' => false, 'error' => '无法获取字幕文件']);
        }

        $startSec = $this->hmsToSec($start);
        $endSec   = empty($end) ? PHP_INT_MAX : $this->hmsToSec($end);
        $filtered = $this->filterVtt($content, $startSec, $endSec);

        $filename = $label . '_' . str_replace(':', '-', $start) . '_to_' . str_replace(':', '-', $end) . '.vtt';
        header('Content-Type: text/vtt; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $filtered;
        exit;
    }

    private function filterVtt($content, $startSec, $endSec) {
        $content = str_replace("\r\n", "\n", $content);
        $blocks  = preg_split('/\n{2,}/', trim($content));
        $out     = ['WEBVTT'];

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block) || $block === 'WEBVTT') continue;

            $lines = explode("\n", $block);
            $tsIdx = -1;
            foreach ($lines as $idx => $line) {
                if (strpos($line, '-->') !== false) { $tsIdx = $idx; break; }
            }
            if ($tsIdx === -1) continue;

            preg_match('/(\d{1,2}:\d{2}:\d{2}[.,]\d+|\d{2}:\d{2}[.,]\d+)\s*-->\s*(\d{1,2}:\d{2}:\d{2}[.,]\d+|\d{2}:\d{2}[.,]\d+)/', $lines[$tsIdx], $m);
            if (empty($m)) continue;

            $cueStart = $this->vttTimeToSec($m[1]);
            $cueEnd   = $this->vttTimeToSec($m[2]);

            if ($cueEnd <= $startSec || $cueStart >= $endSec) continue;

            $adjStart    = max(0, $cueStart - $startSec);
            $adjEnd      = min($endSec - $startSec, $cueEnd - $startSec);
            $lines[$tsIdx] = $this->secToVttTime($adjStart) . ' --> ' . $this->secToVttTime($adjEnd);

            $out[] = implode("\n", $lines);
        }

        return implode("\n\n", $out) . "\n";
    }

    private function vttTimeToSec($t) {
        $t     = str_replace(',', '.', $t);
        $parts = explode(':', $t);
        return count($parts) === 3
            ? (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (float)$parts[2]
            : (int)$parts[0] * 60  + (float)$parts[1];
    }

    private function secToVttTime($sec) {
        $sec = max(0, $sec);
        $h   = floor($sec / 3600);
        $m   = floor(($sec % 3600) / 60);
        $s   = $sec - $h * 3600 - $m * 60;
        return sprintf('%02d:%02d:%06.3f', $h, $m, $s);
    }

    private function vttToAss($vttContent) {
        $ass  = "[Script Info]\n";
        $ass .= "ScriptType: v4.00+\n";
        $ass .= "WrapStyle: 2\n"; // No word wrap — avoids ASS_FEATURE_WRAP_UNICODE
        $ass .= "ScaledBorderAndShadow: yes\n";
        $ass .= "PlayResX: 1920\n";
        $ass .= "PlayResY: 1080\n\n";
        $ass .= "[V4+ Styles]\n";
        $ass .= "Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n";
        $ass .= "Style: Default,Arial,48,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,0,0,0,0,100,100,0,0,1,2,1,2,10,10,30,1\n\n";
        $ass .= "[Events]\n";
        $ass .= "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";

        $content = str_replace("\r\n", "\n", $vttContent);
        $blocks  = preg_split('/\n{2,}/', trim($content));

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block) || $block === 'WEBVTT') continue;

            $lines     = explode("\n", $block);
            $tsIdx     = -1;
            foreach ($lines as $idx => $line) {
                if (strpos($line, '-->') !== false) { $tsIdx = $idx; break; }
            }
            if ($tsIdx === -1) continue;

            preg_match('/(\d{1,2}:\d{2}:\d{2}[.,]\d+|\d{2}:\d{2}[.,]\d+)\s*-->\s*(\d{1,2}:\d{2}:\d{2}[.,]\d+|\d{2}:\d{2}[.,]\d+)/', $lines[$tsIdx], $m);
            if (empty($m)) continue;

            $textLines = array_slice($lines, $tsIdx + 1);
            $text      = implode('\N', array_filter($textLines, function($l) { return $l !== ''; }));
            $text      = preg_replace('/<[^>]+>/', '', $text); // strip VTT inline tags

            $ass .= 'Dialogue: 0,' . $this->vttTimeToAss($m[1]) . ',' . $this->vttTimeToAss($m[2]) . ',Default,,0,0,0,,' . $text . "\n";
        }

        return $ass;
    }

    private function vttTimeToAss($t) {
        $t    = str_replace(',', '.', $t);
        $parts = explode(':', $t);
        if (count($parts) === 2) array_unshift($parts, '0');
        $h  = (int)$parts[0];
        $m  = (int)$parts[1];
        $sf = (float)$parts[2];
        $s  = (int)$sf;
        $cs = (int)round(($sf - $s) * 100);
        return sprintf('%d:%02d:%02d.%02d', $h, $m, $s, $cs);
    }

    private function hmsToSec($hms) {
        $parts = explode(':', $hms);
        return (int)($parts[0] ?? 0) * 3600 + (int)($parts[1] ?? 0) * 60 + (int)($parts[2] ?? 0);
    }

    private function calcDuration($start, $end) {
        $sec = max(0, $this->hmsToSec($end) - $this->hmsToSec($start));
        return sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
    }

    public function downloadClipPage() {
        $url  = input('get.url', '');
        $id   = input('get.id', 0);
        // video_dl_url is the download server base, e.g. http://23.225.92.2:16562
        $base = rtrim(env('app.video_dl_url') ?: Configs::get('zimu_url'), '/');

        // Extract path from URL (handles both relative and absolute URLs)
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        $mp4Url = '';
        $hlsUrl = '';
        if (preg_match('#/ms/amnew/([^/]+)/#', $path, $m)) {
            $folder = $m[1];
            $mp4Url = $base . '/ms/amnew/' . $folder . '/' . $folder . '.mp4';
            $hlsUrl = $base . '/ms/amnew/' . $folder . '/hls/1/index.m3u8';
        }

        // Derive VTT URLs: {base}/ms/amnew/video_id_{id}/{lang}/subtitles.vtt
        // Only include languages that have a non-empty zimu field in the DB
        $zimuList = [];
        if ($id) {
            $video = Db::name('video')->where('id', $id)->field('mash,zimu,zimu_zh,zimu_en,zimu_ru,zimu_ms,zimu_th,zimu_es,zimu_vi')->find();
            if ($video) {
                $labelMap = ['zimu'=>'原字幕','zimu_zh'=>'中文字幕','zimu_en'=>'英文字幕','zimu_ru'=>'俄文字幕','zimu_ms'=>'马来字幕','zimu_th'=>'泰文字幕','zimu_es'=>'西班牙字幕','zimu_vi'=>'越南字幕'];
                foreach ($labelMap as $field => $label) {
                    if (!empty($video[$field])) {
                        $zimuPath = $video[$field];
                        // Use stored path directly — may be absolute URL or relative path
                        $zimuUrl  = preg_match('#^https?://#', $zimuPath) ? $zimuPath : $base . '/' . ltrim($zimuPath, '/');
                        $zimuList[] = ['label' => $label, 'url' => $zimuUrl];
                    }
                }
            }
        }

        $mash = !empty($video['mash']) ? $video['mash'] : ('clip_' . $id);
        $this->assign('video_id',   $id);
        $this->assign('video_url',  $url);
        $this->assign('mp4_url',    $mp4Url);
        $this->assign('hls_url',    $hlsUrl);
        $this->assign('zimu_list',  json_encode($zimuList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assign('video_mash', $mash);
        return $this->fetch('download_clip');
    }

    /*
     * 删除
     */
    public function del(){
        $id=input("param.id");
        $result=$this->model->where(["id"=>$id])->delete();
        if ($result){
            return json(["code"=>1,"msg"=>"删除成功"]);
        }else{
            return json(["code"=>0,"msg"=>"删除失败"]);
        }
    }

    public function clearVideo() {
        $redis = new Redis();
        $keys = $redis->keys('video*');
        if($keys){
            $redis->del($keys);
        }
        $this->success('清除视频缓存成功!');
    }

    public function watchStatistics()
    {
        $param          = input();
        $param['page']  = !empty($param['page']) ? max(1, intval($param['page'])) : 1;
        $param['limit'] = !empty($param['limit']) ? min(100, max(1, intval($param['limit']))) : 20;
        $param['date']  = $param['date'] ?? date('Y-m-d');
        
        // 验证日期格式
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $param['date'])) {
            return json(['code' => 0, 'msg' => '日期格式错误']);
        }
        
        $summaryTable = 'video_play_daily_summary';
        $videoTable   = 'video';
        $tagTable     = 'tag';
        
        try {
            // 检查汇总表是否存在
            $tableExists = Db::query("SHOW TABLES LIKE '{$summaryTable}'");
            
            if (empty($tableExists)) {
                $this->assign([
                    'list'          => [],
                    'total'         => 0,
                    'page'          => $param['page'],
                    'limit'         => $param['limit'],
                    'date'          => $param['date'],
                    'warning'       => '汇总表尚未创建，请先运行每日汇总生成任务。',
                    'tag_table'     => $tagTable,
                    'param'         => array_merge($param, ['page' => '{page}', 'limit' => '{limit}'])
                ]);
                return $this->fetch('watch_statistics');
            }
            
            // 1️⃣ 从汇总表查询（快速！）
            $query = Db::name($summaryTable)
                ->alias('summary')
                ->field([
                    'summary.vid as video_id',
                    'summary.watch_count',
                    'v.publish_date',
                    'v.insert_time',
                    'v.title',
                    'v.tags',
                    'v.mash'
                ])
                ->join([$videoTable => 'v'], 'v.id = summary.vid')
                ->where('summary.date', $param['date'])
                ->order('summary.watch_count', 'desc');
            
            // 获取总数
            $total = $query->count();
            $list  = $query->page($param['page'], $param['limit'])->select();
            
            // 2️⃣ 处理标签
            foreach ($list as &$item) {
                if (!empty($item['tags'])) {
                    $tagIds = explode(',', $item['tags']);
                    $tagNames = Db::name($tagTable)
                        ->where('id', 'in', $tagIds)
                        ->column('name');
                    $item['tags_display'] = implode(', ', $tagNames);
                } else {
                    $item['tags_display'] = '';
                }
                
                $item['watch_count'] = intval($item['watch_count']);
                $item['title']       = $item['title'] ?? '';
                $item['tags']        = $item['tags'] ?? '';
            }
            
            // 3️⃣ 检查数据状态
            $warning = '';
            if ($total === 0) {
                $warning = "该日期 ({$param['date']}) 没有观看记录或汇总数据尚未生成。";
            }
            
            // 4️⃣ 检查数据时效性（是否超过180天）
            $summaryDate  = strtotime($param['date']);
            $sixMonthsAgo = strtotime('-180 days');
            
            if ($summaryDate < $sixMonthsAgo) {
                $warning = "注意：查询日期 ({$param['date']}) 已超过180天，汇总数据已被清理。";
            }
            
            // 5️⃣ 非AJAX请求，渲染模板
            $this->assign([
                'list'          => $list,
                'total'         => $total,
                'page'          => $param['page'],
                'limit'         => $param['limit'],
                'date'          => $param['date'],
                'warning'       => $warning,
                'tag_table'     => $tagTable,
                'param'         => array_merge($param, ['page' => '{page}', 'limit' => '{limit}'])
            ]);
            return $this->fetch('watch_statistics');
        } catch (\Exception $e) {
            \think\facade\Log::error('视频观看统计失败: ' . $e->getMessage());
            return json(['code' => 0, 'msg' => '获取失败: ' . $e->getMessage()]);
        }
    }

    public function burnHistory()
    {
        $page  = input('get.page', 1);
        $limit = input('get.limit', 20);

        $total = Db::name('burn_history')->count();
        $list  = Db::name('burn_history')
            ->order('create_time', 'desc')
            ->page($page, $limit)
            ->select();

        $adminIds = array_filter(array_unique(array_column($list, 'admin_by')));
        $adminMap = [];
        if (!empty($adminIds)) {
            $adminMap = Db::name('admin')->whereIn('id', $adminIds)->column('username', 'id');
        }
        foreach ($list as &$row) {
            $row['admin_name'] = $adminMap[$row['admin_by']] ?? '-';
        }

        $this->assign('list',  $list);
        $this->assign('total', $total);
        $this->assign('page',  $page);
        $this->assign('limit', $limit);
        return $this->fetch('video/burn_history');
    }

    public function burnDownloadStats()
    {
        $param          = input();
        $param['page']  = !empty($param['page'])  ? max(1, intval($param['page']))              : 1;
        $param['limit'] = !empty($param['limit']) ? min(100, max(1, intval($param['limit'])))   : 20;
        $param['date']  = !empty($param['date'])  ? $param['date'] : date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $param['date'])) {
            $param['date'] = date('Y-m-d');
        }

        $startTs = strtotime($param['date'] . ' 00:00:00');
        $endTs   = strtotime($param['date'] . ' 23:59:59');

        $total = Db::name('burn_download_log')->alias('bdl')
            ->join(['video' => 'v'], 'v.id = bdl.video_id')
            ->where('bdl.download_time', '>=', $startTs)
            ->where('bdl.download_time', '<=', $endTs)
            ->group('bdl.video_id, bdl.subtitle_label')
            ->count();

        $list = Db::name('burn_download_log')->alias('bdl')
            ->field(['bdl.video_id', 'bdl.subtitle_label', 'v.mash', 'v.title', 'COUNT(*) as download_count'])
            ->join(['video' => 'v'], 'v.id = bdl.video_id')
            ->where('bdl.download_time', '>=', $startTs)
            ->where('bdl.download_time', '<=', $endTs)
            ->group('bdl.video_id, bdl.subtitle_label')
            ->order('download_count', 'desc')
            ->page($param['page'], $param['limit'])
            ->select();

        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
            'date'  => $param['date'],
            'param' => array_merge($param, ['page' => '{page}', 'limit' => '{limit}']),
        ]);
        return $this->fetch('video/burn_download_stats');
    }

    public function burnHistoryDel()
    {
        $id  = input('post.id', 0);
        $row = Db::name('burn_history')->where('id', $id)->find();
        if (!$row) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }
        Db::name('burn_history')->where('id', $id)->delete();
        return json(['code' => 1, 'msg' => '删除成功']);
    }
}