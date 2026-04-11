@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('gamepoint.edit_gamepoint'))
@section('header-title', __('gamepoint.edit_gamepoint'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('gamepoint.edit_gamepoint') }}: {{ $gamepoint->gamepoint_id }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.gamepoint.update', $gamepoint->gamepoint_id) }}" method="POST" class="p-4">
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
                        <input type="text" class="form-control" id="gamemember_id" name="gamemember_id" value="{{ $gamepoint->gamemember_id }}" style="display:none">
                        <input type="text" class="form-control" id="type" name="type" value="{{ $gamepoint->type }}" style="display:none">
                    </div>
                    <div class="mb-3">
                        <label for="gamemember_id" class="form-label">{{ __('gamemember.select') }}</label>
                        <input type="text" class="form-control" value="{{ $gamepoint->name }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('gamepoint.select_type') }}</label>
                        <input type="text" class="form-control" value="{{ __('gamepoint.'.$gamepoint->type) }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">{{ __('messages.amount') }}</label>
                        <input type="number" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', $gamepoint->amount) }}" step="1.00" readonly>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">{{ __('gamepoint.status') }}</label>
                        <select class="form-control @error('status') is-invalid @enderror" id="status" name="status">
                            <option value="">{{ __('gamepoint.select') }}</option>
                            <option value="-1" {{ $gamepoint->select == -1 ? 'selected' : '' }}>
                                {{ __('gamepoint.inactive') }}
                            </option>
                            <option value="0" {{ $gamepoint->select == 0 ? 'selected' : '' }}>
                                {{ __('gamepoint.pending') }}
                            </option>
                            <option value="1" {{ $gamepoint->select == 1 ? 'selected' : '' }}>
                                {{ __('gamepoint.Approve') }}
                            </option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('gamepoint.edit_gamepoint') }}</button>
                    <a href="{{ route('admin.gamepoint.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
