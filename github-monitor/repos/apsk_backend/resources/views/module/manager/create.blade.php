@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('manager.add_new_manager'))
@section('header-title', __('manager.add_new_manager'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('manager.add_new_manager') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.manager.store') }}" method="POST" class="p-4">
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
                        <label for="manager_login" class="form-label">{{ __('manager.manager_login') }}</label>
                        <input type="text" class="form-control @error('manager_login') is-invalid @enderror" id="manager_login" name="manager_login" value="{{ old('manager_login') }}" required>
                        @error('manager_login')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="manager_pass" class="form-label">{{ __('manager.manager_password') }}</label>
                        <input type="password" class="form-control @error('manager_pass') is-invalid @enderror" id="manager_pass" name="manager_pass" required>
                        @error('manager_pass')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="manager_name" class="form-label">{{ __('manager.manager_name') }}</label>
                        <input type="text" class="form-control @error('manager_name') is-invalid @enderror" id="manager_name" name="manager_name" value="{{ old('manager_name') }}" required>
                        @error('manager_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('manager.phone') }}</label>
                        <div class="input-group">
                            <select class="form-control" name="phone_country" style="max-width: 100px;">
                                <option value="+60" {{ old('phone_country', '+60') == '+60' ? 'selected' : '' }}>+60</option>
                                <option value="+65" {{ old('phone_country') == '+65' ? 'selected' : '' }}>+65</option>
                                <option value="+86" {{ old('phone_country') == '+86' ? 'selected' : '' }}>+86</option>
                            </select>
                            <input
                                type="text"
                                class="form-control @error('phone_number') is-invalid @enderror"
                                name="phone_number"
                                value="{{ old('phone_number') }}"
                                placeholder="123456789"
                                inputmode="numeric"
                                pattern="[0-9]{7,12}"
                                title="{{ __('messages.phonevalidate') }}"
                            >
                        </div>
                        @error('phone_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="principal" class="form-label">{{ __('manager.principal') }}</label>
                        <input type="number" class="form-control @error('principal') is-invalid @enderror" id="principal" name="principal" value="0.00" min="0.00" step="0.01" required>
                        @error('principal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label  class="form-label">{{ __('state.state_name') }}</label>
                        <input type="text" class="form-control" 
                             value="{{ $state_name }}" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="area_code" class="form-label">{{ __('area.select') }}</label>
                            <select class="form-control select2 @error('area_code') is-invalid @enderror"
                                id="area_code"
                                name="area_code"
                                required>
                            <option value="">{{ __('area.select') }}</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->area_code }}" {{ old('area_code') == $area->area_code ? 'selected' : '' }}>
                                    {{ $area->area_name }} ({{ $area->area_code }})
                                </option>
                            @endforeach
                        </select>
                        @error('area_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox"
                        id="can_view_credential" 
                        name="can_view_credential" value="1" >
                        <label class="form-check-label" for="can_view_credential">
                            {{ __('manager.can_view_credential') }}
                        </label>
                        @error('can_view_credential')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.manager.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
