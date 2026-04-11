@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('vip.add_new_vip'))
@section('header-title', __('vip.add_new_vip'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('vip.add_new_vip') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.vip.store') }}" method="POST" class="p-4" enctype="multipart/form-data">
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
                        <label for="vip_name" class="form-label">{{ __('vip.vip_name') }}</label>
                        <input type="text" class="form-control @error('vip_name') is-invalid @enderror" id="vip_name" name="vip_name" value="{{ old('vip_name') }}" required>
                        @error('vip_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="vip_desc" class="form-label">{{ __('vip.vip_desc') }}</label>
                        <textarea class="form-control @error('vip_desc') is-invalid @enderror" id="vip_desc" name="vip_desc" maxlength="1000" style="height: 100px;">{{ old('vip_desc') }}</textarea>
                        @error('vip_desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="lvl" class="form-label">{{ __('vip.lvl') }}</label>
                        <input
                            type="number"
                            class="form-control @error('lvl') is-invalid @enderror"
                            id="lvl"
                            name="lvl"
                            min="0"
                            step="1"
                            required
                        >
                        @error('lvl')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('vip.type') }}</label>
                        <input type="text" class="form-control @error('type') is-invalid @enderror" id="type" name="type" value="{{ old('type') }}" required>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="reward" class="form-label">{{ __('vip.reward') }}</label>
                        <input type="text" class="form-control @error('reward') is-invalid @enderror" id="reward" name="reward" value="{{ old('reward') }}" required>
                        @error('reward')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="icon" class="form-label">{{ __('vip.icon') }}</label>
                            <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="firstbonus" class="form-label">{{ __('vip.firstbonus') }}</label>
                        <input type="number" class="form-control @error('firstbonus') is-invalid @enderror" id="firstbonus" name="firstbonus" value="{{ old('firstbonus') }}" step="0.01">
                        @error('firstbonus')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="dailybonus" class="form-label">{{ __('vip.dailybonus') }}</label>
                        <input type="number" class="form-control @error('dailybonus') is-invalid @enderror" id="dailybonus" name="dailybonus" value="{{ old('dailybonus') }}" step="0.01">
                        @error('dailybonus')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="weeklybonus" class="form-label">{{ __('vip.weeklybonus') }}</label>
                        <input type="number" class="form-control @error('weeklybonus') is-invalid @enderror" id="weeklybonus" name="weeklybonus" value="{{ old('weeklybonus') }}" step="0.01">
                        @error('weeklybonus')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="monthlybonus" class="form-label">{{ __('vip.monthlybonus') }}</label>
                        <input type="number" class="form-control @error('monthlybonus') is-invalid @enderror" id="monthlybonus" name="monthlybonus" value="{{ old('monthlybonus') }}" step="0.01">
                        @error('monthlybonus')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="min_amount" class="form-label">{{ __('vip.min_amount') }}</label>
                        <input type="number" class="form-control @error('min_amount') is-invalid @enderror" id="min_amount" name="min_amount" value="{{ old('min_amount') }}" step="1.00">
                        @error('min_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="max_amount" class="form-label">{{ __('vip.max_amount') }}</label>
                        <input type="number" class="form-control @error('max_amount') is-invalid @enderror" id="max_amount" name="max_amount" value="{{ old('max_amount') }}" step="1.00">
                        @error('max_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.vip.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
