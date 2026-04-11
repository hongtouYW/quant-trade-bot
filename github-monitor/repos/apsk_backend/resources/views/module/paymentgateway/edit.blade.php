@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('paymentgateway.edit_paymentgateway'))
@section('header-title', __('paymentgateway.edit_paymentgateway'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('paymentgateway.edit_paymentgateway') }}: {{ $paymentgateway->payment_name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.paymentgateway.update', $paymentgateway->payment_id) }}" method="POST" class="p-4" enctype="multipart/form-data">
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
                        <label for="payment_name" class="form-label">{{ __('paymentgateway.payment_name') }}</label>
                        <input type="text" class="form-control @error('payment_name') is-invalid @enderror" id="payment_name" name="payment_name" value="{{ old('payment_name', $paymentgateway->payment_name) }}" required readonly>
                        @error('payment_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="icon" class="form-label">{{ __('paymentgateway.icon') }}</label>
                            <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                            <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                            @if ($paymentgateway->icon)
                                <p class="text-sm mt-2">Current icon: <img src="{{ asset('storage/' . $paymentgateway->icon) }}" alt="{{ $paymentgateway->payment_name }} Icon" class="img-fluid" style="max-width: 50px;"></p>
                            @endif
                            @error('icon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="amount_type" class="form-label">{{ __('paymentgateway.amount_type') }}</label>
                        <input type="text" class="form-control @error('amount_type') is-invalid @enderror" id="amount_type" name="amount_type" value="{{ old('amount_type', $paymentgateway->amount_type) }}" required>
                        @error('amount_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="min_amount" class="form-label">{{ __('paymentgateway.min_amount') }}</label>
                        <input type="number" class="form-control @error('min_amount') is-invalid @enderror" id="min_amount" name="min_amount" value="{{ old('min_amount', $paymentgateway->min_amount) }}" step="1.00">
                        @error('min_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="max_amount" class="form-label">{{ __('paymentgateway.max_amount') }}</label>
                        <input type="number" class="form-control @error('max_amount') is-invalid @enderror" id="max_amount" name="max_amount" value="{{ old('max_amount', $paymentgateway->max_amount) }}" step="1.00">
                        @error('max_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $paymentgateway->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('paymentgateway.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('paymentgateway.edit_paymentgateway') }}</button>
                    <a href="{{ route('admin.paymentgateway.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
