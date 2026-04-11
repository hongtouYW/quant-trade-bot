@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('artist.edit_artist'))
@section('header-title', __('artist.edit_artist'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('artist.edit_artist') }}: {{ $artist->artist_name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.artist.update', $artist->artist_id) }}" method="POST" class="p-4">
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
                        <label for="artist_name" class="form-label">{{ __('artist.artist_name') }}</label>
                        <input type="text" class="form-control @error('artist_name') is-invalid @enderror" id="artist_name" name="artist_name" value="{{ old('artist_name', $artist->artist_name) }}" required>
                        @error('artist_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="artist_desc" class="form-label">{{ __('artist.artist_desc') }}</label>
                        <textarea class="form-control @error('artist_desc') is-invalid @enderror" id="artist_desc" name="artist_desc" maxlength="1000" style="height: 100px;">{{ old('artist_desc', $artist->artist_desc) }}</textarea>
                        @error('artist_desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="genre_id" class="form-label">{{ __('genre.select') }}</label>
                        <select class="form-control @error('genre_id') is-invalid @enderror" id="genre_id" name="genre_id">
                            <option value="">{{ __('genre.select') }}</option>
                            @foreach ($genres as $genre)
                                <option value="{{ $genre->genre_id }}" {{ $artist->genre_id == $genre->genre_id ? 'selected' : '' }}>
                                    {{ $genre->genre_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('genre_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="country_code" class="form-label">{{ __('country.select') }}</label>
                        <select class="form-control @error('country_code') is-invalid @enderror" id="country_code" name="country_code">
                            <option value="">{{ __('artist.select') }}</option>
                            @foreach ($countries as $country)
                                <option value="{{ $artist->country_code }}" {{ $country->country_code == $artist->country_code ? 'selected' : '' }}>
                                    {{ $country->country_code }}
                                </option>
                            @endforeach
                        </select>
                        @error('country_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="dob" class="form-label">{{ __('artist.dob') }}</label>
                        <input type="date" class="form-control @error('dob') is-invalid @enderror" id="dob" name="dob" value="{{ old('dob', $artist->dob) }}" required>
                        @error('dob')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $artist->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('artist.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('artist.edit_artist') }}</button>
                    <a href="{{ route('admin.artist.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
