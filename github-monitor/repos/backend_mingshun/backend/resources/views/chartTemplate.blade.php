@extends('main')
@section('head')
    <script src="{{ asset('js/chart.js') }}"></script>
    <link href="{{ asset('css/chart.css') }}" rel="stylesheet" />
@endsection
@section('content')
<div class='card'>
    <div class="card-header">
        <h2>报表</h2>
    </div>
    <div class="card-body">
        {!!$chart!!}
    </div>
</div>
@endsection
