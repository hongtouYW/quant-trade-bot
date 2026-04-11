@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('notification.edit_notification'))
@section('header-title', __('notification.edit_notification'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('notification.edit_notification') }}: {{ $notification->notification_id }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.notification.update', $notification->title) }}" method="POST" class="p-4">
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
                        <label for="title" class="form-label">{{ __('notification.title') }}</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $notification->title) }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="notification_desc" class="form-label">{{ __('notification.notification_desc') }}</label>
                        <textarea class="form-control @error('notification_desc') is-invalid @enderror" id="notification_desc" name="notification_desc" maxlength="1000" style="height: 100px;">{{ old('notification_desc', $notification->notification_desc) }}</textarea>
                        @error('notification_desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="notification_type" class="form-label">{{ __('notification.select') }}</label>
                        <select class="form-control @error('notification_type') is-invalid @enderror" id="notification_type" name="notification_type">
                            <option value="">{{ __('notification.select') }}</option>
                            @php
                                $types = ['admin','manager','role','event','version','game'];
                            @endphp
                            @foreach ($types as $type)
                                <option value="{{ $type }}" {{ $type == $notification->notification_type ? 'selected' : '' }}>
                                    {{ __('notification.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('notification_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $notification->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('notification.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('notification.edit_notification') }}</button>
                    <a href="{{ route('admin.notification.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
