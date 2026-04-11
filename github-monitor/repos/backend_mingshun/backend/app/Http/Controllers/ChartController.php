<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chart;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ChartController extends Controller
{
    /**
     * Shared Redis cache helper
     */
    private function rememberRedis(string $cacheKey, int $ttl, callable $callback)
    {
        $redis = Redis::connection('default');

        if ($redis->exists($cacheKey)) {
            return json_decode($redis->get($cacheKey), true);
        }

        $data = $callback();
        $redis->setex($cacheKey, $ttl, json_encode($data));

        return $data;
    }

    /**
     * Get request date (default today)
     */
    private function getRequestDate(Request $request = null)
    {
        return $request?->input('date') ?? date('Y-m-d');
    }

    public function dailyVideoType(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_dailyVideoType_{$date}";

        $data = $this->rememberRedis($cacheKey, 172800, function () {
            $data = Chart::getDailyVideoTypes();
            $data['title'] = '新增视频分类';
            $data['tableTitle'] = ['分类', '昨天', '总数'];
            $data['rowTotal'] = 2;
            return $data;
        });

        $chart = view('widget.chart', compact('data'));
        return view('chartTemplate', compact('chart'));
    }

    public function dailyVideoUploader(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_dailyVideoUploader_{$date}";

        $data = $this->rememberRedis($cacheKey, 172800, function () {
            $data = Chart::getDailyVideoUploader();
            $data['title'] = '上传手上传视频';
            $data['tableTitle'] = ['上传手', '昨天', '上传成功率', '总数'];
            $data['rowTotal'] = 2;
            return $data;
        });

        $chart = view('widget.chart', compact('data'));
        return view('chartTemplate', compact('chart'));
    }

    public function dailyVideoReviewer(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_dailyVideoReviewer_{$date}";

        $data = $this->rememberRedis($cacheKey, 172800, function () {
            $data = Chart::getDailyVideoReviewer();
            $data['title'] = '审核手审核视频';
            $data['tableTitle'] = ['审核手', '昨天', '总数'];
            $data['rowTotal'] = 2;
            return $data;
        });

        $chart = view('widget.chart', compact('data'));
        return view('chartTemplate', compact('chart'));
    }

    public function dailyVideoChoose(Request $request)
    {
        $date = $this->getRequestDate($request);
        $chart = '';

        // 分类
        $data = $this->rememberRedis("config_dailyVideoChooseType_{$date}", 172800, function () {
            $data = Chart::getDailyVideoChooseType();
            $data['title'] = '项目预选视频分类';
            $data['tableTitle'] = ['分类', '昨天', '总数'];
            $data['rowTotal'] = 2;
            return $data;
        });

        $chart .= view('widget.chart', compact('data'));
        $chart .= '<hr>';

        // 状态
        $data = $this->rememberRedis("config_dailyVideoChooseStatus_{$date}", 172800, function () {
            $data = Chart::getDailyVideoChooseStatus();
            $data['title'] = '项目预选视频状态';
            $pre_set_title = [0 => '项目'];
            $tableTitle = array_replace($pre_set_title, VideoChoose::STATUS);
            $tableTitle[] = '总数';
            $data['tableTitle'] = $tableTitle;
            $data['rowTotal'] = 1;
            $data['count'] = 2;
            return $data;
        });

        $chart .= view('widget.chart', compact('data'));

        return view('chartTemplate', compact('chart'));
    }

    public function dailyCut(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_dailyCut_{$date}";

        $data = $this->rememberRedis($cacheKey, 172800, function () {
            $data = Chart::getDailyCut();
            $data['title'] = '视频切片';
            $data['tableTitle'] = ['视频切片', '昨天', '总数'];
            $data['rowTotal'] = 2;
            return $data;
        });

        $chart = view('widget.chart', compact('data'));
        return view('chartTemplate', compact('chart'));
    }

    public function dailyAiGenerate(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_dailyAiGenerate_{$date}";

        $data = $this->rememberRedis($cacheKey, 172800, function () {
            $data = Chart::getDailyAiGenerate();
            $data['title'] = '视频生成字幕';
            $data['tableTitle'] = ['视频生成字幕', '昨天', '总数'];
            $data['rowTotal'] = 2;
            return $data;
        });

        $chart = view('widget.chart', compact('data'));
        return view('chartTemplate', compact('chart'));
    }

    public function dailyPhoto(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_dailyPhoto_{$date}";

        $data = $this->rememberRedis($cacheKey, 172800, function () {
            $data = Chart::getDailyPhoto();
            $data['title'] = '项目增加水印';
            $data['tableTitle'] = ['项目', '增加水印中', '增加水印失败', '已完成', '总数'];
            $data['rowTotal'] = 2;
            return $data;
        });

        $chart = view('widget.chart', compact('data'));
        return view('chartTemplate', compact('chart'));
    }

    public function cutRealServerStatus(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_cutRealServerStatus_{$date}";

        $chart = $this->rememberRedis($cacheKey, 172800, function () use ($request) {
            return Chart::cutRealServerStatusBase($request);
        });

        return view('chartTemplate', compact('chart'));
    }

    public function cutRealServerStatusSolo(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_cutRealServerStatusSolo_{$date}";

        $chart = $this->rememberRedis($cacheKey, 172800, function () use ($request) {
            return Chart::cutRealServerStatusBase($request, 1);
        });

        return view('chartTemplate', compact('chart'));
    }

    public function videoChooseStatistic(Request $request)
    {
        $date = $this->getRequestDate($request);
        $cacheKey = "config_videoChooseStatistics_onlyStatus_{$date}";

        $data = $this->rememberRedis($cacheKey, 172800, function () use ($date){
            $results = VideoChoose::join('videos', 'video_chooses.video_id', '=', 'videos.id')
                ->leftJoin('video_types', 'video_chooses.video_id', '=', 'video_types.video_id')
                ->leftJoin('types', 'video_types.type_id', '=', 'types.id')
                ->leftJoin('projects as choose_projects', 'video_chooses.project_id', '=', 'choose_projects.id')
                ->leftJoin('projects as video_projects', 'videos.project_id', '=', 'video_projects.id')
                ->whereDate('video_chooses.created_at', $date)
                ->where('videos.status', 3)
                ->select(
                    'video_chooses.video_id',
                    'videos.title',
                    'videos.created_at',
                    'videos.cover_changed_at',
                    DB::raw('COUNT(DISTINCT video_chooses.id) as total'),
                    DB::raw('GROUP_CONCAT(DISTINCT choose_projects.name ORDER BY choose_projects.name) as project_names'),
                    DB::raw('GROUP_CONCAT(DISTINCT video_projects.name ORDER BY video_projects.name) as video_project_name'),
                    DB::raw('GROUP_CONCAT(DISTINCT types.name ORDER BY types.name) as types')
                )
                ->groupBy(
                    'video_chooses.video_id',
                    'videos.title',
                    'videos.created_at',
                    'videos.cover_changed_at'
                )
                ->orderByDesc('total')
                ->orderByDesc('created_at')
                ->limit(1000)
                ->get();

            $data = [
                'title' => '选片数据统计(前1000)',
                'rowTotal' => 1,
                'tableTitle' => ['Id', "标题", '选片次数', '选片项目', '来源', '分类', '创建时间', '图片更新时间'],
                'total' => [],
                'hideChart' => true,
            ];

            foreach ($results as $video) {
                $data['total'][$video->video_id] = [
                    $video->title,
                    $video->total,
                    $video->project_names,
                    $video->uploader ? $video->video_project_name : '接口传入',
                    $video->types,
                    Carbon::parse($video->created_at)->toDateString(),
                    Carbon::parse($video->cover_changed_at)->toDateString(),
                ];
            }

            return $data;
        });

        foreach ($data['total'] as &$row) {
            $row[2] = $row[2] ? implode(', ', explode(',', $row[2])) : '';
            $row[3] = $row[3] ? implode(', ', explode(',', $row[3])) : '';
            $row[4] = $row[4] ? implode(', ', explode(',', $row[4])) : '';
        }
        $data['filter'] = '统计时间: <input type="date" name="date" id="date" value="'. $date . '"><br>';
        $data['script'] = '$("#date").change(function() {
            var currentUrl = window.location.href;
            var urlWithoutParams = currentUrl.split("?")[0];
            window.location.href = urlWithoutParams + "?date=" + $(this).val();
        });';

        $chart = view('widget.chart', compact('data'));
        return view('chartTemplate', compact('chart'));
    }
}
