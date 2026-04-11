@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('commissionrank.edit_commissionrank'))
@section('header-title', __('commissionrank.edit_commissionrank'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('commissionrank.edit_commissionrank') }}: {{ $commissionrank->commissionrank_id }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.commissionrank.update', $commissionrank->commissionrank_id) }}" method="POST" class="p-4">
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
                        <label for="rank" class="form-label">{{ __('commissionrank.rank') }}</label>
                        <input type="number"
                            class="form-control @error('rank') is-invalid @enderror"
                            id="rank"
                            name="rank"
                            value="{{ old('rank', $commissionrank->rank) }}"
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
                            value="{{ old('amount', $commissionrank->amount) }}" 
                            min="0.00" 
                            step="0.01" 
                            required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $commissionrank->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('commissionrank.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('commissionrank.edit_commissionrank') }}</button>
                    <a href="{{ route('admin.commissionrank.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
