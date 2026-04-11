@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.slider_management'))
@section('header-title', __('module.slider_management'))
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
                    <h6>{{ __('slider.slider_list') }}</h6>
                    <a href="{{ route('admin.slider.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('slider.add_new_slider') }}</a>
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
                        <form action="{{ route('admin.slider.index') }}" method="GET">
                            <div class="row align-items-end">

                                <div class="col-md-4 mb-3">
                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="{{ __('messages.search_placeholder') }}"
                                        value="{{ request('search') }}">
                                </div>

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

                                {{-- Language --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('messages.language') }}
                                    </label>
                                    <select name="language" class="form-control">
                                        @php
                                            $alllang = config('languages.supported');
                                        @endphp
                                        <option value="">{{ __('messages.language') ?? 'All' }}</option>
                                        @foreach ($alllang as $lang)
                                            <option value="{{$lang}}" 
                                                {{ request('language') === 'en' ? 'selected' : '' }}
                                            >
                                                {{ __('messages.'.$lang) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.user.index') }}" class="btn btn-secondary btn-block">
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('slider.title') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('slider.slider_desc') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.language') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('slider.status') }}</th>
                                @masteradmin
                                    <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('slider.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sliders as $slider)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $slider->title }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            {{ Str::words($slider->slider_desc, 15, '...') }}
                                        </p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __('messages.'.$slider->lang) }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($slider->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('slider.active') }}</span>
                                        @elseif ($slider->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('slider.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                        <td>
                                            <p class="text-center text-xs font-weight-bold mb-0">{{ $slider->agent->agent_name }}</p>
                                        </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('slider_management')
                                            && $slider->delete == 0
                                        )
                                            <a href="{{ route('admin.slider.edit', $slider->slider_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('slider.edit_slider') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('slider_management')
                                            && $slider->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $slider->slider_id }}"
                                                    data-item-name="{{ $slider->title }}"
                                                    data-delete-route="{{ route('admin.slider.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('slider.delete_slider') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif

                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('slider.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $sliders->firstItem(), 'last' => $sliders->lastItem(), 'total' => $sliders->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $sliders->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
