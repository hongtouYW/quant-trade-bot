@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('song.edit_song'))
@section('header-title', __('song.edit_song'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('song.edit_song') }}: {{ $song->song_name }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.song.update', $song->song_id) }}" method="POST" class="p-4">
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
                        <label for="song_name" class="form-label">{{ __('song.song_name') }}</label>
                        <input type="text" class="form-control @error('song_name') is-invalid @enderror" id="song_name" name="song_name" value="{{ old('song_name', $song->song_name) }}" required>
                        @error('song_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="album" class="form-label">{{ __('song.album') }}</label>
                        <input type="text" class="form-control @error('album') is-invalid @enderror" id="album" name="album" value="{{ old('album', $song->album) }}" required>
                        @error('album')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="published_on" class="form-label">{{ __('song.published_on') }}</label>
                        <input type="date" class="form-control @error('published_on') is-invalid @enderror" id="published_on" name="published_on" value="{{ old('published_on', $song->published_on) }}" required>
                        @error('published_on')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="artist_id" class="form-label">{{ __('artist.select') }}</label>
                        <select class="form-control @error('artist_id') is-invalid @enderror" id="artist_id" name="artist_id">
                            <option value="">{{ __('artist.select') }}</option>
                            @foreach ($artists as $artist)
                                <option value="{{ $artist->artist_id }}" {{ $song->artist_id == $artist->artist_id ? 'selected' : '' }}>
                                    {{ $artist->artist_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('artist_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="genre_id" class="form-label">{{ __('genre.select') }}</label>
                        <select class="form-control @error('genre_id') is-invalid @enderror" id="genre_id" name="genre_id">
                            <option value="">{{ __('genre.select') }}</option>
                            @foreach ($genres as $genre)
                                <option value="{{ $genre->genre_id }}" {{ $song->genre_id == $genre->genre_id ? 'selected' : '' }}>
                                    {{ $genre->genre_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('genre_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $song->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('song.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('song.edit_song') }}</button>
                    <a href="{{ route('admin.song.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
