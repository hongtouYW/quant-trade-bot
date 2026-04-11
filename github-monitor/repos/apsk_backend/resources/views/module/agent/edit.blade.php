@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('agent.edit_agent'))
@section('header-title', __('agent.edit_agent'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('agent.edit_agent') }}: {{ $agent->edit_agent }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.agent.update', $agent->agent_id) }}" method="POST" class="p-4" enctype="multipart/form-data">
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

                    @if ( Auth::user()->user_role === 'superadmin' || Auth::user()->user_role === 'masteradmin' )
                        <div class="mb-3">
                            <div class="form-group">
                                <label for="icon" class="form-label">{{ __('agent.icon') }}</label>
                                <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                                <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                                <span class="text-sm  text-secondary text-bold">{{__('messages.horizontalpng')}}</span>
                                @if ($agent->icon)
                                    <p class="text-sm mt-2">Current icon: <img src="{{ asset('storage/' . $agent->icon) }}" alt="{{ $agent->agent_name }} Icon" class="img-fluid" style="max-width: 50px;"></p>
                                @endif
                                @error('icon')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label for="agent_name" class="form-label">{{ __('agent.agent_name') }}</label>
                        <input type="text" class="form-control @error('agent_name') is-invalid @enderror" id="agent_name" name="agent_name" value="{{ old('agent_name', $agent->agent_name) }}" required>
                        @error('agent_name')
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
                                {{ $agent->isChatAccountCreate == 1 ? 'checked' : '' }}>
                            <label class="custom-control-label" for="isChatAccountCreate">
                                {{ __('agent.chataccountcreate') }}
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="support" class="form-label">{{ __('agent.support') }}</label>
                        <input type="text" class="form-control @error('support') is-invalid @enderror" id="support" name="support" value="{{ old('support', $agent->support) }}">
                        @error('support')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="telegramsupport" class="form-label">{{ __('agent.telegramsupport') }}</label>
                        <input type="text" class="form-control @error('telegramsupport') is-invalid @enderror" id="telegramsupport" name="telegramsupport" value="{{ old('telegramsupport', $agent->telegramsupport) }}">
                        @error('telegramsupport')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="whatsappsupport" class="form-label">{{ __('agent.whatsappsupport') }}</label>
                        <input type="text" class="form-control @error('whatsappsupport') is-invalid @enderror" id="whatsappsupport" name="whatsappsupport" value="{{ old('whatsappsupport', $agent->whatsappsupport) }}">
                        @error('whatsappsupport')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="principal" class="form-label">{{ __('agent.principal') }}</label>
                        <input type="number" class="form-control @error('principal') is-invalid @enderror" id="principal" name="principal" value="{{ old('principal', $agent->principal) }}" min="0.00" step="0.01">
                        @error('principal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $agent->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('agent.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('agent.edit_agent') }}</button>
                    <a href="{{ route('admin.agent.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
