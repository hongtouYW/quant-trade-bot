@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.provider_management'))
@section('header-title', __('module.provider_management'))
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
                    <h6>{{ __('provider.provider_list') }}</h6>
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
                        <form action="{{ route('admin.provider.index') }}" method="GET">
                            <div class="row align-items-end">

                                {{-- Search --}}
                                <div class="col-md-2 mb-3">
                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="{{ __('messages.search_placeholder') }}"
                                        value="{{ request()->query('search') }}"
                                    >
                                </div>

                                {{-- Status --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('user.status') }}
                                    </label>
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

                                {{-- Provider Category --}}
                                <div class="col-md-2 mb-3">
                                    <label for="provider_category" class="form-label fw-bold">
                                        {{ __('provider.provider_category') }}
                                    </label>
                                    <select class="form-control select2 @error('provider_category') is-invalid @enderror"
                                        id="provider_category"
                                        name="provider_category">
                                        <option value="">{{ __('provider.provider_category') }}</option>
                                        @foreach ($provider_categorys as $provider_category)
                                            <option value="{{ $provider_category }}"
                                                {{ request()->filled('provider_category') && 
                                                    (string)request('provider_category') === (string)$provider_category ? 'selected' : '' }}>
                                                {{ __('provider.'.$provider_category) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('provider_category')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Apply --}}
                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                {{-- Clear --}}
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.provider.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.provider_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('game.platform') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.icon') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.icon_sm') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.banner') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.android') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.ios') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.download') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.provider_category') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.status') }}</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($providers as $provider)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $provider->provider_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($provider->Gameplatform)->gameplatform_name }}</p>
                                    </td>
                                    <td>
                                        @if ($provider->icon)
                                            <img src="{{ asset('storage/' . $provider->icon) }}"
                                                 alt="{{ $provider->provider_name }} Icon"
                                                 class="img-fluid cursor-pointer"
                                                 style="max-width: 100px; cursor:pointer;"
                                                 data-toggle="modal"
                                                 data-target="#imagePreviewModal"
                                                 data-image="{{ asset('storage/' . $provider->icon) }}"
                                                 loading="lazy">
                                        @endif
                                    </td>
                                    <td>
                                        @if ($provider->icon_sm)
                                            <img src="{{ asset('storage/' . $provider->icon_sm) }}"
                                                 alt="{{ $provider->provider_name }} Icon"
                                                 class="img-fluid cursor-pointer"
                                                 style="max-width: 100px; cursor:pointer;"
                                                 data-toggle="modal"
                                                 data-target="#imagePreviewModal"
                                                 data-image="{{ asset('storage/' . $provider->icon_sm) }}"
                                                 loading="lazy">
                                        @endif
                                    </td>
                                    <td>
                                        @if ($provider->banner)
                                            <img src="{{ asset('storage/' . $provider->banner) }}"
                                                 alt="{{ $provider->provider_name }} Icon"
                                                 class="img-fluid cursor-pointer"
                                                 style="max-width: 30px; cursor:pointer;"
                                                 data-toggle="modal"
                                                 data-target="#imagePreviewModal"
                                                 data-image="{{ asset('storage/' . $provider->banner) }}"
                                                 loading="lazy">
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $provider->android }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $provider->ios }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $provider->download }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __('provider.'.$provider->provider_category) }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($provider->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('provider.active') }}</span>
                                        @elseif ($provider->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('provider.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('provider_management')
                                            && $provider->delete == 0
                                        )
                                            <a href="{{ route('admin.provider.edit', $provider->provider_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('provider.edit_provider') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('provider_management')
                                            && $provider->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $provider->provider_id }}"
                                                    data-item-name="{{ $provider->provider_name }}"
                                                    data-delete-route="{{ route('admin.provider.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('provider.delete_provider') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('provider.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $providers->firstItem(), 'last' => $providers->lastItem(), 'total' => $providers->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $providers->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@include('components.modals.imagepreview')
@endsection
