@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.application_management'))
@section('header-title', __('module.application_management'))
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
                    <h6>{{ __('application.application_list') }}</h6>
                    <a href="{{ route('admin.application.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('application.add_new_application') }}</a>
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

                {{-- Filters --}}
                <div class="row mt-3">
                    <div class="col-12">
                        <form action="{{ route('admin.application.index') }}" method="GET">
                            <div class="row align-items-end">

                                <div class="col-md-2 mb-3">
                                    <input type="text"
                                           name="search"
                                           class="form-control"
                                           placeholder="{{ __('messages.search_placeholder') }}"
                                           value="{{ request('search') }}">
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

                                {{-- Types --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">{{ __('application.type') }}</label>
                                    <select name="type" class="form-control">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        @foreach($types as $type)
                                            <option value="{{ $type }}"
                                                @selected(request('type')==$type)>
                                                {{ __('application.'.$type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Platform --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">{{ __('application.platform') }}</label>
                                    <select name="platform" class="form-control">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        @foreach($platforms as $platform)
                                            <option value="{{ $platform }}"
                                                @selected(request('platform')==$platform)>
                                                {{ __('application.'.$platform) }}
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
                                    <a href="{{ route('admin.application.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.application_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.type') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.platform') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.version') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.latest_version') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.minimun_version') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.force_update') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.download_url') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.changelog') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.created_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.updated_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.is_use') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('application.status') }}</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($applications as $application)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $application->application_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __('application.'.$application->type) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __('application.'.$application->platform) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $application->version }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $application->latest_version }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $application->minimun_version }}</p>
                                    </td>
                                    <td>
                                        @if ($application->force_update == 1)
                                            <span class="text-success">✓</span>
                                        @else
                                            <span class="text-danger">✗</span>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $application->download_url }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            {{ Str::words($application->changelog, 15, '...') }}
                                        </p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $application->created_on }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $application->updated_on }}</p>
                                    </td>
                                    <td>
                                        @if ($application->is_use == 1)
                                            <span class="text-success">✓</span>
                                        @else
                                            <span class="text-danger">✗</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($application->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('application.active') }}</span>
                                        @elseif ($application->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('application.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if (canEdit('application_management') && $application->delete == 0)
                                            <a href="{{ route('admin.application.edit',  $application->application_id) }}" class="btn btn-link p-0">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        @if (canDelete('application_management') && $application->delete == 0)
                                            <button
                                                class="btn btn-link p-0 delete-btn"
                                                data-toggle="modal"
                                                data-target="#deleteConfirmationModal"
                                                data-item-id="{{  $application->application_id }}"
                                                data-item-name="{{ $application->application_name }}"
                                                data-delete-route="{{ route('admin.application.destroy', '__ITEM_ID__') }}"
                                            >
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('application.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $applications->firstItem(), 'last' => $applications->lastItem(), 'total' => $applications->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $applications->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
