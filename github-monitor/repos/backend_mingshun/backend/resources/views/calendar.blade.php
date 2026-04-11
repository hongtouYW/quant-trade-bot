@extends('main')
@section('head')
    <link href="{{ asset('css/modal.css') }}" rel="stylesheet" />
    <link href="{{ asset('css/calendar.css') }}" rel="stylesheet">
    <script src="{{ asset('js/calendar.js')}}"></script>
@endsection
@section('content')
    <div class='card'>
        <div class="card-header">
            <h2>日历</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="event" style='margin-bottom:20px;'>
                    <span class="badge bg-success">完成每日任务</span>
                    <span class="badge bg-danger">完成额外任务</span>
                    <span class="badge bg-secondary">未完成任务</span>
                    <span class="badge bg-light">未领取任务</span>
                </div>
                {!! $filter ?? '' !!}
                <div class="calendar">

                </div>
            </div>
            <div class="row">
                @foreach($tables as $table)
                    <div class="col-6">
                        <table class='chart-table'>
                            <caption style="text-align:center;">{{$table['title']}}</caption>
                            <tr>
                            @for ($i =0 ; $i < $table['rowTotal']; $i++)
                                @foreach($table['tableTitle'] ?? [] as $key=>$value)
                                <th>{{$value}}</th>
                                @endforeach
                            @endfor
                            </tr>
                            <?php $i=0 ?>
                            @foreach($table['total'] ?? [] as $key=>$value)
                            <?php $i+=1;?>
                            @if(($i % $table['rowTotal'])==1)
                                <tr>
                            @endif
                                <td>{!!$key!!}</td>
                                @foreach($value ?? [] as $key2=>$value2)
                                    <td>{!!$value2!!}</td>
                                @endforeach
                            @if(($i % $table['rowTotal'])==0)
                                </tr>
                            @endif
                            @endforeach
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @include('widget.reviewerModal')
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {
            var year;
            var month;
            var currentURL = window.location.href;
            var url = new URL(currentURL);
            var searchParams = new URLSearchParams(url.search);
            var calendar = $(".calendar").calendarGC({
                dayNames: ["星期日", "星期一", "星期二", "星期三", "星期四", "星期五", "星期六"],
                monthNames: ["一月", "二月", "三月", "四月", "五月", "六月", "七月", "八月", "九月", "十月", "十一月", "十二月"],
                dayBegin: 0,
                prevIcon: '&#x3c;',
                nextIcon: '&#x3e;',
                onclickDate:function (ev, data, cell){
                    if($(cell).hasClass(('clickable-day'))){
                        var dateFull = data.datejs.getFullYear() + "-" + (data.datejs.getMonth() + 1) + "-" + data.datejs.getDate();
                        var data = {
                            _token: '{{csrf_token()}}',
                            time: dateFull,
                        };
                        searchParams.forEach(function(value, key) {
                            data[key] = value;
                        });
                        $.ajax({
                            type: "POST",
                            url: "{{$routeClick}}",
                            data: data,
                            success: function(response) {
                                $('.reviewer-container').find('.col-9').empty();
                                $('.uploader-container').find('.col-9').empty();
                                $('.coverer-container').find('.col-9').empty();
                                $('.date-container').find('.col-9').empty();
                                response.reviewer.forEach(function(item) {
                                    $('.reviewer-container').find('.col-9').append(item);
                                });
                                response.uploader.forEach(function(item) {
                                    $('.uploader-container').find('.col-9').append(item);
                                });
                                response.coverer.forEach(function(item) {
                                    $('.coverer-container').find('.col-9').append(item);
                                });
                                $('.date-container').find('.col-9').append(dateFull);
                                $('#reviewer-modal').show();
                                $("body").addClass("modal-open");
                            },
                            error: function(xhr, status, error) {
                                console.error("Error: " + error);
                            }
                        });
                    }
                },
                onMonthPrevNext: function (e) {
                    calendarFunction(e.getFullYear(), e.getMonth() + 1);
                },
                events: [
                    @foreach($events as $event)
                        {
                            date: new Date('{{$event['date']}}'),
                            eventName: '{{$event['eventName']}}',
                            className: '{{$event['className']}}',
                            dayClassName: '{{$event['dayClassName']}}',
                            dateColor: '{{$event['dateColor']}}',
                            onclick:function (ev, data){},
                        },
                    @endforeach
                ],
            });

            function calendarFunction(year, month){
                var data = {
                    _token: '{{csrf_token()}}',
                    month: month,
                    year: year
                };
                searchParams.forEach(function(value, key) {
                    data[key] = value;
                });
                $.ajax({
                    type: "POST",
                    url: "{{$route}}",
                    data: data,
                    success: function(response) {
                        response = JSON.parse(response);
                        data = JSON.parse(response.data);
                        var temp = [];
                        data.forEach(function(item) {
                            temp.push({
                                date: new Date(item.date),
                                eventName: item.eventName,
                                className: item.className,
                                dayClassName: item.dayClassName,
                                dateColor: item.dateColor,
                                onclick:function (ev, data){ },
                            });
                        });
                        calendar.setEventsNoRender(temp);
                    },
                    error: function(xhr, status, error) {
                        console.error("Error: " + error);
                    }
                });
            }
            {!! $script ?? '' !!}
        });
    </script>
@endsection