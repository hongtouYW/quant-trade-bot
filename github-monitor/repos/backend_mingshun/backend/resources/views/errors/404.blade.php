@extends('layouts.app')

@section('content')
<div class="container text-center mt-5">
    <h1>404</h1>
    <p>页面不存在</p>
    <a href="{{ url()->previous() }}" class="btn btn-primary">返回</a>
</div>
@endsection
