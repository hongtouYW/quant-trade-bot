@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('provider.edit_provider'))
@section('header-title', __('provider.edit_provider'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('provider.edit_provider') }}: {{ $provider->provider_name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.provider.update', $provider->provider_id) }}" method="POST" class="p-4" enctype="multipart/form-data">
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
                        <label for="provider_name" class="form-label">{{ __('provider.provider_name') }}</label>
                        <input type="text" class="form-control @error('provider_name') is-invalid @enderror" id="provider_name" name="provider_name" value="{{ old('provider_name', $provider->provider_name) }}" required readonly>
                        @error('provider_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="gameplatform_id" class="form-label">{{ __('game.platformselect') }}</label>
                        <select class="form-control @error('gameplatform_id') is-invalid @enderror" id="gameplatform_id" name="gameplatform_id" disabled>
                            <option value="">{{ __('game.platformselect') }}</option>
                            @foreach ($gameplatforms as $gameplatform)
                                <option value="{{ $gameplatform->gameplatform_id }}" {{ $provider->gameplatform_id == $gameplatform->gameplatform_id ? 'selected' : '' }}>
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
                            <label for="icon" class="form-label">{{ __('provider.icon') }}</label>
                            <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($provider->icon)
                                <p class="text-sm mt-2">
                                    Current icon: <img src="{{ asset('storage/' . $provider->icon) }}" 
                                    loading="lazy" alt="{{ $provider->provider_name }}" class="img-fluid" style="max-width: 50px;">
                                </p>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" 
                                        class="custom-control-input"
                                        id="delete_icon" name="delete_icon" value="1">
                                    <label class="custom-control-label text-danger" for="delete_icon">
                                        {{ __('messages.deletefile') }}
                                    </label>
                                </div>
                            @endif
                            @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="icon_sm" class="form-label">{{ __('provider.icon_sm') }}</label>
                            <input type="file" class="form-control @error('icon_sm') is-invalid @enderror" id="icon_sm" name="icon_sm" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($provider->icon_sm)
                                <p class="text-sm mt-2">
                                    Current icon_sm: <img src="{{ asset('storage/' . $provider->icon_sm) }}" 
                                    loading="lazy" alt="{{ $provider->provider_name }}" class="img-fluid" style="max-width: 100px;">
                                </p>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" 
                                        class="custom-control-input"
                                        id="delete_icon_sm" name="delete_icon_sm" value="1">
                                    <label class="custom-control-label text-danger" for="delete_icon_sm">
                                        {{ __('messages.deletefile') }}
                                    </label>
                                </div>
                            @endif
                            @error('icon_sm')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="banner" class="form-label">{{ __('provider.banner') }}</label>
                            <input type="file" class="form-control @error('banner') is-invalid @enderror" id="banner" name="banner" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($provider->banner)
                                <p class="text-sm mt-2">
                                    Current banner: <img src="{{ asset('storage/' . $provider->banner) }}" 
                                    loading="lazy" alt="{{ $provider->provider_name }}" class="img-fluid" style="max-width: 100px;">
                                </p>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" 
                                        class="custom-control-input"
                                        id="delete_banner" name="delete_banner" value="1">
                                    <label class="custom-control-label text-danger" for="delete_banner">
                                        {{ __('messages.deletefile') }}
                                    </label>
                                </div>
                            @endif
                            @error('banner')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="android" class="form-label">{{ __('provider.android') }}</label>
                        <input type="text" class="form-control @error('android') is-invalid @enderror" id="android" name="android" value="{{ old('android', $provider->android) }}">
                        @error('android')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="ios" class="form-label">{{ __('provider.ios') }}</label>
                        <input type="text" class="form-control @error('android') is-invalid @enderror" id="ios" name="ios" value="{{ old('ios', $provider->ios) }}">
                        @error('ios')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="download" class="form-label">{{ __('provider.download') }}</label>
                        <input type="text" class="form-control @error('download') is-invalid @enderror" id="download" name="download" value="{{ old('download', $provider->download) }}">
                        @error('ios')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('provider.provider_category') }}</label>
                        <select class="form-control select2 @error('provider_category') is-invalid @enderror" id="provider_category" name="provider_category">
                            <option value="">{{ __('provider.provider_category') }}</option>
                            @foreach ($provider_categorys as $provider_category)
                                <option value="{{ $provider_category }}"
                                    {{ old('provider', $provider->provider_category ?? '') == $provider_category ? 'selected' : '' }}>
                                    {{ __('provider.'.$provider_category) }}
                                </option>
                            @endforeach
                        </select>
                        @error('provider_category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $provider->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('provider.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('provider.edit_provider') }}</button>
                    <a href="{{ route('admin.provider.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
