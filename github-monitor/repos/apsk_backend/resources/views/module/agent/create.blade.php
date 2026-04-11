@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('agent.add_new_agent'))
@section('header-title', __('agent.add_new_agent'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('agent.add_new_agent') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.agent.store') }}" method="POST" class="p-4" enctype="multipart/form-data">
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

                    @if ( Auth::user()->user_role === 'superadmin' || Auth::user()->user_role === 'masteradmin' )
                        <div class="mb-3">
                            <div class="form-group">
                                <label for="icon" class="form-label">{{ __('agent.icon') }}</label>
                                <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                                <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                                <span class="text-sm  text-secondary text-bold">{{__('messages.horizontalpng')}}</span>
                                @error('icon')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label for="agent_name" class="form-label">{{ __('agent.agent_name') }}</label>
                        <input type="text" class="form-control @error('agent_name') is-invalid @enderror" id="agent_name" name="agent_name" value="{{ old('agent_name') }}" required>
                        @error('agent_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="country_code" class="form-label">{{ __('country.select') }}</label>
                        <select class="form-control select2 @error('country_code') is-invalid @enderror"
                            id="country_code"
                            name="country_code"
                            style="width:100%;"
                            required>
                            <option value="">{{ __('country.select') }}</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->country_code }}" {{ old('country_code') == $country->country_code ? 'selected' : '' }}>
                                    {{ $country->country_name }} ({{ $country->country_code }})
                                </option>
                            @endforeach
                        </select>
                        @error('country_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="state_code" class="form-label">{{ __('state.select') }}</label>
                        <select class="form-control select2 @error('state_code') is-invalid @enderror"
                            id="state_code"
                            name="state_code"
                            style="width:100%;"
                            required
                            disabled>
                            <option value="">{{ __('state.select') }}</option>
                            @foreach ($states as $state)
                                <option value="{{ $state->state_code }}" {{ old('state_code') == $state->state_code ? 'selected' : '' }}>
                                    {{ $state->state_name }} ({{ $state->state_code }})
                                </option>
                            @endforeach
                        </select>
                        @error('state_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="custom-control custom-switch">
                            <input class="custom-control-input"
                                type="checkbox"
                                id="isChatAccountCreate"
                                name="isChatAccountCreate"
                                value="1"
                                {{ old('isChatAccountCreate') == 1 ? 'checked' : '' }}>
                            <label class="custom-control-label" for="isChatAccountCreate">
                                {{ __('agent.chataccountcreate') }}
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="support" class="form-label">{{ __('agent.support') }}</label>
                        <input type="text" class="form-control @error('support') is-invalid @enderror" id="support" name="support" value="{{ old('support') }}">
                        @error('support')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="telegramsupport" class="form-label">{{ __('agent.telegramsupport') }}</label>
                        <input type="text" class="form-control @error('telegramsupport') is-invalid @enderror" id="telegramsupport" name="telegramsupport" value="{{ old('telegramsupport') }}">
                        @error('telegramsupport')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="whatsappsupport" class="form-label">{{ __('agent.whatsappsupport') }}</label>
                        <input type="text" class="form-control @error('whatsappsupport') is-invalid @enderror" id="whatsappsupport" name="whatsappsupport" value="{{ old('whatsappsupport') }}">
                        @error('whatsappsupport')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="principal" class="form-label">{{ __('agent.principal') }}</label>
                        <input 
                            type="number" 
                            class="form-control @error('principal') is-invalid @enderror" 
                            id="principal" 
                            name="principal" 
                            value="50000.00"
                            min="50000" 
                            step="0.01" 
                            required
                        >
                        @error('principal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <hr>

                    <h6 class="mt-4">{{ __('role.superadmin') }}</h6>
                    <div class="mb-3">
                        <label for="user_login" class="form-label">{{ __('user.user_login') }}</label>
                        <input type="text"
                               class="form-control @error('user_login') is-invalid @enderror"
                               id="user_login"
                               name="user_login"
                               value="{{ old('user_login') }}"
                               required>
                        @error('user_login')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="user_pass" class="form-label">{{ __('user.user_password') }}</label>
                        <input type="password"
                               class="form-control @error('user_pass') is-invalid @enderror"
                               id="user_pass"
                               name="user_pass"
                               required>
                        @error('user_pass')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="user_name" class="form-label">{{ __('user.user_name') }}</label>
                        <input type="text"
                               class="form-control @error('user_name') is-invalid @enderror"
                               id="user_name"
                               name="user_name"
                               value="{{ old('user_name') }}"
                               required>
                        @error('user_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.agent.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js')
<script>
    // When country changes
    $('#country_code').on('change', function () {
        let country = $(this).val();

        // Reset state dropdown
        $('#state_code').empty().append('<option value="">{{ __("state.select") }}</option>');
        $('#state_code').prop('disabled', true); // Start disabled

        if (country) {
            // AJAX request to filter states by country
            $.get("{{ route('admin.agent.filterstate', ':country_code') }}".replace(':country_code', country))
                .done(function (data) {
                    if (data.status && data.data.length > 0) {
                        $.each(data.data, function (i, state) {
                            $('#state_code').append(
                                $('<option>', { value: state.state_code, text: state.state_name + "("+state.state_code+")" })
                            );
                        });
                        $('#state_code').prop('disabled', false); // Enable only on success
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('Error fetching states:', errorThrown, jqXHR.responseText);
                    $('#state_code').prop('disabled', true); // Keep disabled on error
                });
        }
    });
</script>
@endsection
