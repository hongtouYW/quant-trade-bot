@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.role_management'))
@section('header-title', __('module.role_management'))
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
                    <h6>{{ __('role.role_list') }}</h6>
                    <a href="{{ route('admin.role.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('role.add_new_role') }}</a>
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
                        <form action="{{ route('admin.role.index') }}" method="GET">
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

                                {{-- Apply --}}
                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                {{-- Clear --}}
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.role.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('role.role_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('role.role_desc') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('role.status') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7"></th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($roles as $role)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $role->role_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            {{ Str::words($role->role_desc, 15, '...') }}
                                        </p>
                                    </td>
                                    <td class="text-center">
                                        @if ($role->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('role.active') }}</span>
                                        @elseif ($role->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('role.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($role->delete == 0 
                                            && ($role->role_name !== 'superadmin'
                                            && $role->role_name !== 'masteradmin')
                                        )
                                            <a href="{{ route('admin.access.edit', $role->role_name) }}" class="btn btn-link text-info text-secondary mb-0 p-0 text-xs font-weight-bold mb-0"
                                                data-toggle="tooltip" data-original-title="{{ __('role.edit_access') }}">
                                                {{ __('role.edit_access') }}
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('role_management')
                                            && $role->delete == 0
                                            && ($role->role_name !== 'superadmin'
                                            && $role->role_name !== 'masteradmin')
                                        )
                                            <a href="{{ route('admin.role.edit', $role->role_name) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('role.edit_role') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canEdit('role_management')
                                            && $role->delete == 0
                                            && ($role->role_name !== 'superadmin'
                                            && $role->role_name !== 'masteradmin')
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $role->role_name }}"
                                                    data-item-name="{{ $role->role_name }}"
                                                    data-delete-route="{{ route('admin.role.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('role.delete_role') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('role.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $roles->firstItem(), 'last' => $roles->lastItem(), 'total' => $roles->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $roles->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
