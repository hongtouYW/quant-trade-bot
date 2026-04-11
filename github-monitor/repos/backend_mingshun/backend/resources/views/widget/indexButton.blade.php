<div class='button-container'>
    @if ($setting['create'] ?? 1)
        <a href="{{ route($crudRoutePart . '.create') }}">
            <button type="button" class="btn btn-success waves-effect waves-light">添加</button>
        </a>
    @endif
    @if ($setting['import'] ?? 0)
        <a class="import-btn">
            <button type="button" class="btn btn-import">导入</button>
        </a>
    @endif
    @if ($setting['ftpReset'] ?? 0)
        <a class="ftp-reset-btn" href="{{ route('ftp.ftpReset') }}">
            <button type="button" class="btn btn-import">同步FTP账户</button>
        </a>
    @endif
    @if ($setting['export'] ?? 0)
        <a class="export-btn">
            <button type="button" class="btn btn-export">导出</button>
        </a>
    @endif
    @if ($setting['dailyQuest'] ?? 0)
        <a id="dailyQuest" href="{{ route($crudRoutePart . '.dailyQuest') }}">
            <button type="button" class="btn btn-daily-quest quest-btn">每日任务</button>
        </a>
    @endif
    @if ($setting['extraQuest'] ?? 0)
        <a id="extraQuest" href="{{ route($crudRoutePart . '.extraQuest') }}">
            <button type="button" class="btn btn-extra-quest quest-btn">额外任务</button>
        </a>
    @endif
    @if ($setting['grid'] ?? 0)
        <button class='chg-layout btn change-grid-btn'>
            <i class='layout-icon bx bxs-grid-alt'></i>
        </button>
    @endif
    @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
        <button class='multi-select btn change-grid-btn multi'>
            选择
        </button>
        @if ($setting['grid'] ?? 0)
            <button class='multi-select-grid btn change-grid-btn multi'>
                选择
            </button>
        @endif
        <div class='select-button'>
            @foreach(($setting['multiSelect']['button'] ?? []) as $key=>$button)
                <button type="submit" class='multi-{{$key}} btn change-grid-btn'>
                    {!! $button['name'] !!}
                </button>
            @endforeach
            @foreach(($setting['multiSelectModal']['button'] ?? []) as $key=>$button)
                <button type="submit" class='@if($button['class'] ?? '') {{$button['class']}} @else multi-modal-{{$key}} @endif btn change-grid-btn'>
                    {!! $button['name'] !!}
                </button>
            @endforeach
        </div>
        @if ($setting['grid'] ?? 0)
            <div class='select-grid-button'>
                @foreach(($setting['multiSelect']['button'] ?? []) as $key=>$button)
                    <button type="submit" class='multi-grid-{{$key}} btn change-grid-btn'>
                        {!! $button['name'] !!}
                    </button>
                @endforeach
                @foreach(($setting['multiSelectModal']['button'] ?? []) as $key=>$button)
                    <button type="submit" class='@if($button['class'] ?? '') {{$button['class']}} @else multi-modal-grid-{{$key}} @endif btn change-grid-btn'>
                        {!! $button['name'] !!}
                    </button>
                @endforeach
            </div>
        @endif
    @endif
</div>