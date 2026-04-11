@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('credit.add_new_credit'))
@section('header-title', __('credit.add_new_credit'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('credit.add_new_credit') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.credit.store') }}" method="POST" class="p-4">
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
                        <label for="payment_id" class="form-label">{{ __('paymentgateway.select') }}</label>
                        <select class="form-control @error('payment_id') is-invalid @enderror" id="payment_id" name="payment_id">
                            <option value="">{{ __('paymentgateway.select') }}</option>
                            @foreach ($paymentgateways as $paymentgateway)
                                <option value="{{ $paymentgateway->payment_id }}" {{ old('payment_id') == $paymentgateway->payment_id ? 'selected' : '' }}>
                                    {{ __( 'credit.'.$paymentgateway->payment_name ) }}
                                </option>
                            @endforeach
                        </select>
                        @error('member_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="member_id" class="form-label">{{ __('member.select') }}</label>
                        <select class="form-control @error('member_id') is-invalid @enderror" id="member_id" name="member_id">
                            <option value="">{{ __('member.select') }}</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->member_id }}" {{ old('member_id') == $member->member_id ? 'selected' : '' }}>
                                    {{ $member->member_id }}
                                </option>
                            @endforeach
                        </select>
                        @error('member_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('credit.select_type') }}</label>
                        <select class="form-control @error('type') is-invalid @enderror" id="type" name="type">
                            <option value="">{{ __('credit.select_type') }}</option>
                            @php
                                $types = ['deposit','withdraw'];
                            @endphp
                            @foreach ($types as $type)
                                <option value="{{ $type }}" {{ old('type') == $type ? 'selected' : '' }}>
                                    {{ __('credit.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">{{ __('messages.amount') }}</label>
                        <input type="number" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" min="0.00" step="1.00" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="submit_on" class="form-label">{{ __('credit.submit_on') }}</label>
                        <input type="date" class="form-control @error('submit_on') is-invalid @enderror" id="submit_on" name="submit_on" value="{{ old('submit_on') }}" required>
                        @error('submit_on')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.credit.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
