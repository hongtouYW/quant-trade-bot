@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.artist_management'))
@section('header-title', __('module.artist_management'))
@section('header-description')
    {{-- Use Auth::user() for session-based authentication --}}
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h6>{{ __('artist.artist_list') }}</h6>
                    <a href="{{ route('admin.artist.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('artist.add_new_artist') }}</a>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ session('error') }}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Filtering and Search Form --}}
                <div class="row mt-3">
                    <div class="col-12">
                        <form action="{{ route('admin.artist.index') }}" method="GET">
                            <div class="row align-items-end">

                                {{-- Search --}}
                                <div class="col-md-4 mb-3">
                                    <input type="text"
                                           name="search"
                                           class="form-control"
                                           placeholder="{{ __('messages.search_placeholder') }}"
                                           value="{{ request('search') }}">
                                </div>

                                {{-- Status --}}
                                <div class="col-md-3 mb-3">
                                    <select name="status" class="custom-select">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>
                                            {{ __('messages.active') }}
                                        </option>
                                        <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>
                                            {{ __('messages.inactive') }}
                                        </option>
                                    </select>
                                </div>

                                {{-- Delete --}}
                                <div class="col-md-3 mb-3">
                                    <select name="delete" class="custom-select">
                                        <option value="">{{ __('messages.all_delete') }}</option>
                                        <option value="0" {{ request('delete') == '0' ? 'selected' : '' }}>
                                            {{ __('messages.not_deleted') }}
                                        </option>
                                        <option value="1" {{ request('delete') == '1' ? 'selected' : '' }}>
                                            {{ __('messages.deleted') }}
                                        </option>
                                    </select>
                                </div>

                                {{-- Apply --}}
                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                {{-- Clear --}}
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.artist.index') }}"
                                       class="btn btn-secondary btn-block">
                                        {{ __('messages.clear_filters') }}
                                    </a>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
                {{-- End Filtering and Search Form --}}

            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('artist.artist_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('artist.artist_desc') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('genre.genre_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('country.country_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('artist.dob') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('artist.status') }}</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($artists as $artist)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $artist->artist_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            {{ Str::words($artist->artist_desc, 15, '...') }}
                                        </p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ GetGenreName($artist->genre_id) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ GetCountryName($artist->country_code) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $artist->dob }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($artist->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('artist.active') }}</span>
                                        @elseif ($artist->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('artist.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('artist_management')
                                            && $artist->delete == 0
                                        )
                                            <a href="{{ route('admin.artist.edit', $artist->artist_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('artist.edit_artist') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('artist_management')
                                            && $artist->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $artist->artist_id }}"
                                                    data-item-name="{{ $artist->artist_name }}"
                                                    data-delete-route="{{ route('admin.artist.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('artist.delete_artist') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">{{ __('artist.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $artists->firstItem(), 'last' => $artists->lastItem(), 'total' => $artists->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $artists->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
