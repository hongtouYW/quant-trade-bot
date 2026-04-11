<?php

namespace App\Http\Controllers;

use App\Http\Helper;
use App\Models\Config;
use App\Models\Ftp;
use App\Models\Photo;
use App\Models\Project;
use App\Models\ProjectRules;
use App\Models\Tag;
use App\Models\TokenLogs;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelWriter;

class HomeController extends Controller
{
    public function tempUpload(Request $request){
        $image_mime_types = array(
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/webp'
        );

        $src = $this->upload($request,$image_mime_types,'上传的必须是图片');
        return response()->json([
            'src' => $src,
        ], Response::HTTP_OK);
    }

    public function tempUploadVideo(Request $request){
        $video_mime_types = array(
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
        );

       $src = $this->upload($request,$video_mime_types,'上传的必须是视频');
        return response()->json([
            'src' => $src,
        ], Response::HTTP_OK);
    }

    public function upload($request,$checkMimeType,$errorMsg){
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, $checkMimeType)) {
                throw new Exception($errorMsg);
            } 
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move(storage_path('app/public/temp'), $fileName);
            return '/storage/temp/' . $fileName;
        }
        return '';
    }

    public function calendar(Request $request){
        $filter = '';
        $script = '';
        $month = Carbon::now()->month; 
        $year = Carbon::now()->year; 
        $tables = [];
        if(Auth::user()->isSuperAdmin()){
            $users = User::whereHas('role', function ($query) {
                $query->whereIn('id', [3, 4, 7]);
            })->whereHas('projects', function ($query) {
                $query->where('project_id', Project::MINGSHUN);
            })->pluck('username','id')->toArray();
    
            $grandThisMonthTotal = 0;
            $thisMonthTable = [];
            $thisMonthTotal = [];
            $thisMonth = Carbon::now()->format('m');
            $allTemps = TokenLogs::selectRaw('user_id, type, COUNT(*) as count')
                ->whereIn('user_id', array_keys($users))
                ->whereYear('created_at', $year)->whereMonth('created_at', $thisMonth)
                ->groupBy('user_id', 'type')->get()
                ->groupBy('type')->map(fn($g) => $g->pluck('count', 'user_id')->toArray())->toArray();
            foreach(TokenLogs::TYPE as $key=>$tokenLog){
                $temps = $allTemps[$key] ?? [];
                $tempTotal = 0;
                foreach($users as $userKey=>$user){
                    $thisMonthTable[$user][$tokenLog] = $temps[$userKey] ?? 0;
                    if($temps[$userKey] ?? 0){
                        $tempTotal += $temps[$userKey];
                        if($key!=4){
                            if(isset($thisMonthTotal[$user]['total'])){
                                $thisMonthTotal[$user]['total'] += $temps[$userKey];
                            }else{
                                $thisMonthTotal[$user]['total'] = $temps[$userKey];
                            }
                        }
                    }else{
                        if($key!=4){
                            if(!isset($thisMonthTotal[$user]['total'])){
                                $thisMonthTotal[$user]['total'] = 0;
                            }
                        }
                    }
                }
                $thisMonthTable['<b>总数</b>'][$tokenLog] = '<b>' . $tempTotal . '</b>';
                if($key!=4){
                    $grandThisMonthTotal += $tempTotal;
                }
            }
            $thisMonthTable['<b>总数</b>']['total'] = '<b>' . $grandThisMonthTotal. '</b>';
            foreach($users as $key=>$user){
                $thisMonthTable[$user]['total'] = $thisMonthTotal[$user]['total'];
            }
            $tables[] = [
                'total' => $thisMonthTable,
                'tableTitle' => [
                    '用户','青色','红色',"灰色","白色", "总数"
                ],
                'title' => '这个月('.$thisMonth.')徽章数',
                'rowTotal' => 1
            ];
    
            $grandLastMonthTotal = 0;
            $lastMonthTable = [];
            $lastMonthTotal = [];
            $lastMonth = Carbon::now()->subMonth()->format('m');
            if($lastMonth == 12 && $thisMonth == 1){
                $lastMonthYear = $year - 1;
            }else{
                $lastMonthYear = $year;
            }
            $allTemps = TokenLogs::selectRaw('user_id, type, COUNT(*) as count')
                ->whereIn('user_id', array_keys($users))
                ->whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)
                ->groupBy('user_id', 'type')->get()
                ->groupBy('type')->map(fn($g) => $g->pluck('count', 'user_id')->toArray())->toArray();
            foreach(TokenLogs::TYPE as $key=>$tokenLog){
                $temps = $allTemps[$key] ?? [];
                $tempTotal = 0;
                foreach($users as $userKey=>$user){
                    $lastMonthTable[$user][$tokenLog] = $temps[$userKey] ?? 0;
                    if($temps[$userKey] ?? 0){
                        $tempTotal += $temps[$userKey];
                        if($key!=4){
                            if(isset($lastMonthTotal[$user]['total'])){
                                $lastMonthTotal[$user]['total'] += $temps[$userKey];
                            }else{
                                $lastMonthTotal[$user]['total'] = $temps[$userKey];
                            }
                        }
                    }else{
                        if($key!=4){
                            if(!isset($lastMonthTotal[$user]['total'])){
                                $lastMonthTotal[$user]['total'] = 0;
                            }
                        }
                    }
                }
                $lastMonthTable['<b>总数</b>'][$tokenLog] = '<b>' . $tempTotal . '</b>';
                if($key!=4){
                    $grandLastMonthTotal += $tempTotal;
                }
            }
            $lastMonthTable['<b>总数</b>']['total'] = '<b>' . $grandLastMonthTotal. '</b>';
            foreach($users as $key=>$user){
                $lastMonthTable[$user]['total'] = $lastMonthTotal[$user]['total'];
            }
            $tables[] = [
                'total' => $lastMonthTable,
                'tableTitle' => [
                    '用户','青色','红色',"灰色","白色", "总数"
                ],
                'title' => '上个月('.$lastMonth.')徽章数',
                'rowTotal' => 1
            ];
    
            $grandTotalTotal = 0;
            $totalTable = [];
            $totalTotal = [];
            $allTemps = TokenLogs::selectRaw('user_id, type, COUNT(*) as count')
                ->whereIn('user_id', array_keys($users))
                ->groupBy('user_id', 'type')->get()
                ->groupBy('type')->map(fn($g) => $g->pluck('count', 'user_id')->toArray())->toArray();
            foreach(TokenLogs::TYPE as $key=>$tokenLog){
                $temps = $allTemps[$key] ?? [];
                $tempTotal = 0;
                foreach($users as $userKey=>$user){
                    $totalTable[$user][$tokenLog] = $temps[$userKey] ?? 0;
                    if($temps[$userKey] ?? 0){
                        $tempTotal += $temps[$userKey];
                        if($key!=4){
                            if(isset($totalTotal[$user]['total'])){
                                $totalTotal[$user]['total'] += $temps[$userKey];
                            }else{
                                $totalTotal[$user]['total'] = $temps[$userKey];
                            }
                        }
                    }else{
                        if($key!=4){
                            if(!isset($totalTotal[$user]['total'])){
                                $totalTotal[$user]['total'] = 0;
                            }
                        }
                    }
                }
                $totalTable['<b>总数</b>'][$tokenLog] = '<b>' . $tempTotal . '</b>';
                if($key!=4){
                    $grandTotalTotal += $tempTotal;
                }
            }
            $totalTable['<b>总数</b>']['total'] = '<b>' . $grandTotalTotal. '</b>';
            foreach($users as $key=>$user){
                $totalTable[$user]['total'] = $totalTotal[$user]['total'];
            }
            $tables[] = [
                'total' => $totalTable,
                'tableTitle' => [
                    '用户','青色','红色',"灰色","白色", "总数"
                ],
                'title' => '徽章总数',
                'rowTotal' => 1
            ];    
            $filter = '<div class="col-3"><div class="row"><div class="col-3">名字: </div>';
            $filter .= '<div class="col-9">'.view('widget.input',[
                'key' => 'user_id',
                'name' => '名字',
                'type' => 'select',
                'setting' => [
                    'containerKey' => 'user_id',
                    'route' => route('users.project.select'),
                    'value' => $request->user_id ?? '',
                    'label' => $request->user_label ?? '',
                ],
            ]).'</div></div></div>';
            $filter .= '<div class="col-3"><div class="row"><div class="col-3">角色: </div>';
            $filter .=  '<div class="col-9">'.view('widget.input',[
                'key' => 'role_id',
                'name' => '角色',
                'type' => 'select',
                'setting' => [
                    'containerKey' => 'role_id',
                    'route' => route('roles.select'),
                    'value' => $request->role_id ?? '',
                    'label' => $request->role_label ?? '',
                ],
            ]).'</div></div></div>';
            $filter .= '<div class="col-lg-12" style="text-align: right;">
                <button class="btn btn-sm search-reset-btn">
                    重置
                </button>
            </div>';
            $script = "
                $('#role_id').on('change', function() {
                    var currentUrl = window.location.href;
                    var url = new URL(currentUrl);
                    var searchParams = new URLSearchParams(url.search);
                    var user_id = searchParams.has('user_id') ? searchParams.get('user_id') : '';
                    var user_label = searchParams.has('user_label') ? searchParams.get('user_label') : '';
                    var urlWithoutParams = currentUrl.split('?')[0];
                    window.location.href = urlWithoutParams + '?role_id=' + $(this).val() + '&role_label=' +  $(this).select2('data')[0].text + '&user_id=' + user_id + '&user_label=' + user_label;
                });
                $('#user_id').on('change', function() {
                    var currentUrl = window.location.href;
                    var url = new URL(currentUrl);
                    var searchParams = new URLSearchParams(url.search);
                    var role_id = searchParams.has('role_id') ? searchParams.get('role_id') : '';
                    var role_label = searchParams.has('role_label') ? searchParams.get('role_label') : '';
                    var urlWithoutParams = currentUrl.split('?')[0];
                    window.location.href = urlWithoutParams + '?user_id=' + $(this).val() + '&user_label=' +  $(this).select2('data')[0].text + '&role_id=' + role_id + '&role_label=' + role_label;
                });
                $('.search-reset-btn').on('click', function() {
                    var currentUrl = window.location.href;
                    var urlWithoutParams = currentUrl.split('?')[0];
                    window.location.href = urlWithoutParams;
                });
            " ;
        }
        return view('calendar',[
            "route"=> route('getMonthData'),
            "routeClick"=> route('getMonthDataClick'),
            "events" => $this->tokenMonthData($year, $month, $request->role_id ?? '',$request->user_id ?? ''),
            "tables" => $tables,
            "filter" => $filter,
            "script" => $script
        ]);
    }

    public function getMonthData(Request $request){
        $data = $this->tokenMonthData($request->year, $request->month, $request->role_id ?? '',$request->user_id ?? '');
        return json_encode([
            'data' => json_encode($data)
        ]);
    }

    public function tokenMonthData($year, $month,$role_id,$user_id){
        $role = [3, 4, 7];
        if(Auth::user()->isSuperAdmin()){
            if($role_id){
                $role = [$role_id];
            }
        }
        $userQuery = User::whereHas('role', function ($query) use ($role) {
            $query->whereIn('id', $role);
        })->whereHas('projects', function ($query) {
            $query->where('project_id', Project::MINGSHUN);
        });
        if(Auth::user()->isSuperAdmin()){
            if($user_id){
                $userQuery->where('id', $user_id);
            }
        }
        $users = $userQuery->pluck('username','id')->toArray();

        if(Auth::user()->isSuperAdmin()){
            $tokenLogs = TokenLogs::whereYear('created_at', $year)->whereIn('user_id',array_keys($users))
                ->whereMonth('created_at', $month)->orderBy('created_at','desc')
                ->get();
            $data = [];
            $temp = [];
            $limit = Config::getCachedConfig('calendar_show_limit') ?? 4;
            $more = true;
            foreach($tokenLogs as $tokenLog){
                $flag = true;
                $date = $tokenLog->created_at->format('Y-m-d');
                if(array_key_exists($date, $temp)){
                    if($temp[$date] >= $limit){
                        $flag = false;
                    }
                }
                if($flag){
                    if(array_key_exists($date, $temp)){
                        $temp[$date] ++ ;
                    }else{
                        $more = true;
                        $temp[$date] = 1;
                    }
                }
                if($flag){
                    if($tokenLog->user){
                        if($tokenLog->user->status == 1){
                            $name = $tokenLog->user->username;
                            if($tokenLog->type == 2){
                                $name .= "(" . $tokenLog->extra .")";
                            }
                            $data[] = [
                                'date' => $tokenLog->created_at->format('Y-m-d'),
                                'eventName' => $name,
                                'className' => TokenLogs::CALENDARSUPERADMINCLASS[$tokenLog->type],
                                'dayClassName' => '',
                                'dateColor' => '#38385c'
                            ];
                        }
                    }
                }elseif($more){
                    $data[] = [
                        'date' => $tokenLog->created_at->format('Y-m-d'),
                        'eventName' => 'more',
                        'className' => TokenLogs::CALENDARMORESUPERADMINCLASS,
                        'dayClassName' => 'clickable-day',
                        'dateColor' => '#38385c'
                    ];
                    $more = false;
                }
            }
        }else{
            $tokenLogs = TokenLogs::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where('user_id', Auth::user()->id)
                ->where('type',"!=",4)
                ->get();
            $data = [];
            foreach($tokenLogs as $tokenLog){
                $data[] = [
                    'date' => $tokenLog->created_at->format('Y-m-d'),
                    'eventName' => '',
                    'className' => '',
                    'dayClassName' => TokenLogs::CALENDARREVIEWERCLASS[$tokenLog->type],
                    'dateColor' => 'white'
                ];
            }
        }
       
        return $data;
    }

    public function getMonthDataClick(Request $request){
        $searchDate = Carbon::parse($request->time);
        $role = [3, 4, 7];
        if(Auth::user()->isSuperAdmin()){
            if($request->role_id){
                $role = [$request->role_id];
            }
        }
        $userQuery = User::whereHas('role', function ($query) use ($role) {
            $query->whereIn('id', $role);
        })->whereHas('projects', function ($query) {
            $query->where('project_id', Project::MINGSHUN);
        });
        if(Auth::user()->isSuperAdmin()){
            if($request->user_id){
                $userQuery->where('id', $request->user_id);
            }
        }
        $users = $userQuery->pluck('username','id')->toArray();
        $tokenLogs = TokenLogs::whereDate('created_at', $searchDate)->whereIn('user_id',array_keys($users))
            ->orderBy('created_at','desc')
            ->get();
        $data = [];
        $data['uploader'] = [];
        $data['reviewer'] = [];
        $data['coverer'] = [];
        foreach($tokenLogs as $tokenLog){
            if($tokenLog->user){
                if($tokenLog->user->status == 1){
                    $name = $tokenLog->user->username;
                    if($tokenLog->type == 2){
                        $name .= "(" . $tokenLog->extra .")";
                    }
                    if($tokenLog->user->isUploader()){
                        $data['uploader'][] = '<div class="badge-modal gc-event '.TokenLogs::CALENDARSUPERADMINCLASS[$tokenLog->type].'">'.$name.'</div>';
                    }
                    if($tokenLog->user->isReviewer()){
                        $data['reviewer'][] = '<div class="badge-modal gc-event '.TokenLogs::CALENDARSUPERADMINCLASS[$tokenLog->type].'">'.$name.'</div>';
                    }
                    if($tokenLog->user->isCoverer()){
                        $data['coverer'][] = '<div class="badge-modal gc-event '.TokenLogs::CALENDARSUPERADMINCLASS[$tokenLog->type].'">'.$name.'</div>';
                    }
                }
            }
        }
        return $data;
    }

    public function temp(){
        dd(VideoChoose::where('status', 3)->where('cut_callback_msg','Attempt to read property "msg" on string--subtitle-service-v2-0')
            ->orderBy('id')->get()->pluck('id'));
    }

    public function exportExcel()
    {
        $fileName = Helper::export();
        $filePath = storage_path('app/public/temp/' . $fileName);

        return response()->download($filePath)->deleteFileAfterSend(false);
    }
}
