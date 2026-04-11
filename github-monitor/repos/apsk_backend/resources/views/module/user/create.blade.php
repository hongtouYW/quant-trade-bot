@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('user.add_new_user'))
@section('header-title', __('user.add_new_user'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <h6>{{ __('user.add_new_user') }}</h6>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    <form action="{{ route('admin.user.store') }}" method="POST" class="p-4">
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
                            <label for="user_login" class="form-label">{{ __('user.user_login') }}</label>
                            <input type="text" class="form-control @error('user_login') is-invalid @enderror" id="user_login" name="user_login" value="{{ old('user_login') }}" required>
                            @error('user_login')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="user_pass" class="form-label">{{ __('user.user_password') }}</label>
                            <input type="password" class="form-control @error('user_pass') is-invalid @enderror" id="user_pass" name="user_pass" required>
                            @error('user_pass')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="user_name" class="form-label">{{ __('user.user_name') }}</label>
                            <input type="text" class="form-control @error('user_name') is-invalid @enderror" id="user_name" name="user_name" value="{{ old('user_name') }}" required>
                            @error('user_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="user_role" class="form-label">{{ __('user.user_role') }}</label>
                            <select class="form-control @error('user_role') is-invalid @enderror" id="user_role" name="user_role">
                                <option value="">{{ __('role.select') }}</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->role_name }}" {{ old('user_role') == $role->role_name ? 'selected' : '' }}>
                                        {{ $role->role_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_role')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @masteradmin
                        <div class="mb-3">
                            <label for="agent_id" class="form-label">{{ __('user.agent') }}</label>
                            <select class="form-control @error('agent_id') is-invalid @enderror" id="agent_id" name="agent_id">
                                <option value="">{{ __('user.select_agent') }}</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->agent_id }}" {{ old('agent_id') == $agent->agent_id ? 'selected' : '' }}>
                                        {{ $agent->agent_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('agent_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endmasteradmin
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

                        <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                        <a href="{{ route('admin.user.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
