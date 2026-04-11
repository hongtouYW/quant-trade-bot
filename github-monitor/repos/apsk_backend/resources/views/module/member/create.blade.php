@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('member.add_new_member'))
@section('header-title', __('member.add_new_member'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('member.add_new_member') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.member.store') }}" method="POST" class="p-4">
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
                        <label for="member_login" class="form-label">{{ __('member.member_login') }}</label>
                        <input type="text" class="form-control @error('member_login') is-invalid @enderror" id="member_login" name="member_login" value="{{ old('member_login') }}" required>
                        @error('member_login')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="member_pass" class="form-label">{{ __('member.member_password') }}</label>
                        <input type="password" class="form-control @error('member_pass') is-invalid @enderror" id="member_pass" name="member_pass" required>
                        @error('member_pass')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="member_name" class="form-label">{{ __('member.member_name') }}</label>
                        <input type="text" class="form-control @error('member_name') is-invalid @enderror" id="member_name" name="member_name" value="{{ old('member_name') }}" required>
                        @error('member_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">{{ __('member.full_name') }}</label>
                        <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name') }}">
                        @error('full_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('member.phone') }}</label>
                        <div class="input-group">
                            <select class="form-control" name="phone_country" style="max-width: 120px;">
                                <option value="+{{ \Prefix::phonecode() }}" {{ old('phone_country') == '+'.\Prefix::phonecode() ? 'selected' : '' }}>+{{ \Prefix::phonecode() }}</option>
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
                        <label for="balance" class="form-label">{{ __('member.balance') }}</label>
                        <input type="number" class="form-control @error('balance') is-invalid @enderror" id="balance" name="balance" min="0.00" step="0.01">
                        @error('balance')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="shop_id" class="form-label">{{ __('shop.select') }}</label>
                            <select class="form-control select2 @error('shop_id') is-invalid @enderror" 
                                id="shop_id" 
                                name="shop_id">
                            <option value="">{{ __('shop.select') }}</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->shop_id }}" {{ old('shop_id') == $shop->shop_id ? 'selected' : '' }}>
                                    {{ $shop->shop_name }} ({{ $shop->shop_id }})
                                </option>
                            @endforeach
                        </select>
                        @error('shop_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">{{ __('member.email') }}</label>
                        <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="wechat" class="form-label">{{ __('member.wechat') }}</label>
                        <input type="text" class="form-control @error('wechat') is-invalid @enderror" id="wechat" name="wechat" value="{{ old('wechat') }}">
                        @error('wechat')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="whatsapp" class="form-label">{{ __('member.whatsapp') }}</label>
                        <input type="text" class="form-control @error('whatsapp') is-invalid @enderror" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}">
                        @error('whatsapp')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="facebook" class="form-label">{{ __('member.facebook') }}</label>
                        <input type="text" class="form-control @error('facebook') is-invalid @enderror" id="facebook" name="facebook" value="{{ old('facebook') }}">
                        @error('facebook')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="telegram" class="form-label">{{ __('member.telegram') }}</label>
                        <input type="text" class="form-control @error('telegram') is-invalid @enderror" id="telegram" name="telegram" value="{{ old('telegram') }}">
                        @error('telegram')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.member.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
