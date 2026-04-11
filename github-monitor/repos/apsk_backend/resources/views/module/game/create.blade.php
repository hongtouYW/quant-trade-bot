@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('game.add_new_game'))
@section('header-title', __('game.add_new_game'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('game.add_new_game') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.game.store') }}" method="POST" class="p-4">
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
                        <label for="game_name" class="form-label">{{ __('game.game_name') }}</label>
                        <input type="text" class="form-control @error('game_name') is-invalid @enderror" id="game_name" name="game_name" value="{{ old('game_name') }}" required>
                        @error('game_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="game_desc" class="form-label">{{ __('game.game_desc') }}</label>
                        <textarea class="form-control @error('game_desc') is-invalid @enderror" id="game_desc" name="game_desc" maxlength="1000" style="height: 100px;">{{ old('game_desc') }}</textarea>
                        @error('game_desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="gameplatform_id" class="form-label">{{ __('game.platformselect') }}</label>
                        <select class="form-control @error('gameplatform_id') is-invalid @enderror" id="gameplatform_id" name="gameplatform_id">
                            <option value="">{{ __('game.platformselect') }}</option>
                            @foreach ($gameplatforms as $gameplatform)
                                <option value="{{ $gameplatform->gameplatform_id }}" {{ old('gameplatform_id') == $gameplatform->gameplatform_name ? 'selected' : '' }}>
                                    {{ $gameplatform->gameplatform_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('gameplatform_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="icon" class="form-label">{{ __('game.icon') }}</label>
                            <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="api" class="form-label">{{ __('game.api') }}</label>
                        <input type="text" class="form-control @error('api') is-invalid @enderror" id="api" name="api" value="{{ old('api') }}" required>
                        @error('api')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('gametype.select') }}</label>
                        <select class="form-control @error('type') is-invalid @enderror" id="type" name="type">
                            <option value="">{{ __('gametype.select') }}</option>
                            @foreach ($gametypes as $gametype)
                                <option value="{{ $gametype->gametype_id }}" {{ old('type') == $gametype->type_name ? 'selected' : '' }}>
                                    {{ __('gametype.'.$gametype->type_name) }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.game.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
