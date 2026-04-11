@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('gamepoint.add_new_gamepoint'))
@section('header-title', __('gamepoint.add_new_gamepoint'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('gamepoint.add_new_gamepoint') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.gamepoint.store') }}" method="POST" class="p-4">
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
                        <label for="gamemember_id" class="form-label">{{ __('gamemember.select') }}</label>
                        <select class="form-control @error('gamemember_id') is-invalid @enderror" id="gamemember_id" name="gamemember_id">
                            <option value="">{{ __('gamemember.select') }}</option>
                            @foreach ($gamemembers as $gamemember)
                                <option value="{{ $gamemember->gamemember_id }}" {{ old('gamemember_id') == $gamemember->gamemember_id ? 'selected' : '' }}>
                                    {{ $gamemember->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('member_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('gamepoint.select_type') }}</label>
                        <select class="form-control @error('type') is-invalid @enderror" id="type" name="type">
                            <option value="">{{ __('gamepoint.select_type') }}</option>
                            @php
                                $types = ['bonus','reward','reload','withdraw'];
                            @endphp
                            @foreach ($types as $type)
                                <option value="{{ $type }}" {{ old('type') == $type ? 'selected' : '' }}>
                                    {{ __('gamepoint.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">{{ __('messages.amount') }}</label>
                        <input type="number" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" step="1.00" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="start_on" class="form-label">{{ __('gamepoint.start_on') }}</label>
                        <input type="date" class="form-control @error('start_on') is-invalid @enderror" id="start_on" name="start_on" value="{{ old('start_on') }}" required>
                        @error('start_on')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.gamepoint.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
