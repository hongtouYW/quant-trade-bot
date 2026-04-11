@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('user.edit_user'))
@section('header-title', __('user.edit_user'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <h6>{{ __('user.edit_user') }}: {{ $user->user_name }}</h6>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    <form action="{{ route('admin.user.update', $user->user_id) }}" method="POST" class="p-4">
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
                            <label for="user_name" class="form-label">{{ __('user.user_name') }}</label>
                            <input type="text" class="form-control @error('user_name') is-invalid @enderror" id="user_name" name="user_name" value="{{ old('user_name', $user->user_name) }}" required readonly>
                            @error('user_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="user_login" class="form-label">{{ __('user.user_login') }}</label>
                            <input type="text" class="form-control @error('user_login') is-invalid @enderror" id="user_login" name="user_login" value="{{ old('user_login', $user->user_login) }}" required>
                            @error('user_login')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="user_pass" class="form-label">{{ __('user.user_password') }}</label>
                            <input type="password" class="form-control @error('user_pass') is-invalid @enderror" id="user_pass" name="user_pass" placeholder="{{ __('user.leave_blank_for_no_change') }}">
                            @error('user_pass')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="user_role" class="form-label">{{ __('user.user_role') }}</label>
                            <input type="text" class="form-control" value="{{ $user->user_role }}" readonly>
                            <input type="hidden" class="form-control @error('user_role') is-invalid @enderror" id="user_role" name="user_role" value="{{ old('user_role', $user->user_role) }}" readonly>
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
                                    <option value="{{ $agent->agent_id }}" {{ $user->agent_id == $agent->agent_id ? 'selected' : '' }}>
                                        {{ $agent->agent_name }} ({{ $agent->agent_code ?? $agent->agent_id }})
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
                                disabled>
                                <option value="">{{ __('area.select') }}</option>
                                @foreach ($areas as $area)
                                    <option value="{{ $area->area_code }}" {{ $user->area_code == $area->area_code ? 'selected' : '' }}>
                                        {{ $area->area_name }} ({{ $area->area_code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('area_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3 form-check form-switch" style="display:{{ $user->user_role == 'superadmin' ? 'none' : '' }};" >
                            <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $user->status) == 1 ? 'checked' : '' }} >
                            <label class="form-check-label" for="status">{{ __('user.active_status') }}</label>
                            @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('user.edit_user') }}</button>
                        <a href="{{ route('admin.user.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('js')
<script>

    function resetarea() {
        // Reset area dropdown
        $('#area_code').empty().append('<option value="">{{ __("area.select") }}</option>');
        $('#area_code').val(null).trigger('change');
    }
</script>
@endsection
