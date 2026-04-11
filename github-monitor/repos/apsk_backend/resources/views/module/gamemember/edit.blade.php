@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('gamemember.edit_gamemember'))
@section('header-title', __('gamemember.edit_gamemember'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('gamemember.edit_gamemember') }}: {{ $gamemember->name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.gamemember.update', $gamemember->gamemember_id) }}" method="POST" class="p-4">
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
                        <label for="login" class="form-label">{{ __('gamemember.login') }}</label>
                        <input type="text" class="form-control @error('login') is-invalid @enderror" id="login" name="login" value="{{ old('login', $gamemember->login) }}" required>
                        @error('login')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="pass" class="form-label">{{ __('gamemember.password') }}</label>
                        <input type="password" class="form-control @error('pass') is-invalid @enderror" id="pass" name="pass" value="{{ old('pass', $gamemember->pass) }}" required>
                        @error('pass')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="game_id" class="form-label">{{ __('game.select') }}</label>
                        <select class="form-control @error('game_id') is-invalid @enderror" id="game_id" name="game_id">
                            <option value="">{{ __('game.select') }}</option>
                            @foreach ($games as $game)
                                <option value="{{ $game->game_id }}" {{ $gamemember->game_id == $game->game_id ? 'selected' : '' }}>
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
                                <option value="{{ $gamemember->member_id }}" {{ $member->member_id == $gamemember->member_id ? 'selected' : '' }}>
                                    {{ $member->member_id }}
                                </option>
                            @endforeach
                        </select>
                        @error('member_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="balance" class="form-label">{{ __('gamemember.balance') }}</label>
                        <input type="number" class="form-control @error('balance') is-invalid @enderror" id="balance" name="balance" value="{{ old('balance', $gamemember->balance) }}" min="0.00" step="0.01" readonly>
                        @error('balance')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $gamemember->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('gamemember.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('gamemember.edit_gamemember') }}</button>
                    <a href="{{ route('admin.gamemember.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
