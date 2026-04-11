@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('commissionrank.add_new_commissionrank'))
@section('header-title', __('commissionrank.add_new_commissionrank'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('commissionrank.add_new_commissionrank') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.commissionrank.store') }}" method="POST" class="p-4">
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
                        <label for="rank" class="form-label">{{ __('commissionrank.rank') }}</label>
                        <input type="number"
                            class="form-control @error('rank') is-invalid @enderror"
                            id="rank"
                            name="rank"
                            value="{{ old('rank') }}"
                            min="0"
                            step="1"
                            oninput="this.value = this.value.replace(/[^0-9]/g,'')"
                            required>
                        @error('rank')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">{{ __('commissionrank.amount') }}</label>
                        <input type="number" 
                            class="form-control @error('amount') is-invalid @enderror" 
                            id="amount" 
                            name="amount" 
                            value="{{ old('amount') }}" 
                            min="0.00" 
                            step="0.01" 
                            required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('messages.add') }}</button>
                    <a href="{{ route('admin.commissionrank.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
