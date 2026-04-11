@extends('main')
@section('head')
    <link href="{{ asset('css/chart.css') }}" rel="stylesheet" />
@endsection
@section('content')
    <div class='card'>
        <div class="card-header">
            <h2>你好, {{Auth::user()->username}}!</h2>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach($values as $value)
                    @if(($value['type']??'') == 'table')
                        <div class="col-{{$value['columnNo'] ?? 6}}">
                            <table style='caption-side: top;'>
                                <caption style="text-align:center;">{{$value['title']}}</caption>
                                @foreach($value['header'] as $header)
                                    <th>
                                        {{$header}}
                                    </th>
                                @endforeach
                                @foreach($value['value'] as $key=>$valueTable)
                                    <tr>
                                        <td>{!! $key !!}</td>
                                        <td>{!! $valueTable !!}</td>
                                    </tr>
                                @endforeach
                            </table> 
                        </div>
                    @else
                        @php
                            $titleColumnNo = 12 - ($value['columnNo'] ?? 8);
                        @endphp
                        <div class="row row-text-container">
                            <div class="col-{{$titleColumnNo}}">
                                {{$value['title']}}
                            </div>
                            <div class="col-{{$value['columnNo'] ?? 8}}">
                                {{$value['value']}}
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endsection