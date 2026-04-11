<div class="sidebar">
    <ul class="nav-links">
        <li class='{{ Request::is('dashboard') ? 'active' : '' }}'>
            <a href="{{ route('dashboard') }}">
                <i class='bx bx-home-alt-2'></i>
                <span class="link_name">控制面板</span>
            </a>
        </li>
        @if (Auth::user()->checkUserRole([1, 999]))
            <li class='{{ Request::is('chart*') ? 'active showMenu' : '' }}'>
                <div class="icon-link">
                    <a class='arrow menu-a'>
                        <i class='bx bx-bar-chart-alt-2'></i>
                        <span class="link_name">报表</span>
                    </a>
                    <i class='bx bxs-chevron-down arrow'></i>
                </div>
                <ul class="sub-menu">
                    <li>
                        <a class='{{ Request::is('chart/cutRealServerStatus') ? 'active' : '' }}'
                            href="{{ route('chart.cutRealServerStatus') }}">共享切片服务器状态</a>
                    </li>
                    <li>
                        <a class='{{ Request::is('chart/cutRealServerStatusSolo') ? 'active' : '' }}'
                            href="{{ route('chart.cutRealServerStatusSolo') }}">单独切片服务器状态</a>
                    </li>
                    @if (!Auth::user()->checkUserRole([999]))
                        <li>
                            <a class='{{ Request::is('chart/dailyVideoType') ? 'active' : '' }}'
                                href="{{ route('chart.dailyVideoType') }}">新增视频分类</a>
                        </li>
                        <li>
                            <a class='{{ Request::is('chart/dailyVideoUploader') ? 'active' : '' }}'
                                href="{{ route('chart.dailyVideoUploader') }}">上传手上传视频</a>
                        </li>
                        <li>
                            <a class='{{ Request::is('chart/dailyVideoReviewer') ? 'active' : '' }}'
                                href="{{ route('chart.dailyVideoReviewer') }}">审核手审核视频</a>
                        </li>
                        <li>
                            <a class='{{ Request::is('chart/dailyVideoChoose') ? 'active' : '' }}'
                                href="{{ route('chart.dailyVideoChoose') }}">项目预选视频</a>
                        </li>
                        <li>
                            <a class='{{ Request::is('chart/dailyCut') ? 'active' : '' }}'
                                href="{{ route('chart.dailyCut') }}">视频切片</a>
                        </li>
                        <li>
                            <a class='{{ Request::is('chart/dailyAiGenerate') ? 'active' : '' }}'
                                href="{{ route('chart.dailyAiGenerate') }}">视频生成字幕</a>
                        </li>
                        <li>
                            <a class='{{ Request::is('chart/dailyPhoto') ? 'active' : '' }}'
                                href="{{ route('chart.dailyPhoto') }}">项目增加水印</a>
                        </li>
                        <li>
                            <a class='{{ Request::is('chart/videoChooseStatistic') ? 'active' : '' }}'
                                href="{{ route('chart.videoChooseStatistic') }}">选片数据统计</a>
                        </li>
                    @endif
                </ul>
            </li>
        @endif
        @if(!Auth::user()->checkUserRole([999]))
            @if(Auth::user()->type == 3 || Auth::user()->type == 1)
                <li class='{{ Request::is('videos*') ? 'active showMenu' : '' }}'>
                    <div class="icon-link">
                        <a class='arrow menu-a'>
                            <i class='bx bxs-videos'></i>
                            <span class="link_name">视频</span>
                        </a>
                        <i class='bx bxs-chevron-down arrow'></i>
                    </div>
                    <ul class="sub-menu">
                        <li>
                            <a class='{{ Request::is('videos') ? 'active' : '' }}' href="{{ route('videos.index') }}">{{Auth::user()->checkUserRole([4, 7]) ? "任务" : "视频"}}列表</a>
                        </li>
                        @if (Auth::user()->checkUserRole([1, 3]))
                            <li><a class='{{ Request::is('videos/create') ? 'active' : '' }}'
                                    href="{{ route('videos.create') }}">上传</a></li>
                        @endif
                        @if (Auth::user()->checkUserRole([1, 2, 4]))
                            <li><a class='{{ Request::is('videosRereview') ? 'active' : '' }}'
                                    href="{{ route('videosRereview.index') }}">{!!(Auth::user()->checkUserRole([1, 2]) ? "重新审核" : "<span style='color:red'>任务失败</span>")!!}</a></li>
                        @endif
                        @if (Auth::user()->checkUserRole([1, 2, 4, 7]))
                            <li><a class='{{ Request::is('videosSuccess') ? 'active' : '' }}'
                                    href="{{ route('videosSuccess.index') }}">{{Auth::user()->checkUserRole([1, 2]) ? "审核" : "任务"}}成功</a></li>
                        @endif
                        @if (Auth::user()->checkUserRole([1, 2, 5, 6]))
                            <li><a class='{{ Request::is('videosChoose') ? 'active' : '' }}'
                                    href="{{ route('videoChoose.index') }}">预选区</a></li>
                        @endif
                        @if ((Auth::user()->checkUserRole([1, 2, 5, 6])) || (Auth::user()->checkUserRole([3]) && Auth::user()->projects->first()?->direct_cut))
                            <li><a class='{{ Request::is('videosChooseHistory') ? 'active' : '' }}'
                                    href="{{ route('videoChooseHistory.index') }}">{{Auth::user()->checkUserRole([3]) ? "切片历史" : "预选历史"}}</a></li>
                        @endif
                    </ul>
                </li>
            @endif
        @endif
        @if(!Auth::user()->checkUserRole([999]))
            @if(Auth::user()->checkUserRole([1,2]) ||Auth::user()->projects->first()?->enable_photo)
                <li class='{{ Request::is('photos*') ? 'active showMenu' : '' }}'>
                    <div class="icon-link">
                        <a class='arrow menu-a'>
                            <i class='bx bx-image'></i>
                            <span class="link_name">图片</span>
                        </a>
                        <i class='bx bxs-chevron-down arrow'></i>
                    </div>
                    <ul class="sub-menu">
                        @if (Auth::user()->checkUserRole([3]))
                        <li>
                            <a class='{{ Request::is('photos/create') ? 'active' : '' }}'
                            href="{{ route('photos.create') }}">上传</a>
                        </li>
                        @endif
                        @if (Auth::user()->checkUserRole([1, 2, 3, 6]))
                            <li>
                                <a class='{{ Request::is('photos') ? 'active' : '' }}' href="{{ route('photos.index') }}">图片列表</a>
                            </li>
                        @endif
                       
                    </ul>
                </li>
            @endif
        @endif
        {{-- @if(Auth::user()->type == 3 || Auth::user()->type == 2)
            <li class='{{ Request::is('posts*') ? 'active showMenu' : '' }}'>
                <div class="icon-link">
                    <a class='arrow menu-a'>
                        <i class='bx bx-collection'></i>
                        <span class="link_name">帖子</span>
                    </a>
                    <i class='bx bxs-chevron-down arrow'></i>
                </div>
                <ul class="sub-menu">
                    <li><a class='{{ Request::is('posts') ? 'active' : '' }}' href="{{ route('posts.index') }}">帖子列表</a>
                    </li>

                    @if (Auth::user()->checkUserRole([1, 3]))
                        <li><a class='{{ Request::is('posts/create') ? 'active' : '' }}'
                                href="{{ route('posts.create') }}">上传</a></li>
                    @endif
                    @if (Auth::user()->checkUserRole([1, 2]))
                        <li><a class='{{ Request::is('postsFailed') ? 'active' : '' }}'
                                href="{{ route('postsFailed.index') }}">审核失败</a></li>
                    @endif
                    @if (Auth::user()->checkUserRole([1, 5, 6]))
                        <li><a class='{{ Request::is('postsChoose') ? 'active' : '' }}'
                                href="{{ route('postsChoose.index') }}">预选区</a></li>
                    @endif
                    @if (Auth::user()->checkUserRole([1, 5, 6]))
                        <li><a class='{{ Request::is('postsChooseHistory') ? 'active' : '' }}'
                                href="{{ route('postsChooseHistory.index') }}">预选历史</a></li>
                    @endif
                </ul>
            </li>
        @endif --}}
      
        @if (Auth::user()->checkUserRole([3, 4, 7]))
            @if( Auth::user()->projects->pluck('id')->toArray())
                @if (in_array(\App\Models\Project::MINGSHUN, Auth::user()->projects->pluck('id')->toArray())) 
                    <li class='{{ Request::is('calendar*') ? 'active' : '' }}'>
                        <a href="{{ route('calendar') }}">
                            <i class='bx bxs-calendar'></i>
                            <span class="link_name">日历</span>
                        </a>
                    </li>
                @endif
            @endif
        @elseif(Auth::user()->checkUserRole([1, 2]))
            <li class='{{ Request::is('calendar*') ? 'active' : '' }}'>
                <a href="{{ route('calendar') }}">
                    <i class='bx bxs-calendar'></i>
                    <span class="link_name">日历</span>
                </a>
            </li>
        @endif
        @if (Auth::user()->checkUserRole([1, 2, 3]))
            <li class='{{ Request::is('types*') ? 'active' : '' }}'>
                <a href="{{ route('types.index') }}">
                    <i class='bx bx-grid-alt'></i>
                    <span class="link_name">分类</span>
                </a>
            </li>
        @endif
        @if (Auth::user()->checkUserRole([1, 2]))
            <li class='{{ Request::is('tags*') ? 'active' : '' }}'>
                <a href="{{ route('tags.index') }}">
                    <i class='bx bx-purchase-tag-alt'></i>
                    <span class="link_name">标签</span>
                </a>
            </li>
        @endif
        @if (Auth::user()->checkUserRole([1, 2]))
            <li class='{{ Request::is('authors*') ? 'active' : '' }}'>
                <a href="{{ route('authors.index') }}">
                    <i class='bx bx-male-female'></i>
                    <span class="link_name">作者</span>
                </a>
            </li>
        @endif
        @if (Auth::user()->checkUserRole([1, 3]))
            <li class='{{ Request::is('ftps*') ? 'active' : '' }}'>
                <a href="{{ route('ftps.index') }}">
                    <i class='bx bx-folder'></i>
                    <span class="link_name">FTP</span>
                </a>
            </li>
        @endif
        @if ((Auth::user()->checkUserRole([1])) || (Auth::user()->checkUserRole([5,6]) && Auth::user()->projects->first()) 
            || (Auth::user()->checkUserRole([3]) && Auth::user()->projects->first()?->direct_cut)
            || (Auth::user()->checkUserRole([3]) && Auth::user()->projects->first()?->enable_photo))
            <li class='{{ (Request::is('prules*') || Request::is('pphotorules*')|| Request::is('ptypes*') || Request::is('pservers*') || Request::is('projects*'))  ? 'active showMenu' : '' }}'>
                <div class="icon-link">
                    <a class='arrow menu-a'>
                        <i class='bx bx-briefcase-alt-2'></i>
                        <span class="link_name">项目设置</span>
                    </a>
                    <i class='bx bxs-chevron-down arrow'></i>
                </div>
                <ul class="sub-menu">
                    <li>
                        <a class='{{ Request::is('pphotorules*') ? 'active' : '' }}' href="{{ route('pphotorules.index') }}">图片规则列表</a>
                    </li>
                    <li>
                        <a class='{{ Request::is('prules') ? 'active' : '' }}' href="{{ route('prules.index') }}">规则列表</a>
                    </li>
                    <li>
                        <a class='{{ Request::is('ptypes') ? 'active' : '' }}' href="{{ route('ptypes.index') }}">主题列表</a>
                    </li>
                    @if(!Auth::user()->isSuperAdmin())
                        @if (!Auth::user()->checkUserRole([3]))
                            <li>
                                <a class='{{ Request::is('pservers') ? 'active' : '' }}' href="{{ route('pservers.store') }}">项目设置</a>
                            </li>
                        @endif
                    @else
                        <li>
                            <a class='{{ Request::is('projects*') ? 'active' : '' }}' href="{{ route('projects.index') }}">项目列表</a>
                        </li>
                    @endif
                    @if (Auth::user()->checkUserRole([1,2,5,6]))
                        <li>
                            <a href="{{ asset('文档6.0.pdf') }}" target="_blank">回调文档</a>
                        </li>
                    @endif
                </ul>
            </li>
        @endif
        @if (Auth::user()->checkUserRole([1]))
            <li class='{{ (Request::is('subtitleLanguages*') || Request::is('users*') || Request::is('cutServer*') || Request::is('servers*') || Request::is('logs*') || Request::is('configs*')) ? 'active showMenu' : '' }}'>
                <div class="icon-link">
                    <a class='arrow menu-a'>
                        <i class='bx bxs-cog'></i>
                        <span class="link_name">管理员设置</span>
                    </a>
                    <i class='bx bxs-chevron-down arrow'></i>
                </div>
                <ul class="sub-menu">
                    <li>
                        <a class='{{ Request::is('users*') ? 'active' : '' }}' href="{{ route('users.index') }}">用户列表</a>
                    </li>
                    <li>
                        <a class='{{ Request::is('servers*') ? 'active' : '' }}' href="{{ route('servers.index') }}">资源服务器</a>
                    </li>
                    <li>
                        <a class='{{ Request::is('cutServer*') ? 'active' : '' }}' href="{{ route('cutServer.index') }}">切片资源服务器</a>
                    </li>
                    <li>
                        <a class='{{ Request::is('subtitleLanguages*') ? 'active' : '' }}' href="{{ route('subtitleLanguages.index') }}">生成字幕语言</a>
                    </li>
                    <li>
                        <a class='{{ Request::is('logs*') ? 'active' : '' }}' href="{{ route('logs.index') }}">操作日志</a>
                    </li>
                    <li>
                        <a class='{{ Request::is('configs*') ? 'active' : '' }}' href="{{ route('configs.index') }}">设置</a>
                    </li>
                </ul>
            </li>
        @endif
    </ul>
</div>
<script src="{{ asset('js/app.js') }}"></script>
<link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
