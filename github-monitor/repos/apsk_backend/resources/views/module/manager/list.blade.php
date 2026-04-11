@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp
@section('title', __('module.manager_management'))
@section('header-title', __('module.manager_management'))
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
                    <h6>{{ __('manager.manager_list') }}</h6>
                    <a href="{{ route('admin.manager.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('manager.add_new_manager') }}</a>
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
                <form action="{{ route('admin.manager.index') }}" method="GET">
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="row align-items-end">

                                <div class="col-md-2 mb-3">
                                    <input type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="{{ __('messages.search_placeholder') }}"
                                        value="{{ request('search') }}">
                                </div>

                                {{-- State Code --}}
                                <div class="col-md-2 mb-3">
                                    <label for="state_code" class="form-label fw-bold">
                                        {{ __('state.select') }}
                                    </label>
                                    <select class="form-control select2 @error('state_code') is-invalid @enderror"
                                        id="state_code"
                                        name="state_code">
                                        <option value="">{{ __('state.select') }}</option>
                                        @foreach ($states as $state)
                                            <option value="{{ $state->state_code }}"
                                                {{ request()->filled('state_code') && 
                                                (string)request('state_code') === (string)$state->state_code ? 'selected' : '' }}>
                                                {{ $state->state_code }} - {{ $state->state_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('state_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Area Code --}}
                                <div class="col-md-2 mb-3">
                                    <label for="area_code" class="form-label fw-bold">
                                        {{ __('area.select') }}
                                    </label>
                                    <select class="form-control select2 @error('area_code') is-invalid @enderror"
                                        id="area_code"
                                        name="area_code">
                                        <option value="">{{ __('area.select') }}</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->area_code }}"
                                                {{ request()->filled('area_code') && 
                                                (string)request('area_code') === (string)$area->area_code ? 'selected' : '' }}>
                                                {{ $area->area_code }} - {{ $area->area_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('area_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                @masteradmin
                                {{-- Agent Name --}}
                                <div class="col-md-2 mb-3">
                                    <label for="agent_id" class="form-label fw-bold">
                                        {{ __('agent.select') }}
                                    </label>
                                    <select class="form-control select2 @error('agent_id') is-invalid @enderror"
                                        id="agent_id"
                                        name="agent_id">
                                        <option value="">{{ __('agent.select') }}</option>
                                        @foreach ($agents as $agent)
                                            <option value="{{ $agent->agent_id }}"
                                                {{ request()->filled('agent_id') && (int)request('agent_id') === (int)$agent->agent_id ? 'selected' : '' }}>
                                                {{ $agent->agent_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('agent_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endmasteradmin

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

                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="row align-items-end">

                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.manager.index') }}"
                                    class="btn btn-secondary btn-block">
                                        {{ __('messages.clear_filters') }}
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                </form>
                {{-- End Filtering and Search Form --}}

            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('manager.manager_id') }}</th>
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7 ps-2">{{ __('manager.manager_login') }}</th>
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7">{{ __('manager.manager_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('manager.phone') }}</th>
                                <th class="text-uppercase text-uppercase text-primary text-md font-weight-bolder opacity-7">{{ __('manager.balance') }}</th>
                                <th class="text-uppercase text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('manager.principal') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('state.state_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('area.area_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.created_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.updated_on') }}</th>
                                @masteradmin
                                    <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('manager.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('manager.last_login') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('manager.status') }}</th>
                                @if ( Auth::user()->user_role !== 'masteradmin' )
                                <th class="text-secondary opacity-7">{{ __('manager.clearmanager') }}</th>
                                @endif
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($managers as $manager)
                                <tr>
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm">{{ $manager->manager_id }}</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-primary text-md font-weight-bold mb-0">{{ $manager->manager_login }}</p>
                                    </td>
                                    <td>
                                        <p class="text-primary text-md font-weight-bold mb-0">{{ $manager->manager_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ formatPhone($manager->phone) }}</p>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.manager.show', $manager->manager_id) }}"
                                           class="text-primary text-md font-weight-bold mb-0">
                                            {{ number_format($manager->balance, 2) }}
                                        </a>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($manager->principal, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($manager->states)->state_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($manager->areas)->area_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $manager->created_on }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $manager->updated_on }}</p>
                                    </td>
                                    @masteradmin
                                        <td class="text-center">
                                            <p class="text-xs font-weight-bold mb-0">{{ optional($manager->agent)->agent_name ?? '-' }}</p>
                                        </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        <span class="text-secondary text-xs font-weight-bold">{{ $manager->lastlogin_on ? \Carbon\Carbon::parse($manager->lastlogin_on)->format('Y-m-d H:i') : __('manager.never') }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($manager->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('manager.active') }}</span>
                                        @elseif ($manager->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('manager.inactive') }}</span>
                                        @endif
                                    </td>
                                    @if ( Auth::user()->user_role !== 'masteradmin' )
                                        <td>
                                            @if ($manager->delete == 0 &&
                                                $manager->balance !== $manager->principal
                                            )
                                                <button type="button" class="btn btn-link text-secondary mb-0 p-0 clear-manager-btn"
                                                    data-toggle="modal" data-target="#clearManagerConfirmationModal"
                                                    data-manager-id="{{ $manager->manager_id }}"
                                                    data-manager-name="{{ $manager->manager_name }}"
                                                    data-clear-manager-route="{{ route('admin.manager.clear', '__ITEM_ID__') }}"
                                                    title="{{ __('manager.clearmanager') }}"
                                                    >
                                                    <i class="fas fa-money-bill"></i>
                                                </button>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('manager_management')
                                            && $manager->delete == 0
                                        )
                                            <a href="{{ route('admin.manager.edit', $manager->manager_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('manager.edit_manager') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('manager_management')
                                            && $manager->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $manager->manager_id }}"
                                                    data-item-name="{{ $manager->manager_name }}"
                                                    data-delete-route="{{ route('admin.manager.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('manager.delete_manager') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('manager.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $managers->firstItem(), 'last' => $managers->lastItem(), 'total' => $managers->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $managers->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.managerclear')
@include('components.modals.delete-confirmation')
@endsection
