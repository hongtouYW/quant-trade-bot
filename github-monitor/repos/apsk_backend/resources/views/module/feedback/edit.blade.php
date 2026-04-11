@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('feedback.edit_feedback'))
@section('header-title', __('feedback.edit_feedback'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('feedback.edit_feedback') }}: {{ $feedback->title }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.feedback.update', $feedback->feedback_id) }}" method="POST" class="p-4" enctype="multipart/form-data">
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
                        <label for="title" class="form-label">{{ __('feedback.title') }}</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', __('feedback.'.$feedback->title) ) }}" readonly>
                        @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="feedback_desc" class="form-label">{{ __('feedback.feedback_desc') }}</label>
                        <input type="text" class="form-control @error('feedback_desc') is-invalid @enderror" id="feedback_desc" name="feedback_desc" value="{{ old('feedback_desc', $feedback->feedback_desc) }}" required readonly>
                        @error('feedback_desc')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="feedback_type" class="form-label">{{ __('feedback.selecttype') }}</label>
                        <select class="form-control select2 @error('feedback_type') is-invalid @enderror" 
                            id="feedback_type" 
                            name="feedback_type"
                            required>
                            <option value="">{{ __('feedback.selecttype') }}</option>
                            @foreach ($feedback_types as $type)
                                <option value="{{ $type }}" {{ $feedback->feedback_type == $type ? 'selected' : '' }}>
                                    {{ __('feedback.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('feedback_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('member.member_name') }}</label>
                        <div class="form-control bg-light">
                            {{ optional($members->firstWhere('member_id', $feedback->member_id))->member_name ?? '-' }}
                        </div>

                        <input type="hidden" name="member_id" value="{{ $feedback->member_id }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('shop.shop_name') }}</label>
                        <div class="form-control bg-light">
                            {{ optional($shops->firstWhere('shop_id', $feedback->shop_id))->shop_name ?? '-' }}
                        </div>

                        <input type="hidden" name="shop_id" value="{{ $feedback->shop_id }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('manager.manager_name') }}</label>
                        <div class="form-control bg-light">
                            {{ optional($managers->firstWhere('manager_id', $feedback->manager_id))->manager_name ?? '-' }}
                        </div>

                        <input type="hidden" name="manager_id" value="{{ $feedback->manager_id }}">
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="photo" class="form-label">{{ __('feedback.photo') }}</label>
                            <input type="file" class="form-control @error('photo') is-invalid @enderror" id="picture" name="picture" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($feedback->photo)
                                <p class="text-sm mt-2"><img src="{{ asset('storage/' . $feedback->photo) }}" alt="{{ $feedback->title }} Icon" class="img-fluid" style="max-width: 50px;"></p>
                            @endif
                        </div>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $feedback->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('feedback.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('feedback.edit_feedback') }}</button>
                    <a href="{{ route('admin.feedback.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
