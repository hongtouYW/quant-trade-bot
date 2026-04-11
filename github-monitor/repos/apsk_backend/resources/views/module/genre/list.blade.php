@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp
@section('title', __('module.genre_management'))
@section('header-title', __('module.genre_management'))
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
                    <h6>{{ __('genre.genre_list') }}</h6>
                    <a href="{{ route('admin.genre.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('genre.add_new_genre') }}</a>
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
                    <div class="col-md-12">
                        <form action="{{ route('admin.genre.index') }}" method="GET" class="form-inline">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="search" class="form-label visually-hidden">{{ __('messages.search') }}</label>
                                    <input type="text" name="search" id="search" class="form-control" placeholder="{{ __('messages.search_placeholder') }}" value="{{ request('search') }}">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="status" class="form-label visually-hidden">{{ __('messages.active_status') }}</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                                        <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="delete" class="form-label visually-hidden">{{ __('messages.deleted_status') }}</label>
                                    <select name="delete" id="delete" class="form-select">
                                        <option value="">{{ __('messages.all_delete') }}</option>
                                        <option value="0" {{ request('delete') == '0' ? 'selected' : '' }}>{{ __('messages.not_deleted') }}</option>
                                        <option value="1" {{ request('delete') == '1' ? 'selected' : '' }}>{{ __('messages.deleted') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <!-- <div class="col-md-4 mb-3">
                                    <label for="created_on_from" class="form-label">{{ __('messages.from_date') }}</label>
                                    <input type="date" name="created_on_from" id="created_on_from" class="form-control" value="{{ request('created_on_from') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="created_on_to" class="form-label">{{ __('messages.to_date') }}</label>
                                    <input type="date" name="created_on_to" id="created_on_to" class="form-control" value="{{ request('created_on_to') }}">
                                </div> -->
                                <div class="col-md-4 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-info me-2">{{ __('messages.apply_filters') }}</button>
                                    <a href="{{ route('admin.genre.index') }}" class="btn btn-secondary">{{ __('messages.clear_filters') }}</a>
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('genre.genre_name') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('genre.status') }}</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($genres as $genre)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $genre->genre_name }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($genre->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('genre.active') }}</span>
                                        @elseif ($genre->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('genre.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($genre->delete == 0)
                                            <a href="{{ route('admin.genre.edit', $genre->genre_name) }}" class="btn btn-link text-secondary mb-0 p-0" data-toggle="tooltip" data-original-title="{{ __('genre.edit_genre') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                            <button type="button" class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $genre->genre_name }}"
                                                    data-item-name="{{ $genre->genre_name }}"
                                                    data-delete-route="{{ route('admin.genre.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('genre.delete_genre') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center">{{ __('genre.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $genres->firstItem(), 'last' => $genres->lastItem(), 'total' => $genres->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $genres->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
