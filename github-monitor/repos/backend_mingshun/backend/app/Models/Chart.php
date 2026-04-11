<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Models\Video;
use App\Models\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Chart extends Model
{
   public static function getDailyVideoTypes(){
        $typesLabels = Type::all()->pluck('name');
        list($datas,$labels,$lastDate,$yesterdayDate) = Chart::processPreData($typesLabels);
        $videoCounts = Video::select('types.name as type',DB::raw('DATE(videos.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->join('video_types', 'videos.id', '=', 'video_types.video_id')
            ->join('types', 'video_types.type_id', '=', 'types.id')
            ->where('videos.created_at','>',$lastDate . " 00:00:00")
            ->groupBy('types.name', 'date')
            ->get()->toArray();
        
        $yesterdayData = [];
        foreach ($videoCounts as $videoCount) {
            $type = $videoCount['type'];
            $date = $videoCount['date'];
            $count = $videoCount['count'];
            $key = array_search($date, $labels);
            $datas[$type][$key]=$count;
            if($date == $yesterdayDate){
                $yesterdayData[$type]=$count;
            }
        }

        $totalCountArray = Type::withCount('videos')->get()->pluck('videos_count','name')->toArray();
        $totalCountData = [];
        $grantTotal = 0;
        $grantYesterdayTotal = 0;
        foreach($typesLabels as $typesLabel){
            $totalCountData[$typesLabel]['yesterday'] = $yesterdayData[$typesLabel] ?? 0;
            $totalCountData[$typesLabel]['total'] = $totalCountArray[$typesLabel] ?? 0;
            if($totalCountArray[$typesLabel] ?? 0){
                $grantTotal += $totalCountArray[$typesLabel];
            }
            if($yesterdayData[$typesLabel] ?? 0){
                $grantYesterdayTotal += $yesterdayData[$typesLabel];
            }
        }
        $totalCountData['<b>总数</b>']['yesterday'] = '<b>'.$grantYesterdayTotal.'</b>';
        $totalCountData['<b>总数</b>']['total'] = '<b>'.$grantTotal.'</b>';
        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => $totalCountData
        ];
   }

   public static function getDailyVideoUploader(){
        $uploaderLabels = User::active()->whereHas('role', function ($query) {
            $query->where('role_id', 3);
        })->pluck('username','id');
        $keysUploaderArray = array_keys($uploaderLabels->toArray());
        list($datas,$labels,$lastDate,$yesterdayDate)= Chart::processPreData($uploaderLabels);
        $videoUploaderCounts = Video::select('users.username as username',DB::raw('DATE(videos.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->join('users', 'videos.uploader', '=', 'users.id')
            ->whereIn('videos.uploader',$keysUploaderArray)
            ->where('videos.created_at','>',$lastDate . " 00:00:00")
            ->groupBy('videos.uploader', 'date')
            ->get()->toArray();
        $yesterdayData = [];
        foreach ($videoUploaderCounts as $videoCount) {
            $uploader = $videoCount['username'];
            $date = $videoCount['date'];
            $count = $videoCount['count'];
            $key = array_search($date, $labels);
            $datas[$uploader][$key]=$count;
            if($date == $yesterdayDate){
                $yesterdayData[$uploader]=$count;
            }
        }
        
        $totalCountArray = User::active()->withCount(['videos' => function($query) use ($keysUploaderArray){
            $query->whereIn('videos.uploader',$keysUploaderArray);
        }])->whereHas('videos', function ($query) use ($keysUploaderArray){
            $query->whereIn('videos.uploader',$keysUploaderArray);
        })->get()->pluck('videos_count','username')->toArray();
        $totalSuccessArray = User::active()->withCount(['videos' => function($query) use ($keysUploaderArray){
            $query->whereIn('videos.uploader',$keysUploaderArray);
            $query->where('videos.status',3);
        }])->whereHas('videos', function ($query) use ($keysUploaderArray){
            $query->whereIn('videos.uploader',$keysUploaderArray);
        })->get()->pluck('videos_count','username')->toArray();
        $totalCountData = [];
        $grantTotal = 0;
        $grantYesterdayTotal = 0;
        foreach($uploaderLabels as $uploaderLabel){
            $total = $totalCountArray[$uploaderLabel] ?? 0;
            $totalSuccess = $totalSuccessArray[$uploaderLabel] ?? 0;
            $totalCountData[$uploaderLabel]['yesterday'] = $yesterdayData[$uploaderLabel] ?? 0;
            $totalCountData[$uploaderLabel]['percentage'] = (($total!=0)?number_format((float)($totalSuccess / $total) * 100, 2, '.', ''): "0.00") . "%";
            $totalCountData[$uploaderLabel]['total'] = $total;
            if($total){
                $grantTotal += $totalCountArray[$uploaderLabel];
            }
            if($yesterdayData[$uploaderLabel] ?? 0){
                $grantYesterdayTotal += $yesterdayData[$uploaderLabel];
            }
        }
        $totalCountData['<b>总数</b>']['yesterday'] = '<b>'.$grantYesterdayTotal.'</b>';
        $totalCountData['<b>总数</b>']['percentage'] = '';
        $totalCountData['<b>总数</b>']['total'] = '<b>'.$grantTotal.'</b>';
        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => $totalCountData
        ];
   }

   public static function getDailyVideoReviewer(){
        $reviwerLabels = User::active()->whereHas('role', function ($query) {
            $query->where('role_id', 4);
        })->pluck('username','id');
        $keysReviwerArray = array_keys($reviwerLabels->toArray());
        list($datas,$labels,$lastDate,$yesterdayDate)= Chart::processPreData($reviwerLabels);
        $videoFirstReviewerCounts = Video::select('users.username as username',DB::raw('DATE(videos.first_approved_at) as date'), DB::raw('COUNT(*) as count'))
            ->join('users', 'videos.first_approved_by', '=', 'users.id')
            ->whereIn('videos.first_approved_by',$keysReviwerArray)
            ->where('videos.first_approved_at','>',$lastDate . " 00:00:00")
            ->groupBy('videos.first_approved_by', 'date')
            ->get()->toArray();
        $yesterdayData = [];
        foreach ($videoFirstReviewerCounts as $videoCount) {
            $uploader = $videoCount['username'];
            $date = $videoCount['date'];
            $count = $videoCount['count'];
            $key = array_search($date, $labels);
            $datas[$uploader][$key]=$count;
            if($date == $yesterdayDate){
                $yesterdayData[$uploader]=$count;
            }
        }
        $totalCountFirstArray = User::active()->withCount(['firstApprove' => function($query) use ($keysReviwerArray){
            $query->whereIn('videos.first_approved_by',$keysReviwerArray);
        }])->whereHas('firstApprove', function ($query) use ($keysReviwerArray){
            $query->whereIn('videos.first_approved_by',$keysReviwerArray);
        })->get()->pluck('first_approve_count','username')->toArray();
        $totalCountArray = $totalCountFirstArray;
        $totalCountData = [];
        $grantTotal = 0;
        $grantYesterdayTotal = 0;
        foreach($reviwerLabels as $uploaderLabel){
            $total = $totalCountArray[$uploaderLabel] ?? 0;
            $totalCountData[$uploaderLabel]['yesterday'] = $yesterdayData[$uploaderLabel] ?? 0;
            $totalCountData[$uploaderLabel]['total'] = $total;
            if($total){
                $grantTotal += $totalCountArray[$uploaderLabel];
            }
            if($yesterdayData[$uploaderLabel] ?? 0){
                $grantYesterdayTotal += $yesterdayData[$uploaderLabel];
            }
        }
        $totalCountData['<b>总数</b>']['yesterday'] = '<b>'.$grantYesterdayTotal.'</b>';
        $totalCountData['<b>总数</b>']['total'] = '<b>'.$grantTotal.'</b>';
        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => $totalCountData
        ];
   }

   public static function getDailyVideoChooseType(){
        $typesLabels = Type::all()->pluck('name');
        list($datas,$labels,$lastDate,$yesterdayDate) = Chart::processPreData($typesLabels);
        $videoCounts = Video::select('types.name as type',DB::raw('DATE(video_chooses.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->join('video_types', 'videos.id', '=', 'video_types.video_id')
            ->join('types', 'video_types.type_id', '=', 'types.id')
            ->join('video_chooses', 'videos.id', '=', 'video_chooses.video_id')
            ->where('video_chooses.created_at','>',$lastDate . " 00:00:00")
            ->groupBy('types.name', 'date')
            ->get()->toArray();
        $yesterdayData = [];
        foreach ($videoCounts as $videoCount) {
            $type = $videoCount['type'];
            $date = $videoCount['date'];
            $count = $videoCount['count'];
            $key = array_search($date, $labels);
            $datas[$type][$key]=$count;
            if($date == $yesterdayDate){
                $yesterdayData[$type]=$count;
            }
        }

        $totalCountArray =  Video::select('types.name as type', DB::raw('COUNT(*) as count'))
        ->join('video_types', 'videos.id', '=', 'video_types.video_id')
        ->join('types', 'video_types.type_id', '=', 'types.id')
        ->join('video_chooses', 'videos.id', '=', 'video_chooses.video_id')
        ->groupBy('types.name')
        ->get()->pluck('count','type')->toArray();
        $totalCountData = [];
        $grantTotal = 0;
        $grantYesterdayTotal = 0;
        foreach($typesLabels as $typesLabel){
            $totalCountData[$typesLabel]['yesterday'] = $yesterdayData[$typesLabel] ?? 0;
            $totalCountData[$typesLabel]['total'] = $totalCountArray[$typesLabel] ?? 0;
            if($totalCountArray[$typesLabel] ?? 0){
                $grantTotal += $totalCountArray[$typesLabel];
            }
            if($yesterdayData[$typesLabel] ?? 0){
                $grantYesterdayTotal += $yesterdayData[$typesLabel];
            }
        }
        $totalCountData['<b>总数</b>']['yesterday'] = '<b>'.$grantYesterdayTotal.'</b>';
        $totalCountData['<b>总数</b>']['total'] = '<b>'.$grantTotal.'</b>';
        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => $totalCountData
        ];
   }

   public static function getDailyVideoChooseStatus(){
        $projectsLabels = Project::all()->pluck('name');
        list($datas,$labels,$lastDate,$yesterdayDate) = Chart::processPreData($projectsLabels);
        $videoCounts = VideoChoose::select('projects.name as name',DB::raw('DATE(video_chooses.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->join('projects', 'projects.id', '=', 'video_chooses.project_id')
            ->where('video_chooses.created_at','>',$lastDate . " 00:00:00")
            ->where('video_chooses.status',7)
            ->groupBy('projects.name', 'date')
            ->get()->toArray();
        foreach ($videoCounts as $videoCount) {
            $type = $videoCount['name'];
            $date = $videoCount['date'];
            $count = $videoCount['count'];
            $key = array_search($date, $labels);
            $datas[$type][$key]=$count;
        }
        $totalCountArray = VideoChoose::select('video_chooses.status','projects.name as name', DB::raw('COUNT(*) as count'))
            ->join('projects', 'projects.id', '=', 'video_chooses.project_id')
            ->groupBy('projects.name','video_chooses.status')
            ->get()->toArray();
        foreach ($totalCountArray as $item) {
            $name = $item["name"];
            $status = $item["status"];
            $count = $item["count"];
        
            if (!isset($outputArray[$name])) {
                $outputArray[$name] = [];
            }
        
            $outputArray[$name][$status] = $count;
        }
        $totalCountData = [];
        $grantTotalCountData = [];
       
        $statusLabels = array_keys(VideoChoose::STATUS);
        $grantTotal = 0;
        foreach($projectsLabels as $projectLabel){
            $total = 0;
            foreach($statusLabels as $statusLabel){
                $temp = $outputArray[$projectLabel][$statusLabel] ?? 0;
                $totalCountData[$projectLabel][$statusLabel] = $temp;
                if(isset($grantTotalCountData[$statusLabel])){
                    $grantTotalCountData[$statusLabel] += $temp;
                }else{
                    $grantTotalCountData[$statusLabel] = $temp;
                }
                $total += $temp;
            }
            $grantTotal+= $total;
            $totalCountData[$projectLabel]['total'] = $total;
        }
        $grantTotalCountData['total'] = $grantTotal;
        foreach($grantTotalCountData as $key=>$value){
            $grantTotalCountData[$key] = '<b>' . $value . '</b>';
        }
        $totalCountData['<b>总数</b>'] = $grantTotalCountData;
        foreach($totalCountData as $key2=>$data2){
            foreach($data2 as $key=>$data){
                if(isset(VideoChoose::STATUS[$key])){
                    $finalTotalCountData[$key2][VideoChoose::STATUS[$key]] = $data;
                }else{
                    $finalTotalCountData[$key2][$key] = $data;
                }
            }
           
        }
        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => $finalTotalCountData
        ];
    }

   public static function processPreData($datasLabels){
        $total = 7;
        $labels = [];
        $datas = [];
        for ($i = ($total - 1); $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = $date;
            if($i == ($total - 1)){
                $lastDate = $date;
            }elseif($i == 1){
                $yesterdayDate = $date;
            }
            foreach($datasLabels as $datasLabel){
                $datas[$datasLabel][] = 0;
                if(!isset($datas[$datasLabel]['color'])){
                    $datas[$datasLabel]['color'] = 'rgb('.floor(rand(0, 255)).','.floor(rand(0, 255)).','.floor(rand(0, 255)).')';
                }
            }
        }
        return array($datas, $labels,$lastDate,$yesterdayDate);
   }

   public static function getDailyAiGenerate(){
        $statusLabels = [
           0=> '成功',
           1=> '失败',
           2=> '待处理'
        ];
        list($datas,$labels,$lastDate,$yesterdayDate) = Chart::processPreData($statusLabels);
       
        $statusDailyCutCount = VideoChoose::select('status',DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->whereIn('status', [7, 9, 10, 11, 12, 13])
            ->where('created_at', '>', $lastDate . " 00:00:00")
            ->where(function($query) {
                $query->where('status', '!=', 7)
                    ->orWhere(function($query) {
                        $query->where('status', 7)
                                ->whereNotNull('subtitle_callback_msg');
                    });
            })
            ->groupBy('created_at', 'date', 'status')
            ->get()
            ->toArray();
        $yesterdayData = [
            '成功'=> 0,
            '失败'=> 0,
            '待处理'=> 0
        ];
        $yesterdayTotal = 0;
        foreach ($statusDailyCutCount as $videoCount) {
            $status = $videoCount['status'];
            $date = $videoCount['date'];
            $count = $videoCount['count'];
            $key = array_search($date, $labels);
            switch ($status) {
                case 7:
                    $statusKey = $statusLabels[0];
                    break;
                case 9:
                case 11:
                    $statusKey = $statusLabels[2];
                    break;
                case 10:
                case 12:
                case 13:
                    $statusKey = $statusLabels[1];
                    break;
            }
            if(isset($datas[$statusKey][$key])){
                $datas[$statusKey][$key]+=$count;
            }else{
                $datas[$statusKey][$key]=$count;
            }
            if($date == $yesterdayDate){
                $yesterdayData[$statusKey]+=$count;
                $yesterdayTotal +=$count;
            }
        }

        $totalSuccess = VideoChoose::where('status',7)->whereNotNull('subtitle_callback_msg')->count();
        $totalFail = VideoChoose::whereIn('status',[10,12,13])->count();
        $totalPending = VideoChoose::whereIn('status',[9,11])->count();
        $grandTotal = $totalSuccess+$totalFail+$totalPending;

        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => [
                '成功'=> [
                    "yesterday" => $yesterdayData['成功'],
                    "total" => $totalSuccess,
                ],
                '失败'=> [
                    "yesterday" => $yesterdayData['失败'],
                    "total" => $totalFail,
                ],
                '待处理'=> [
                    "yesterday" => $yesterdayData['待处理'],
                    "total" => $totalPending,
                ],
                "<b>总数</b>" =>[
                    "yesterday" => "<b>".$yesterdayTotal."</b>",
                    "total" => "<b>".$grandTotal."</b>"
                ]
            ]
        ];
    }

   public static function getDailyCut(){
        $statusLabels = [
           0=> '成功',
           1=> '失败',
           2=> '待处理'
        ];
        list($datas,$labels,$lastDate,$yesterdayDate) = Chart::processPreData($statusLabels);
       
        $statusDailyCutCount = VideoChoose::select('status',DB::raw('DATE(cut_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('status','!=', 1)
            ->where('cut_at','>',$lastDate . " 00:00:00")
            ->groupBy('cut_at', 'date','status')
            ->get()->toArray();
        $yesterdayData = [
            '成功'=> 0,
            '失败'=> 0,
            '待处理'=> 0
        ];
        $yesterdayTotal = 0;
        foreach ($statusDailyCutCount as $videoCount) {
            $status = $videoCount['status'];
            $date = $videoCount['date'];
            $count = $videoCount['count'];
            $key = array_search($date, $labels);
            switch ($status) {
                case 2:
                    $statusKey = $statusLabels[2];
                    break;
                case 3:
                    $statusKey = $statusLabels[1];
                    break;
                case 4:
                case 5:
                case 6:
                case 7:
                case 8:
                case 9:
                case 10:
                case 11:
                case 12:
                case 13:
                    $statusKey = $statusLabels[0];
                    break;
            }
            if(isset($datas[$statusKey][$key])){
                $datas[$statusKey][$key]+=$count;
            }else{
                $datas[$statusKey][$key]=$count;
            }
            if($date == $yesterdayDate){
                $yesterdayData[$statusKey]+=$count;
                $yesterdayTotal +=$count;
            }
        }

        $totalSuccess = VideoChoose::whereIn('status',[4,5,6,7,8])->count();
        $totalFail = VideoChoose::where('status',3)->count();
        $totalPending = VideoChoose::where('status',2)->count();
        $grandTotal = $totalSuccess+$totalFail+$totalPending;

        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => [
                '成功'=> [
                    "yesterday" => $yesterdayData['成功'],
                    "total" => $totalSuccess,
                ],
                '失败'=> [
                    "yesterday" => $yesterdayData['失败'],
                    "total" => $totalFail,
                ],
                '待处理'=> [
                    "yesterday" => $yesterdayData['待处理'],
                    "total" => $totalPending,
                ],
                "<b>总数</b>" =>[
                    "yesterday" => "<b>".$yesterdayTotal."</b>",
                    "total" => "<b>".$grandTotal."</b>"
                ]
            ]
        ];
    }

    public static function getRealServerStatus($date, $solo){
        $labels = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23];
        if($solo){
            $statusLabels = [
                '1' => '单独服务器开启', 
            ];
        }else{
            $statusLabels = [
                '1' => '共享服务器开启', 
            ];
        }
        $currentDate = Carbon::now()->toDateString();
        if($currentDate == $date){
            $currentHour = Carbon::now()->hour;
        }else{
            $currentHour = '23';
        }
        foreach($labels as $label){
            foreach($statusLabels as $statusLabel){
                if($label <= $currentHour){
                    $datas[$statusLabel][$label] = 0;
                    if(!isset($datas[$statusLabel]['color'])){
                        $datas[$statusLabel]['color'] = 'rgb('.floor(rand(0, 255)).','.floor(rand(0, 255)).','.floor(rand(0, 255)).')';
                    }
                }
            }
        }
        $realServerStatussQuery = CutRealServerStatusLog::select('status','hours', DB::raw('COUNT(*) as count'));
        if($solo){
            $realServerStatussQuery->where('namespace','!=','default');
        }else{
            $realServerStatussQuery->where('namespace','default');
        }
        $realServerStatuss =  $realServerStatussQuery->whereDate('created_at',$date)->where('status', 1)->groupBy('status','hours')->get()->toArray();
        foreach($realServerStatuss as $realServerStatus){
            if(isset($datas[$statusLabels[$realServerStatus['status']]][$realServerStatus['hours']])){
                $datas[$statusLabels[$realServerStatus['status']]][$realServerStatus['hours']]=$realServerStatus['count'];
            }
        }

        $yesterday = Carbon::now()->subDay()->toDateString();
        foreach($labels as $label){
            foreach($statusLabels as $statusLabel){
                $tables[$statusLabel][$label] = 0;
            }
            $tables['<b>总数</b>'][$label] = 0;
        }
        $realServerStatusYesterdaysQuery = CutRealServerStatusLog::select('hours','status', DB::raw('COUNT(*) as count'));
        if($solo){
            $realServerStatusYesterdaysQuery->where('namespace','!=','default');
        }else{
            $realServerStatusYesterdaysQuery->where('namespace','default');
        }
        $realServerStatusYesterdays =$realServerStatusYesterdaysQuery->whereDate('created_at',$yesterday)->where('status', 1)->groupBy('hours','status')->get()->toArray();
        foreach($realServerStatusYesterdays as $realServerStatusYesterday){
            if(isset($tables[$statusLabels[$realServerStatusYesterday['status']]][$realServerStatusYesterday['hours']])){
                $tables[$statusLabels[$realServerStatusYesterday['status']]][$realServerStatusYesterday['hours']]=$realServerStatusYesterday['count'];
            }
            $tables['<b>总数</b>'][$realServerStatusYesterday['hours']]+=$realServerStatusYesterday['count'];
        }
        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => $tables
        ];
    }

    public static function getDailyPhoto(){
        $projectsLabels = Project::all()->pluck('name');
        list($datas,$labels,$lastDate,$yesterdayDate) = Chart::processPreData($projectsLabels);
        $videoCounts = Photo::select('projects.name as name',DB::raw('DATE(photos.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->join('projects', 'projects.id', '=', 'photos.project_id')
            ->where('photos.created_at','>',$lastDate . " 00:00:00")
            ->groupBy('projects.name', 'date')
            ->get()->toArray();
        foreach ($videoCounts as $videoCount) {
            $type = $videoCount['name'];
            $date = $videoCount['date'];
            $count = $videoCount['count'];
            $key = array_search($date, $labels);
            $datas[$type][$key]=$count;
        }
        $totalCountArray = Photo::select('photos.status','projects.name as name', DB::raw('COUNT(*) as count'))
            ->join('projects', 'projects.id', '=', 'photos.project_id')
            ->groupBy('projects.name','photos.status')
            ->get()->toArray();
        foreach ($totalCountArray as $item) {
            $name = $item["name"];
            $status = $item["status"];
            $count = $item["count"];
        
            if (!isset($outputArray[$name])) {
                $outputArray[$name] = [];
            }
        
            $outputArray[$name][$status] = $count;
        }
        $totalCountData = [];
        $grantTotalCountData = [];
       
        $statusLabels = array_keys(Photo::STATUS);
        $grantTotal = 0;
        foreach($projectsLabels as $projectLabel){
            $total = 0;
            foreach($statusLabels as $statusLabel){
                $temp = $outputArray[$projectLabel][$statusLabel] ?? 0;
                $totalCountData[$projectLabel][$statusLabel] = $temp;
                if(isset($grantTotalCountData[$statusLabel])){
                    $grantTotalCountData[$statusLabel] += $temp;
                }else{
                    $grantTotalCountData[$statusLabel] = $temp;
                }
                $total += $temp;
            }
            $grantTotal+= $total;
            $totalCountData[$projectLabel]['total'] = $total;
        }
        $grantTotalCountData['total'] = $grantTotal;
        foreach($grantTotalCountData as $key=>$value){
            $grantTotalCountData[$key] = '<b>' . $value . '</b>';
        }
        $totalCountData['<b>总数</b>'] = $grantTotalCountData;
        foreach($totalCountData as $key2=>$data2){
            foreach($data2 as $key=>$data){
                if(isset(Photo::STATUS[$key])){
                    $finalTotalCountData[$key2][Photo::STATUS[$key]] = $data;
                }else{
                    $finalTotalCountData[$key2][$key] = $data;
                }
            }
           
        }
        
        return [
            'datas' => $datas,
            'labels' => $labels,
            'total' => $finalTotalCountData
        ];
    }

    public static function cutRealServerStatusBase($request, $solo = 0){
        if($request->date){
            $date = $request->date;
        }else{
            $date = Carbon::now()->toDateString();
        }
        $data = self::getRealServerStatus($date, $solo);

        if($solo){
            $data['title'] = '单独切片服务器状态';
            $data['tableTitle'] = [
                '单独切片服务器状态(昨天)',0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23
            ];
        }else{
            $data['title'] = '共享切片服务器状态';
            $data['tableTitle'] = [
                '共享切片服务器状态(昨天)',0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23
            ];
        }

        $data['rowTotal'] = 1;
        $data['filter'] = '统计时间: <input type="date" name="date" id="date" value="'. $date . '"><br>';
        $data['script'] = '$("#date").change(function() {
            var currentUrl = window.location.href;
            var urlWithoutParams = currentUrl.split("?")[0];
            window.location.href = urlWithoutParams + "?date=" + $(this).val();
        });';
        
        $data['option'] = 'scales: {
            x: {
                display: true,
            },
            y: {
                display: true,
                suggestedMin: 0,
            }
        }';
        
        $chart = view('widget.chart', compact("data"));
        $currentHour = Carbon::now()->hour;
        $cutRealServerStatusLogsQuery = CutRealServerStatusLog::whereDate('created_at',Carbon::now())->where('hours',$currentHour);
        if($solo){
            $cutRealServerStatusLogsQuery->where('namespace','!=','default');
        }else{
            $cutRealServerStatusLogsQuery->where('namespace','default');
        }
        $cutRealServerStatusLogs = $cutRealServerStatusLogsQuery->get();
        $chart .= '<br><h5>切片服务器状态('.Carbon::now()->toDateString() . ' ' . $currentHour.'小时)</h5>';
        $chart .= '<table>
                            <tbody>
                                <tr>
                                    <th>#</th>
                                    <th>切片服务器</th>
                                    <th>状态</th>
                                </tr>';
        $i = 1;
        foreach($cutRealServerStatusLogs as $cutRealServerStatusLog){
            $chart .=  
                '<tr>
                    <td>'.$i.'</td>
                    <td>'.$cutRealServerStatusLog->server.'</td>
                    <td>'.($cutRealServerStatusLog->status?'开启':'关闭').'</td>
                </tr>';
            $i++;
        }
        $chart .= '</tbody></table>';
        
        return $chart;
    }
}
