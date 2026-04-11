@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('credit.edit_credit'))
@section('header-title', __('credit.edit_credit'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('credit.edit_credit') }}: {{ $credit->credit_id }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.credit.update', $credit->credit_id) }}" method="POST" class="p-4">
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
                        <label class="form-label">{{ __('member.member_id') }}</label>
                        <input type="text" class="form-control" value="{{ optional($credit->Member)->prefix }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('member.select') }}</label>
                        <input type="text" class="form-control" value="{{ optional($credit->Member)->member_name }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('paymentgateway.select') }}</label>
                        <input type="text" class="form-control" value="{{ __( 'credit.'.optional($credit->Paymentgateway)->payment_name ) }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('bank.select') }}</label>
                        <input type="text" class="form-control" value="{{ optional($credit->Bankaccount?->Bank)->bank_name }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('member.bank_account') }}</label>
                        <input type="text" class="form-control" value="{{ optional($credit->Bankaccount)->bank_account }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('member.bank_full_name') }}</label>
                        <input type="text" class="form-control" value="{{ optional($credit->Bankaccount)->bank_full_name }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('credit.select_type') }}</label>
                        <input type="text" class="form-control" value="{{ __('credit.'.$credit->type) }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.amount') }}</label>
                        <input type="number" class="form-control" value="{{ $credit->amount }}" readonly>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">{{ __('credit.status') }}</label>
                        <select class="form-control @error('status') is-invalid @enderror" id="status" name="status">
                            <option value="">{{ __('credit.select') }}</option>
                            <option value="-1" {{ $credit->status == -1 ? 'selected' : '' }}>
                                {{ __('credit.inactive') }}
                            </option>
                            <option value="0" {{ $credit->status == 0 ? 'selected' : '' }}>
                                {{ __('credit.pending') }}
                            </option>
                            <option value="1" {{ $credit->status == 1 ? 'selected' : '' }}>
                                {{ __('credit.approve') }}
                            </option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">{{ __('credit.reason') }}</label>
                        <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" style="height: 100px;"></textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('credit.edit_credit') }}</button>
                    <a href="{{ route('admin.credit.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
