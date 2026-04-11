@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('country.edit_country'))
@section('header-title', __('country.edit_country'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('country.edit_country') }}: {{ $country->country_code }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.country.update', $country->country_code) }}" method="POST" class="p-4">
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
                        <label for="country_code" class="form-label">{{ __('country.country_code') }}</label>
                        <input type="text" class="form-control @error('country_code') is-invalid @enderror" id="country_code" name="country_code" value="{{ old('country_code', $country->country_code) }}" required readonly>
                        @error('country_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="country_name" class="form-label">{{ __('country.country_name') }}</label>
                        <input type="text" class="form-control @error('country_name') is-invalid @enderror" id="country_name" name="country_name" value="{{ old('country_name', $country->country_name) }}" required>
                        @error('country_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $country->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('country.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('country.edit_country') }}</button>
                    <a href="{{ route('admin.country.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
