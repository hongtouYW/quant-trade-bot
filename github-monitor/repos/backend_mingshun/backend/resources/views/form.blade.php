@extends('main')
@section('head')
    <link href="{{ asset('css/modal.css') }}" rel="stylesheet" />
    <script src="{{ asset('js/editor.js')}}"></script>
    <link href="{{ asset('css/editor.css') }}" rel="stylesheet">
@endsection
@section('content')
    <div class='row'>
        <div class='col-12'>
            {!!$buttons!!}
        </div>
    </div>
    <div class='card'>
        <div class="card-header">
            <h2>@if(($edit ?? 0) == 1) 编辑 @elseif(($edit ?? 0) == 0) 增加 @endif {{ $title }}</h2>
        </div>
        <div class="card-body">
            <form id='baseForm' action="@if(($edit ?? 0) == 1) {{ route($crudRoutePart . '.update', $id) }} @else {{ route($crudRoutePart . '.store') }} @endif" method="POST" enctype="multipart/form-data">
                @csrf
                @if(($edit ?? 0) == 1) @method('PUT') @endif
                @foreach ($columns as $key => $column)
                    @include('widget.inputContainer', [
                        'key' => $key,
                        'name' => $column['name'] ?? '',
                        'type' => $column['type'] ?? '',
                        'setting' => [
                            'containerKey' => $key,
                            'required' => $column['required'] ?? 0,
                            'multiple' => $column['multiple'] ?? 0,
                            'readonly' => $column['readonly'] ?? 0,
                            'value' => ($column['value'] ?? (($column['multiple'] ?? 0)?[]:'')),
                            'spacing' => 1,
                            'mimeType' => $column['mimeType'] ?? '',
                            'placeholder' => $column['placeholder'] ?? '',
                            'create' => $column['create'] ?? 0,
                            'condition' => $column['condition'] ?? [],
                            'fields' => $column['field'] ?? [],
                            'setting' => $column['setting'] ?? [],
                            'modal' => $column['modal'] ?? '',
                            'route' => $column['route'] ?? '',
                            'label' => $column['label'] ?? '',
                            'pre' => $column['pre'] ?? [],
                        ],
                    ])
                @endforeach
                @include('widget.video',['script' => 1])
                @include('widget.imageModal',['script' => 1])
                @if(!($extra ?? ''))
                    <div class='row'>
                        <div class='col-12 submit-button'>
                            <button class="btn btn-sm btn-submit">
                                提交
                            </button>
                        </div>
                    </div>
                @endif
            </form>
            {!! $extra !!}
        </div>
    </div>
    <link href="{{ asset('css/form.css') }}" rel="stylesheet" />
@endsection
