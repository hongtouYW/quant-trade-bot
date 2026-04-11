@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('game.edit_game'))
@section('header-title', __('game.edit_game'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('game.edit_game') }}: {{ $game->game_name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.game.update', $game->game_id) }}" method="POST" class="p-4" enctype="multipart/form-data">
                    @csrf
                    @method('PUT') {{-- Use PUT method for update --}}

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

                    {{-- Game Name --}}
                    <div class="mb-3">
                        <label for="game_name" class="form-label">{{ __('game.game_name') }}</label>
                        <input type="text" class="form-control @error('game_name') is-invalid @enderror" id="game_name" name="game_name" value="{{ old('game_name', $game->game_name) }}" required>
                        @error('game_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Game Type --}}
                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('gametype.select') }}</label>
                        <select class="form-control select2 @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">{{ __('gametype.select') }}</option>
                            @foreach ($gametypes as $gametype)
                                <option value="{{ $gametype->gametype_id }}"
                                    {{ old('type', $game->type) == $gametype->gametype_id ? 'selected' : '' }}>
                                    {{ __('gametype.' . $gametype->type_name) }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Hidden fields --}}
                    <input type="hidden" name="game_desc" value="{{ old('game_desc', $game->game_desc) }}">
                    <input type="hidden" name="gameplatform_id" value="{{ old('gameplatform_id', $game->gameplatform_id) }}">
                    <input type="hidden" name="api" value="{{ old('api', $game->api) }}">

                    {{-- Game Icon --}}
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="icon" class="form-label">{{ __('game.icon') }}</label>
                            <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($game->icon)
                                <p class="text-sm mt-2">Current icon: <img src="{{ asset('storage/' . $game->icon) }}" alt="{{ $game->game_name }} Icon" class="img-fluid" style="max-width: 50px;"></p>
                            @endif
                            @error('icon')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Game Icon ZH --}}
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="icon_zh" class="form-label">{{ __('game.icon_zh') }}</label>
                            <input type="file" class="form-control @error('icon_zh') is-invalid @enderror" id="icon_zh" name="icon_zh" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($game->icon_zh)
                                <p class="text-sm mt-2">Current icon_zh: <img src="{{ asset('storage/' . $game->icon_zh) }}" alt="{{ $game->game_name }} Icon" class="img-fluid" style="max-width: 50px;"></p>
                            @endif
                            @error('icon_zh')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $game->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('game.active_status') }}</label>
                        @error('status')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('game.edit_game') }}</button>
                    <a href="{{ route('admin.game.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
