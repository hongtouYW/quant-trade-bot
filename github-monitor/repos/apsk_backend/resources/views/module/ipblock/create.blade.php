@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('ipblock.add_new_ipblock'))
@section('header-title', __('ipblock.add_new_ipblock'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('ipblock.add_new_ipblock') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.ipblock.store') }}" method="POST" class="p-4">
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
                        <label for="ip" class="form-label">{{ __('ipblock.ip') }}</label>
                        <input type="text" class="form-control @error('ip') is-invalid @enderror" id="ip" name="ip" value="{{ old('ip') }}" required>
                        @error('ip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">{{ __('ipblock.reason') }}</label>
                        <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" maxlength="10000" style="height: 100px;">{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @masteradmin
                    <div class="mb-3">
                        <label for="agent_id" class="form-label">{{ __('agent.select') }}</label>
                            <select class="form-control select2 @error('agent_id') is-invalid @enderror"
                                id="agent_id"
                                name="agent_id"
                                required>
                            <option value="">{{ __('agent.select') }}</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->agent_id }}"
                                    {{ old('agent_id', '') == $agent->agent_id ? 'selected' : '' }}>
                                    {{ $agent->agent_name }} ({{ $agent->agent_code }})
                                </option>
                            @endforeach
                        </select>
                        @error('agent_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @endmasteradmin
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.ipblock.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
