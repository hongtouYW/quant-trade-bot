@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('application.add_new_application'))
@section('header-title', __('application.add_new_application'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('application.add_new_application') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.application.store') }}" method="POST" class="p-4">
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
                        <label for="application_name" class="form-label">{{ __('application.application_name') }}</label>
                        <input type="text" class="form-control @error('application_name') is-invalid @enderror" id="application_name" name="application_name" value="{{ old('application_name') }}" required>
                        @error('application_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('application.typeselect') }}</label>
                        <select class="form-control select2 @error('type') is-invalid @enderror" 
                            id="type" 
                            name="type"
                            required>
                            <option value="">{{ __('application.typeselect') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" {{ old('type') == $type ? 'selected' : '' }}>
                                    {{ __('application.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="platform" class="form-label">{{ __('application.platformselect') }}</label>
                        <select class="form-control select2 @error('platform') is-invalid @enderror" 
                            id="platform" 
                            name="platform"
                            required>
                            <option value="">{{ __('application.platformselect') }}</option>
                            @foreach ($platforms as $platform)
                                <option value="{{ $platform }}" {{ old('platform') == $platform ? 'selected' : '' }}>
                                    {{ __('application.'.$platform) }}
                                </option>
                            @endforeach
                        </select>
                        @error('platform')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="version" class="form-label">{{ __('application.version') }}</label>
                        <input type="text" class="form-control @error('version') is-invalid @enderror" id="version" name="version" value="{{ old('version') }}" required>
                        @error('version')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="latest_version" class="form-label">{{ __('application.latest_version') }}</label>
                        <input type="text" class="form-control @error('latest_version') is-invalid @enderror" 
                            id="latest_version" 
                            name="latest_version" 
                            value="{{ old('latest_version') }}" >
                        @error('latest_version')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="minimun_version" class="form-label">{{ __('application.minimun_version') }}</label>
                        <input type="text" class="form-control @error('minimun_version') is-invalid @enderror" 
                            id="minimun_version" 
                            name="minimun_version" 
                            value="{{ old('minimun_version') }}" >
                        @error('minimun_version')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="download_url" class="form-label">{{ __('application.download_url') }}</label>
                        <input type="url" class="form-control @error('download_url') is-invalid @enderror"
                               id="download_url" name="download_url" value="{{ old('download_url') }}">
                        @error('download_url')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="changelog" class="form-label">{{ __('application.changelog') }}</label>
                        <textarea class="form-control my-editor @error('changelog') is-invalid @enderror"
                            id="changelog"
                            name="changelog"
                            maxlength="10000"
                            style="height: 300px;">{{ old('changelog') }}</textarea>
                        @error('changelog')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="force_update" name="force_update" 
                            value="1" {{ old('force_update') ? 'checked' : '' }} >
                        <label class="form-check-label" for="force_update">{{ __('application.force_update') }}</label>
                        @error('force_update')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" 
                            value="1" {{ old('status') ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('application.is_use') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.application.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('js')
    @include('components.tinymce.setup')
@endpush
