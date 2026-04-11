@extends('main')
@section('head')
    <link href="{{ asset('css/modal.css') }}" rel="stylesheet" />
    <link href="{{ asset('css/editor.css') }}" rel="stylesheet">
@endsection
@section('content')
    <div class='row'>
        <div class='col-12'>
            {!! $backButton !!}
        </div>
    </div>
    <div class='card'>
        <div class="card-header">
            <h2>{{ $title }}详情</h2>
        </div>
        <div class="card-body">
            @foreach ($columns as $key => $column)
                <div class='row create-container'>
                    @if($column['type'] == 'hr')
                        <div class='col-12'>
                            <hr>
                            <br>
                            <h4>{{ $column['value'] }}</h4>
                        </div>
                    @else
                        <div class='col-3'>
                            <span>{{ $column['name'] }} :</span>
                        </div>
                        <div class='col-9'>
                            @if ($column['value'] != '')
                                @switch($column['type'])
                                    @case('text')
                                        <span>{{ $column['value'] }}</span>
                                    @break

                                    @case('html')
                                        <div class='fr-view' style="width:100%">{!! $column['value'] !!}</div>
                                    @break

                                    @case('json')
                                        <table class="view-table" style="width:100%">
                                            @foreach ($column['value'] as $key => $value)
                                                <tr>
                                                    <th width='100px'>{{ $key }} :</th>
                                                    <td>{{ $value }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    @break

                                    @case('table')
                                        <table class="view-table" style="width:100%">
                                            @foreach ($column['value'] as $key => $value)
                                                <tr>
                                                    @foreach ($value as $key2 => $value2)
                                                        @if($key == 0)
                                                            <th>
                                                        @else
                                                            <td>
                                                        @endif
                                                            {{$value2}}
                                                        @if($key == 0)
                                                            </th>
                                                        @else
                                                            </td>
                                                        @endif
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </table>
                                    @break

                                    @case('file')
                                        <a href="{{$column['value']}}">{{$column['name']}}</a>
                                    @break

                                    @case('image')
                                        @if (gettype($column['value']) == 'array')
                                            <div class='upload-img-container'>
                                                @foreach ($column['value'] as $value)
                                                    <img class="upload-img table-img clickable-img" src="{{ $value }}" class="">
                                                @endforeach
                                            </div>
                                        @else
                                            <div class='upload-img-container'>
                                                <img class="upload-img table-img clickable-img" src="{{ $column['value'] }}">
                                            </div>
                                        @endif
                                    @break

                                    @case('video')
                                        @if (gettype($column['value']) == 'array')
                                            <table class="view-table" style="width:100%">
                                                <tr>
                                                    <th>#</th>
                                                    <th>视频</th>
                                                </tr>
                                                @foreach ($column['value'] as $key => $value)
                                                    <tr>
                                                        <td>{{$key + 1}}</td>
                                                        <td>
                                                            <a class="open-hls-video" data='{{ $value }}'>
                                                                观看视频
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        @else
                                            <a class="open-hls-video" data='{{ $column['value'] }}'>
                                                观看视频
                                            </a>
                                        @endif
                                    
                                    @break

                                    @case('multiple')
                                        @foreach ($column['value'] as $value)
                                            <span class="label">{{ $value }}</span>
                                        @endforeach
                                    @break
                                @endswitch
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
            <div class='row'>
                <div class='col-12 submit-button'>
                    {!! $button !!}
                </div>
            </div>
        </div>
    </div>
    @include('widget.video', ['script' => 1])
    @include('widget.imageModal',['script' => 1])
    <link href="{{ asset('css/view.css') }}" rel="stylesheet">
@endsection
