@extends('main')
@section('head')
    <link rel="stylesheet" href="{{ asset('css/jquery.dataTables.css') }}" />
    <script src="{{ asset('js/jquery.dataTables.js') }}"></script>
    @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
        <script src="{{ asset('js/dataTables.select.min.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('css/dataTables.select.min.css') }}" />
    @endif
    <link href="{{ asset('css/index.css') }}" rel="stylesheet" />
    <link href="{{ asset('css/modal.css') }}" rel="stylesheet" />
    <link href="{{ asset('css/indexgrid.css') }}" rel="stylesheet" />
    <script src="{{ asset('js/jquery.cookie.min.js') }}"></script>
@endsection
@section('content')
    <div class='card'>
        <div class="card-header">
            <h2>{{ $title }}列表</h2>
        </div>
        <div class="card-body">
            @if ($setting['multiSelect']['button']?? '')
                <form id='multi-form' action="" method="post" style="display:inline-block">
                    @csrf
                    <input type="hidden" name="multi-id" id="multi-id" value=''>
                    <input type="hidden" name="multi-status" id="multi-status" value=''>
                </form>
            @endif
            {!! $setting['filters'] ?? '' !!}
            @include('widget.indexButton')
            <div class='table-wrapper'>
                <table class="table table-responsive datatable-table datatable"
                    style='width:100%; padding: 10px 0; border-bottom: 0;'>
                    <thead>
                        <tr>
                            @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                                <th scope="col"></th>
                            @endif
                            @foreach ($columns as $column)
                                <th scope="col">
                                    {{ $column['name'] }}</th>
                            @endforeach
                            @if ($setting['actions'] ?? 1)
                                <th scope="col">行动</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if ($setting['video'] ?? 0)
        {!! $setting['video'] ?? '' !!}
    @endif
    @if ($setting['import'] ?? 0)
        {!! $setting['import'] ?? '' !!}
    @endif
    @if ($setting['rejectStatus'] ?? 0)
        {!! $setting['rejectStatus'] ?? '' !!}
    @endif
    @if ($setting['imageModal'] ?? 0)
        {!! $setting['imageModal'] ?? '' !!}
    @endif
    @if ($setting['cutStatus'] ?? 0)
        {!! $setting['cutStatus'] ?? '' !!}
    @endif
@endsection

@section('scripts')
    <script>
        @if ($setting['grid'] ?? 0)
            var grid = 0;
            var default_grid = 0;

            function openDropDown(dropdownId) {
                document.getElementById(dropdownId).classList.toggle("grid-menu-show");
            }

            function chageLayout(table) {
                if (grid) {
                    grid = 0;
                    @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                        $('.multi-select').show();
                        $('.multi-select-grid').hide();
                    @endif
                } else {
                    grid = 1;
                    @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                        $('.multi-select').hide();
                        $('.multi-select-grid').show();
                    @endif
                }
                @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                    $('.select-button').hide();
                    $('.select-grid-button').hide();
                    $('.multi-select').text('选择');
                    $('.multi-select-grid').text('选择');
                    table.column(0).visible(false);
                @endif
                $('.layout-icon').toggleClass('bxs-grid-alt bx-list-ul');
                $('.datatable-table').toggle();
                $('#grid-row').toggle();

                table.ajax.reload(null, false);
            }

            function processingGrid(item){
                return `
                    <div class="feed_item">
                        <div class="feed_item_video">
                        @if(($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                            <a class='video-grid-video' data="` + item.path + `">
                                <i class='grid-select-checkbox-container bx bx-check'></i>
                                <span class='no-grid-select-checkbox-container'>
                                    <input class="grid-select-checkbox" type="checkbox" name="select_id[]" value="`+ item.id + `">
                                </span>
                                <input class="grid-select-status-checkbox" type="checkbox" name="select_status[]" value="`+ item.status + `">
                        @else
                            <a class='video-grid' data="` + item.path + `" data-id='`+ item.id + `'>
                        @endif
                                <img class="cover-picture-img" src="` + item.cover_photo + `">
                                <span class="status">` + item.status + `</span>
                            </a>
                        </div>
                        <div class="feed_item_info">
                            <div>
                                <div class="menu-container">
                                    <span onclick="openDropDown('dropdown` + item.id + `')" class="grid-menu"><svg enable-background="new 0 0 24 24" height="24" viewBox="0 0 24 24" width="24" focusable="false" style="pointer-events: none; display: block; width: 100%; height: 100%;"><path d="M12 16.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5.67-1.5 1.5-1.5zM10.5 12c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5-.67-1.5-1.5-1.5-1.5.67-1.5 1.5zm0-6c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5-.67-1.5-1.5-1.5-1.5.67-1.5 1.5z"></path></svg></span>
                                    ` + item.actions_grid + `
                                </div>
                                <a class='video-grid video-grid-title-info' data="` + item.path + `">
                                    <div class="feed_item_title">
                                        <span class="tooltip-text">` + item.title + `</span>
                                            <h3>` + item.title + `</h3>
                                    </div>
                                    <p class="feed_item_details">` + item.uid + ` (` + item.id + `)` +
                        `<br>` + item.author + ` • ` + item.author_time + `</p>
                                    <div class='tag'>` + item.tag + item.type + `</div>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            }
        @endif
        @if ($setting['video'] ?? 0)
            var player;

            function openVideo(data){
                $('#video-modal').show();
                $("body").addClass("modal-open");
                $('#hls-video-source').attr('src', data);
                $('#hls').attr('src', data);
                player = videojs("hls");
                player.play();
            }
            
            function closeVideo() {
                $("body").removeClass("modal-open");
                $('#video-modal').hide();
                player.pause();
                player.dispose();
                $('.video-modal-body').append(`<video id="hls" class="video-js vjs-default-skin" width="875" height="500" controls>
                            <source id='hls-video-source' type="video/mp4" src="">
                        </video>`);
            }
        @endif

        @if ($setting['rejectStatus'] ?? 0)
            function closeStatusModal() {
                $("body").removeClass("modal-open");
                $('#status-modal').hide();
            }
        @endif

        @if ($setting['imageModal'] ?? 0)
            function closeImageModal() {
                $("body").removeClass("modal-open");
                $('#image-modal').hide();
            }
        @endif

        @if ($setting['cutStatus'] ?? 0)
            function closeCutModal() {
                $("body").removeClass("modal-open");
                $('#cut-modal').hide();
            }
        @endif

        @if ($setting['multiSelect']['button']?? '')
            function sendSelectRequest(id, status, statusName, statusFormValue, route){
                var statusNameArray = $.trim(statusName).split(",");
                if(id.length === 0){
                    setTimeout(() => {
                        const danger = addNotification(
                            NOTIFICATION_TYPES.DANGER,
                            '请选择最少一排');
                        setTimeout(() => {
                            removeNotification(danger);
                        }, 5000);
                    }, 300);
                }else{
                    var canChange = true;
                    $.each(status, function(index, element) {
                        if ($.inArray(element, statusNameArray) === -1) {
                            canChange = false;
                            return false; 
                        }
                    });
                    if (canChange) {
                        $('#multi-id').val(JSON.stringify(id));
                        $('#multi-status').val(statusFormValue);
                        $('#multi-form').attr('action', route); 
                        $('#multi-form').submit();
                    } else {
                        setTimeout(() => {
                            const danger = addNotification(
                                NOTIFICATION_TYPES.DANGER,
                                '选择的状态必须是' + statusName);
                            setTimeout(() => {
                                removeNotification(danger);
                            }, 5000);
                        }, 300);
                    }
                }
            }
        @endif

        @if ($setting['multiSelectModal']['button']?? '')
            function processMultiSelectModal(id, status, statusName, statusFormValue, modal, modalID, modalStatus){
                var statusNameArray = $.trim(statusName).split(",");
                if(id.length === 0){
                    setTimeout(() => {
                        const danger = addNotification(
                            NOTIFICATION_TYPES.DANGER,
                            '请选择最少一排');
                        setTimeout(() => {
                            removeNotification(danger);
                        }, 5000);
                    }, 300);
                }else{
                    var canChange = true;
                    $.each(status, function(index, element) {
                        if ($.inArray(element, statusNameArray) === -1) {
                            canChange = false;
                            return false; 
                        }
                    });
                    if (canChange) {
                        $('#' + modalID).val(JSON.stringify(id));
                        $('#' + modalStatus).val(statusFormValue);
                        $('#' + modal).show();
                    } else {
                        setTimeout(() => {
                            const danger = addNotification(
                                NOTIFICATION_TYPES.DANGER,
                                '选择的状态必须是' + statusName);
                            setTimeout(() => {
                                removeNotification(danger);
                            }, 5000);
                        }, 300);
                    }
                }
            }
        @endif

        function sendAjax(event,b){
            event.preventDefault(); 
            var confirmWord = $(b).attr('confirmWord'); 
            if(confirmWord){
                var confirmation = confirm(confirmWord);
            }else{
                var confirmation = true;
            }
            if (confirmation) {
                var formData = $(b).serialize();
                var url = $(b).attr('action'); 
                var id = $(b).attr('data'); 
                var table =  $('.datatable-table').DataTable();
                $('.modal').hide();
                $("body").removeClass("modal-open");
                $.ajax({
                    url: url, 
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        @if ($setting['grid'] ?? 0)
                            if(grid){
                                $('#grid-div-container-' + id).empty();
                                if(response.data[0]){
                                    $('#grid-div-container-' + id).append(processingGrid(response.data[0]));
                                }else{
                                    $('#grid-div-container-' + id).remove();
                                }
                            }
                        @endif
                        table.draw(false);
                        const success = addNotification(NOTIFICATION_TYPES.SUCCESS, '状态编辑成功');
                        setTimeout(() => {
                            removeNotification(success);
                        }, 5000);
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = xhr['responseJSON']['message'];
                        const danger = addNotification(
                            NOTIFICATION_TYPES.DANGER,
                            errorMessage);
                        setTimeout(() => {
                            removeNotification(danger);
                        }, 5000);
                    }
                });
            }
        }

        window.onclick = function(event) {
            @if ($setting['grid'] ?? 0)
                if (!event.target.matches('.grid-menu')) {
                    var dropdowns = document.getElementsByClassName("dropdown-content");
                    var i;

                    for (i = 0; i < dropdowns.length; i++) {
                        var openDropdown = dropdowns[i];

                        if (openDropdown.classList.contains('grid-menu-show')) {
                            openDropdown.classList.remove('grid-menu-show');
                        }
                    }
                }
            @endif
            @if ($setting['video'] ?? 0)
                var videoModal = document.getElementById('video-modal');
                if (event.target == videoModal) {
                    closeVideo();
                }
            @endif
            @if ($setting['rejectStatus'] ?? 0)
                var statusModal = document.getElementById('status-modal');
                if (event.target == statusModal) {
                    closeStatusModal();
                }
            @endif
            @if ($setting['imageModal'] ?? 0)
                var statusModal = document.getElementById('image-modal');
                if (event.target == statusModal) {
                    closeImageModal();
                }
            @endif
            @if ($setting['cutStatus'] ?? 0)
                var statusModal = document.getElementById('cut-modal');
                if (event.target == statusModal) {
                    closeCutModal();
                }
            @endif
        }
        $(function() {
            var table;
            let dtOverrideGlobals = {
                @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                    select: {
                        style: 'multi+shift',
                        selector: '.select-checkbox'
                    },
                @endif
                processing: true,
                serverSide: true,
                stateSave: true,
                retrieve: true,
                aaSorting: [],
                rowId: 'id',
                ajax: {
                    url: "{{ route($crudRoutePart . '.index') }}",
                    data: function(d) {
                        jQuery.each($(".datatable-search-field").serializeArray(), function(i, field) {
                            d[field.name] = field.value;
                        });
                    },
                    dataSrc: function(json) {
                        @if ($setting['grid'] ?? 0)
                            if (grid == 1) {
                                var parentElement = document.getElementById('grid-row');
                                if (!parentElement) {
                                    $('.datatable-table').after('<div class="row" id="grid-row"></div>');
                                }
                                parentElement = document.getElementById('grid-row');
                                while (parentElement.firstChild) {
                                    parentElement.removeChild(parentElement.firstChild);
                                }
                                json.data.forEach(function(item) {
                                    var newElement = document.createElement('div');
                                    newElement.className = 'col-lg-3';
                                    newElement.setAttribute('id', 'grid-div-container-' + item.id);
                                    newElement.innerHTML = processingGrid(item);
                                    parentElement.appendChild(newElement);
                                });
                                return '';
                            }
                        @endif
                        return json.data;
                    },
                },
                columns: [
                    @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                    {
                        orderable: false,
                        searchable: false,
                        className: 'select-checkbox',
                        data: null,
                        title: '<input type="checkbox" id="select_all">',
                        defaultContent: '',
                        width: '20px'
                    },
                    @endif
                    @foreach ($columns as $key => $column)
                        {
                            data: '{{ $key }}',
                            name: '{{ $key }}',
                            @if (!($column['sort'] ?? 1))
                                orderable: false,
                                searchable: false,
                            @endif
                            @if (!($column['visible'] ?? 1))
                                visible: false,
                            @endif
                            @if ($column['width'] ?? '')
                                width: "{{ $column['width'] }}",
                            @endif
                            @if ($column['className'] ?? '')
                                className: "{{ $column['className'] }}",
                            @endif
                        },
                    @endforeach
                    @if ($setting['actions'] ?? 1)
                        {
                            data: 'actions',
                            name: '{{ trans('global.actions') }}',
                            orderable: false,
                            searchable: false,
                            width: "215px",
                        }
                    @endif
                ],
                orderCellsTop: true,
                order: [
                    @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                        [1, 'desc']
                    @else
                        [0, 'desc']
                    @endif
                ],
                pageLength: {{ $setting['pageLength'] ?? 10 }},
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.10.19/i18n/Chinese.json"
                },
                drawCallback: function() {
                    $('.changeStatusForm').submit(function(event) {
                        sendAjax(event,this);
                    });
                    @if ($setting['video'] ?? 0)
                        $(".open-hls-video, .video-grid").click(function() {
                            openVideo($(this).attr('data'));
                        });
                        $('#close-hls-video').click(function() {
                            closeVideo();
                        });
                        @if ($setting['grid'] ?? 0)
                            @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                                $(".video-grid-video").click(function() {
                                    if($.trim($('.multi-select-grid').first().text()) == '选择'){
                                        openVideo($(this).attr('data'));
                                    }else{
                                        var checkbox = $(this).find('input[type="checkbox"]');
                                        $(this).find('input[type="checkbox"]').prop("checked",!checkbox.prop("checked"));
                                        $(this).find('.cover-picture-img').toggleClass('selected-image');
                                        $(this).find('.grid-select-checkbox-container').toggle();
                                        $(this).find('.no-grid-select-checkbox-container').toggle();
                                    }
                                });
                            @endif
                        @endif
                    @endif
                    @if ($setting['rejectStatus'] ?? 0)
                        var rejectStatusBtnClass = "{{ $setting['rejectStatusBtn'] }}".split(",");
                        var temp = '';
                        rejectStatusBtnClass.forEach(element => {
                            $("." + element).click(function() {
                                $('#reason-label').text($(this).attr('data-label'));
                                $('#chg-status-modal-status').val($(this).attr('data-value'));
                                $('#chg-status-modal-id').val($(this).attr('data'));
                                $('#chg-status-modal-form').attr('data',$(this).attr('data'));
                                $('#status-modal').show();
                                $("body").addClass("modal-open");
                            });
                        });
                       
                        $('#close-status-modal').click(function() {
                            closeStatusModal();
                        });
                    @endif
                    @if ($setting['imageModal'] ?? 0)
                        $('#close-image-modal').click(function() {
                            closeImageModal();
                        });

                        $('.clickable-img').click(function() {
                            $('#image-modal').show();
                            $("#modal-image-inside").attr('src',$(this).attr('src'));
                            $("body").addClass("modal-open");
                        });

                    @endif
                    @if ($setting['cutStatus'] ?? 0)
                        const originalRuleOptions = $("#rule").html();
                        const uniqueRuleExtraValues = [];

                        const originalThemeOptions = $("#theme").html();
                        const uniqueThemeExtraValues = [];

                        $("#rule option").each(function() {
                            const extra = $(this).attr("extra");
                            if (extra !== undefined && !uniqueRuleExtraValues.includes(extra)) {
                                uniqueRuleExtraValues.push(extra);
                            }
                        });

                        $("#theme option").each(function() {
                            const extra = $(this).attr("extra");
                            if (extra !== undefined && !uniqueThemeExtraValues.includes(extra)) {
                                uniqueThemeExtraValues.push(extra);
                            }
                        });

                        $(".{{ $setting['cutStatusBtn'] }}").click(function() {
                            $('#chg-cut-modal-id').val($(this).attr('data'));
                            $('#cut-modal').show();
                            $("body").addClass("modal-open");
                            @if(Auth::user()->checkUserRole([1]))
                                var project_id = $(this).attr('extra');
                                $("#rule").html(originalRuleOptions); 
                                uniqueRuleExtraValues.forEach(function(value) {
                                    if(value != project_id && value!=0){
                                        $("#rule option[extra='"+value+"']").remove();
                                    }
                                });
                                $("#rule").trigger('change.select2');

                                $("#theme").html(originalThemeOptions); 
                                uniqueThemeExtraValues.forEach(function(value) {
                                    if(value != project_id && value!=0){
                                        $("#theme option[extra='"+value+"']").remove();
                                    }
                                });
                                $("#theme").trigger('change.select2');
                            @endif
                        });

                        @if(Auth::user()->checkUserRole([1]))
                            $('.multi-modal-cut').click(function() {
                                var tempProjectIdArray = $.map(table.rows( { selected: true } ).data(), function(item) {
                                    return item.project_id;
                                });
                                var uniqueProjectIdsSet = new Set(tempProjectIdArray);
                                var projectIdArray = Array.from(uniqueProjectIdsSet);
                                if(projectIdArray.length > 1){
                                    const danger = addNotification(NOTIFICATION_TYPES.DANGER, '不能多选不同的项目');
                                    setTimeout(() => {
                                        removeNotification(danger);
                                    }, 5000);
                                    setTimeout(() => {
                                        closeCutModal();
                                    }, 100);
                                }else if(projectIdArray.length == 1){
                                    var project_id = projectIdArray[0];
                                    $("#rule").html(originalRuleOptions); 
                                    uniqueRuleExtraValues.forEach(function(value) {
                                        if(value != project_id && value!=0){
                                            $("#rule option[extra='"+value+"']").remove();
                                        }
                                    });
                                    $("#rule").trigger('change.select2');

                                    $("#theme").html(originalThemeOptions); 
                                    uniqueThemeExtraValues.forEach(function(value) {
                                        if(value != project_id && value!=0){
                                            $("#theme option[extra='"+value+"']").remove();
                                        }
                                    });
                                    $("#theme").trigger('change.select2');
                                }
                            });
                        @endif

                        $('#close-cut-modal').click(function() {
                            closeCutModal();
                        });

                        $(".no-server").click(function() {
                            const danger = addNotification(
                                NOTIFICATION_TYPES.DANGER,
                                '用户没设置项目服务器，无法切片或同步');
                            setTimeout(() => {
                                removeNotification(danger);
                            }, 5000);
                        });
                    @endif
                    $('.select2-init').select2();
                    @if ($setting['jump_page'] ?? 1)
                        $('.paging_simple_numbers').prepend('到 <input id="pagination-input" type="number" min=0 step=1> 页');
                        $('#pagination-input').change(function() {
                            var newValue = $(this).val();
                            table.page(parseInt(newValue) - 1).draw( false );
                        });
                    @endif
                    $('.status-form').on('change', function() {
                        var formId = $(this).data('form-id');
                        $('#' + formId).submit();
                    });
                    $('.filter-click').on('click', function() {
                        var $newOption = $("<option selected='selected'></option>").val($(this).attr('filter')).text($(this).text())
                        $("#search_" + $(this).attr('key')).append($newOption).trigger('change');
                        $().val($(this).attr('filter')).trigger('change');
                    });
                    setTimeout(function () {
                        $('#loader').hide();
                    }, 500);
                },
                stateSaveParams: function(settings, data) {
                    @if ($setting['grid'] ?? 0)
                        data.grid = grid;
                    @endif
                    data.filter = $(".datatable-search-field").serializeArray();
                },
                stateLoadParams: function(settings, data) {
                    @if ($setting['grid'] ?? 0)
                        default_grid = data.grid;
                    @endif
                    jQuery.each(data.filter, function(i, field) {
                        $('.datatable-search-field[name="'+field.name+'"]').val(field.value);
                    });
                },
                lengthMenu: {{ $setting['lengthMenu'] ?? '[ 10, 25, 50, 75, 100 ]' }}
            };
            table = $('.datatable-table').DataTable(dtOverrideGlobals);
            table.on( 'preDraw', function () {
                if(table.settings()[0]){
                    if (table.settings()[0].jqXHR) {
                        table.settings()[0].jqXHR.abort();
                    }
                }
            });
            $('a[data-toggle="tab"]').on('shown.bs.tab click', function(e) {
                $($.fn.dataTable.tables(true)).DataTable()
                    .columns.adjust();
            });
            table.on("init", function() {
                $('.changeStatusFormOnce').submit(function(event) {
                    sendAjax(event,this);
                });
                @if ($setting['grid'] ?? 0)
                    $(".chg-layout").click(function() {
                        chageLayout(table);
                    });
                    if (default_grid) {
                        chageLayout(table);
                    }else{
                        @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                            $('.multi-select').show();
                            $('.multi-select-grid').hide();
                        @endif
                    }
                @elseif (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                    $('.multi-select').show();
                @endif
                @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                    setTimeout(() => {
                        table.rows( { selected: true } ).deselect();
                    }, 100);    
                @endif
                var flag = true;
                $('.datatable-search-field').on('change', function() {
                    if (flag) {
                        $('#loader').show();
                        table.ajax.reload();
                    }
                });
                $('.search-reset-btn').on('click', function() {
                    flag = false;
                    $('.datatable-search-field').val('').trigger('change');
                    table.search('').draw();
                    flag = true;
                    table.ajax.reload();
                });
                @if (($setting['multiSelect']['button']?? '') || ($setting['multiSelectModal']['button']?? ''))
                    if (table.column(0).visible()) {
                        table.column(0).visible(false);
                        table.column(-1).visible(true);
                    }

                    var selectFlag = true;
                    $('.multi-select').on('click', function(e) {
                        e.preventDefault();
                        if($.trim($('.multi-select').first().text()) == '选择'){
                            table.column(-1).visible(false);
                            table.column(0).visible(true);
                            $('.multi-select').text('不选择');
                            $('.select-button').css('display', 'inline-block');
                        } else {
                            table.rows( { selected: true } ).deselect();
                            table.column(-1).visible(true);
                            table.column(0).visible(false);
                            $('.multi-select').text('选择');
                            $('.select-button').css('display', 'none');
                        }
                        $("#select_all").prop("checked", false);
                        if(selectFlag){
                            $('#select_all').on('change', function(e) {
                                if($(this).is(":checked")){
                                    table.rows().select(); 
                                }else{
                                    table.rows().deselect(); 
                                }
                            });
                        }
                       
                    });
                    @if ($setting['grid'] ?? 0)
                        $('.multi-select-grid').on('click', function(e) {
                            if($.trim($('.multi-select-grid').first().text()) == '选择'){
                                $('.no-grid-select-checkbox-container').show();
                                $('.multi-select-grid').text('不选择');
                                $('.select-grid-button').css('display', 'inline-block');
                            }else{
                                $('.no-grid-select-checkbox-container').hide();
                                $('.multi-select-grid').text('选择');
                                $('.select-grid-button').css('display', 'none');
                                $('.grid-select-checkbox-container').hide();
                                $('.grid-select-checkbox').prop('checked',false);
                                $('.cover-picture-img').removeClass('selected-image');
                            }
                        });
                    @endif
                    @foreach(($setting['multiSelect']['button'] ?? []) as $key=>$button)
                        $('.multi-{{$key}}').on('click',function(event) {
                            @if($button['confirm'] ?? 1)
                                var confirmed = confirm("确定{{ strip_tags(($button['name'] ?? "")) }}吗？");
                                if (confirmed) {
                            @endif
                                    event.preventDefault();
                                    var rowData = table.rows('.selected').data();
                                    var status = rowData.pluck('status');
                                    var id = [];
                                    var selectedIds = rowData.map(function(row) {
                                        id.push(row.id);
                                        return row.id; 
                                    });
                                    var statusName = "{{ $button['statusNow']??''}}"
                                    sendSelectRequest(id, status, statusName,{{$button['status'] ?? 0}},'{{$button['route'] ?? ''}}');
                            @if($button['confirm'] ?? 1)
                                }
                            @endif
                        });
                    @endforeach
                    @foreach(($setting['multiSelectModal']['button'] ?? []) as $key=>$button)
                        $('.multi-modal-{{$key}}').on('click',function(event) {
                            var rowData = table.rows('.selected').data();
                            var status = rowData.pluck('status');
                            var id = [];
                            var selectedIds = rowData.map(function(row) {
                                id.push(row.id);
                                return row.id; 
                            });
                            var statusName = "{{ $button['statusNow']??''}}"
                            processMultiSelectModal(id, status, statusName,{{$button['status'] ?? 0}},'{{$button['modal'] ?? ''}}','{{$button['modalID'] ?? ''}}','{{$button['modalStatus'] ?? ''}}');
                        });
                    @endforeach
                @endif
                @if ($setting['grid'] ?? 0)
                    @foreach(($setting['multiSelect']['button'] ?? []) as $key=>$button)
                        $('.multi-grid-{{$key}}').on('click',function(event) {
                            @if($button['confirm'] ?? 1)
                                var confirmed = confirm("确定{{ strip_tags(($button['name'] ?? "")) }}吗？");
                                if (confirmed) {
                            @endif
                                event.preventDefault();
                                var statusName = "{{ $button['statusNow']??''}}"
                                var statusNameArray = $.trim(statusName).split(",");
                                var checkboxValuesID = $('.grid-select-checkbox:checked').map(function() {
                                    return $(this).val();
                                }).get();
                                var checkboxValuesStatus = $('.grid-select-status-checkbox:checked').map(function() {
                                    return $(this).val();
                                }).get();
                                sendSelectRequest(checkboxValuesID, checkboxValuesStatus, statusName,{{$button['status'] ?? 0}},'{{$button['route'] ?? ''}}');
                            @if($button['confirm'] ?? 1)
                                }
                            @endif
                        });
                    @endforeach
                    @foreach(($setting['multiSelectModal']['button'] ?? []) as $key=>$button)
                        $('.multi-modal-grid-{{$key}}').on('click',function(event) {
                                var statusName = "{{ $button['statusNow']??''}}"
                                var statusNameArray = $.trim(statusName).split(",");
                                var checkboxValuesID = $('.grid-select-checkbox:checked').map(function() {
                                    return $(this).val();
                                }).get();
                                var checkboxValuesStatus = $('.grid-select-status-checkbox:checked').map(function() {
                                    return $(this).val();
                                }).get();
                                processMultiSelectModal(checkboxValuesID, checkboxValuesStatus, statusName,{{$button['status'] ?? 0}},'{{$button['modal'] ?? ''}}','{{$button['modalID'] ?? ''}}','{{$button['modalStatus'] ?? ''}}');
                        });
                    @endforeach
                @endif
            });
            @if ($setting['export'] ?? 0)
                $(".btn-export").click(function() {
                    $.ajax({
                    url: '{{ route($crudRoutePart . '.export') }}',
                    method: 'GET',
                    success: function(data) {
                        var blob = new Blob([data], { type: 'text/csv' });
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = '{{ $title }}.csv';
                        link.click();
                    }
                    });
                });
            @endif
            @if ($setting['dailyQuest'] ?? 0)
                $('#dailyQuest').click(function() {
                    $('#loader').show();
                });
            @endif
            @if ($setting['extraQuest'] ?? 0)
                $('#extraQuest').click(function() {
                    $('#loader').show();
                });
            @endif
        });
    </script>
@endsection
