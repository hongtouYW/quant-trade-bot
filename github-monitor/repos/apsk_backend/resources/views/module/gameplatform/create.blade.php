@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('gameplatform.add_new_gameplatform'))
@section('header-title', __('gameplatform.add_new_gameplatform'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('gameplatform.add_new_gameplatform') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.gameplatform.store') }}" method="POST" class="p-4" enctype="multipart/form-data">
                    @csrf
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    <div class="mb-3">
                        <label for="gameplatform_name" class="form-label">{{ __('gameplatform.gameplatform_name') }}</label>
                        <input type="text" class="form-control @error('gameplatform_name') is-invalid @enderror" id="gameplatform_name" name="gameplatform_name" value="{{ old('gameplatform_name') }}" required>
                        @error('gameplatform_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="icon" class="form-label">{{ __('gameplatform.icon') }}</label>
                            <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="api" class="form-label">{{ __('gameplatform.api') }}</label>
                        <input type="text" class="form-control @error('api') is-invalid @enderror" id="api" name="api" value="{{ old('api') }}">
                        @error('api')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.gameplatform.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
