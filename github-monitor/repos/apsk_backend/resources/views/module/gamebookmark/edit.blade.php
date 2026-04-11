@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('gamebookmark.edit_gamebookmark'))
@section('header-title', __('gamebookmark.edit_gamebookmark'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('gamebookmark.edit_gamebookmark') }}: {{ $gamebookmark->gamebookmark_name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.gamebookmark.update', $gamebookmark->gamebookmark_id) }}" method="POST" class="p-4">
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

                    <div class="mb-3">
                        <label for="gamebookmark_name" class="form-label">{{ __('gamebookmark.gamebookmark_name') }}</label>
                        <input type="text" class="form-control @error('gamebookmark_name') is-invalid @enderror" id="gamebookmark_name" name="gamebookmark_name" value="{{ old('gamebookmark_name', $gamebookmark->gamebookmark_name) }}" required>
                        @error('gamebookmark_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="game_id" class="form-label">{{ __('game.select') }}</label>
                        <select class="form-control @error('game_id') is-invalid @enderror" id="game_id" name="game_id">
                            <option value="">{{ __('game.select') }}</option>
                            @foreach ($games as $game)
                                <option value="{{ $game->game_id }}" {{ $gamebookmark->game_id == $game->game_id ? 'selected' : '' }}>
                                    {{ $game->game_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('game_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="member_id" class="form-label">{{ __('member.select') }}</label>
                        <select class="form-control @error('member_id') is-invalid @enderror" id="member_id" name="member_id">
                            <option value="">{{ __('member.select') }}</option>
                            @foreach ($members as $member)
                                <option value="{{ $gamebookmark->member_id }}" {{ $member->member_id == $gamebookmark->member_id ? 'selected' : '' }}>
                                    {{ $member->member_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('member_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $gamebookmark->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('gamebookmark.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('gamebookmark.edit_gamebookmark') }}</button>
                    <a href="{{ route('admin.gamebookmark.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
