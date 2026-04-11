@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('promotion.edit_promotion'))
@section('header-title', __('promotion.edit_promotion'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('promotion.edit_promotion') }}: {{ $promotion->edit_promotion }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.promotion.update', $promotion->promotion_id) }}" method="POST" class="p-4" enctype="multipart/form-data">
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
                        <label for="title" class="form-label">{{ __('promotion.title') }}</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $promotion->title) }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="promotion_desc" class="form-label">{{ __('promotion.promotion_desc') }}</label>
                        <textarea class="form-control @error('promotion_desc') is-invalid @enderror" id="promotion_desc" name="promotion_desc" maxlength="10000" style="height: 100px;">{{ old('promotion_desc', $promotion->promotion_desc) }}</textarea>
                        @error('promotion_desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="promotiontype_id" class="form-label">{{ __('promotion.type') }}</label>
                        <select class="form-control select2 @error('promotiontype_id') is-invalid @enderror" 
                            id="promotiontype_id" 
                            name="promotiontype_id" 
                            required>
                            <option value="">{{ __('promotion.selecttype') }}</option>
                            @foreach ($promotiontypes as $promotiontype)
                                <option value="{{ $promotiontype->promotiontype_id }}" {{ $promotion->promotiontype_id == $promotiontype->promotiontype_id ? 'selected' : '' }}>
                                    {{ __('promotion.'.$promotiontype->promotion_type) }} - {{ __('promotion.'.$promotiontype->event) }}
                                </option>
                            @endforeach
                        </select>
                        @error('promotiontype_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="photo" class="form-label">{{ __('promotion.photo') }}</label>
                            <input type="file" class="form-control @error('photo') is-invalid @enderror" id="photo" name="photo" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($promotion->photo)
                                <p class="text-sm mt-2"><img src="{{ asset('storage/' . $promotion->photo) }}" alt="{{ $promotion->title }}" class="img-fluid" style="max-width: 100px;"></p>
                            @endif
                            @error('photo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">{{ __('promotion.amount') }}</label>
                        <input type="number" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', $promotion->amount) }}" step="1.00">
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="percent" class="form-label">{{ __('promotion.percent') }}</label>
                        <input type="number" class="form-control @error('percent') is-invalid @enderror" id="percent" name="percent" value="{{ old('percent', $promotion->percent) }}" step="0.01">
                        @error('percent')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input type="hidden" name="newbie" value="0">
                        <input class="form-check-input" type="checkbox" id="newbie" name="newbie" value="1"
                            {{ old('newbie', $promotion->newbie) == 1 ? 'checked' : '' }}>
                        <label class="form-check-label" for="newbie">{{ __('promotion.newbie') }}</label>
                        @error('newbie')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="url" class="form-label">{{ __('promotion.url') }}</label>
                        <input type="text" class="form-control @error('url') is-invalid @enderror" id="url" name="url" value="{{ old('url', $promotion->url) }}" >
                        @error('url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="vip_id" class="form-label">{{ __('vip.select') }}</label>
                        <select class="form-control @error('vip_id') is-invalid @enderror" id="vip_id" name="vip_id">
                            <option value="">{{ __('vip.select') }}</option>
                            @foreach ($vips as $vip)
                                <option value="{{ $vip->vip_id }}" {{ $vip->vip_id == $promotion->vip_id ? 'selected' : '' }}>
                                    {{ $vip->vip_name }} - {{ __('vip.lvl') }} {{ $vip->lvl }}
                                </option>
                            @endforeach
                        </select>
                        @error('vip_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="language" class="form-label">{{ __('messages.selectlanguage') }}</label>
                        <select class="form-control @error('lang') is-invalid @enderror" id="language" name="language">
                            <option value="">{{ __('messages.selectlanguage') }}</option>
                            @foreach ($langs as $lang)
                                <option value="{{ $lang }}" {{ $promotion->lang == $lang ? 'selected' : '' }}>
                                    {{ __('messages.'.$lang) }}
                                </option>
                            @endforeach
                        </select>
                        @error('language')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $promotion->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('promotion.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('promotion.edit_promotion') }}</button>
                    <a href="{{ route('admin.promotion.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
