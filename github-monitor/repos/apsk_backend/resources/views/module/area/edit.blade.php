@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('area.edit_area'))
@section('header-title', __('area.edit_area'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('area.edit_area') }}: {{ $area->area_code }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.area.update', $area->area_code) }}" method="POST" class="p-4">
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
                        <label for="area_code" class="form-label">{{ __('area.area_code') }}</label>
                        <input type="text" class="form-control @error('area_code') is-invalid @enderror" id="area_code" name="area_code" value="{{ old('area_code', $area->area_code) }}" required readonly>
                        @error('area_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="area_name" class="form-label">{{ __('area.area_name') }}</label>
                        <input type="text" class="form-control @error('area_name') is-invalid @enderror" id="area_name" name="area_name" value="{{ old('area_name', $area->area_name) }}" required>
                        @error('area_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="area_type" class="form-label">{{ __('area.select_type') }}</label>
                        <select class="form-control @error('area_type') is-invalid @enderror" id="area_type" name="area_type">
                            <option value="">{{ __('area.select_type') }}</option>
                            <option value="city" {{ $area->area_type == "city" ? 'selected' : '' }}>
                                {{ __( 'area.city' ) }}
                            </option>
                            <option value="district" {{ $area->area_type == "district" ? 'selected' : '' }}>
                                {{ __( 'area.district' ) }}
                            </option>
                        </select>
                        @error('area_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="postal_code" class="form-label">{{ __('area.postal_code') }}</label>
                        <input type="text" class="form-control @error('postal_code') is-invalid @enderror" id="postal_code" name="postal_code" value="{{ old('postal_code', $area->postal_code) }}" required>
                        @error('postal_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="country_code" class="form-label">{{ __('country.select') }}</label>
                        <select class="form-control @error('country_code') is-invalid @enderror" id="country_code" name="country_code">
                            <option value="">{{ __('country.select') }}</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->country_code }}" {{ $area->country_code == $country->country_code ? 'selected' : '' }}>
                                    {{ $country->country_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('country_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="state_code" class="form-label">{{ __('state.select') }}</label>
                        <select class="form-control @error('state_code') is-invalid @enderror" id="state_code" name="state_code">
                            <option value="">{{ __('area.select') }}</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->state_code }}" {{ $area->state_code == $state->state_code ? 'selected' : '' }}>
                                    {{ $state->state_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('state_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $area->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('area.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('area.edit_area') }}</button>
                    <a href="{{ route('admin.area.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
