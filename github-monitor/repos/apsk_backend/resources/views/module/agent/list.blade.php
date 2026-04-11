@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.agent_management'))
@section('header-title', __('module.agent_management'))
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
                    <h6>{{ __('agent.agent_list') }}</h6>
                    <a href="{{ route('admin.agent.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('agent.add_new_agent') }}</a>
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
                {{-- Filters --}}
                <form action="{{ route('admin.agent.index') }}" method="GET">
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="row align-items-end">

                                {{-- Search --}}
                                <div class="col-md-2 mb-3">
                                    <input type="text"
                                            name="search"
                                            class="form-control"
                                            placeholder="{{ __('messages.search_placeholder') }}"
                                            value="{{ request('search') }}">
                                </div>

                                {{-- Countries --}}
                                <div class="col-md-2 mb-3">
                                    <label for="country_code" class="form-label fw-bold">
                                        {{ __('country.select') }}
                                    </label>
                                    <select class="form-control select2 @error('country_code') is-invalid @enderror"
                                        id="country_code"
                                        name="country_code">
                                        <option value="">{{ __('country.select') }}</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->country_code }}"
                                                {{ request()->filled('country_code') && 
                                                    (string)request('country_code') === (string)$country->country_code ? 'selected' : '' }}>
                                                {{ $country->country_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('country_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- States --}}
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
                                                {{ $state->state_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('state_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                                    <a href="{{ route('admin.agent.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.agent_id') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.agent_code') }}</th>
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7">{{ __('agent.agent_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.icon') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('country.country_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('state.state_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.chataccountcreate') }}</th>
                                <th class="text-uppercase text-uppercase text-primary text-md font-weight-bolder opacity-7">{{ __('agent.balance') }}</th>
                                <th class="text-uppercase text-uppercase text-primary text-md font-weight-bolder opacity-7">{{ __('agent.principal') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.created_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.updated_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.referral_link') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.status') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7"></th>
                                <th class="text-secondary opacity-7">{{ __('agent.clearagent') }}</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($agents as $agent)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $agent->agent_id }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $agent->agent_code }}</p>
                                    </td>
                                    <td>
                                        <p class="text-md text-primary font-weight-bold mb-0">{{ $agent->agent_name }}</p>
                                    </td>
                                    <td>
                                        @if ($agent->icon)
                                            <img src="{{ asset('storage/' . $agent->icon) }}"
                                                 alt="{{ $agent->agent_name }}"
                                                 class="img-fluid cursor-pointer"
                                                 style="max-width: 100px; cursor:pointer;"
                                                 data-toggle="modal"
                                                 data-target="#imagePreviewModal"
                                                 data-image="{{ asset('storage/' . $agent->icon) }}">
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($agent->countries)->country_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($agent->states)->state_name }}</p>
                                    </td>
                                    <td>
                                        @if ($agent->isChatAccountCreate == 1)
                                            <span class="text-success">✓</span>
                                        @else
                                            <span class="text-danger">✗</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.agent.show', $agent->agent_id) }}"
                                           class="text-primary text-md font-weight-bold mb-0">
                                            {{ number_format($agent->balance, 2) }}
                                        </a>
                                    </td>
                                    <td>
                                        <p class="text-primary text-md font-weight-bold mb-0">{{ number_format($agent->principal, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $agent->created_on }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $agent->updated_on }}</p>
                                    </td>
                                    <td>
                                        @if ($agent->delete == 0)
                                            <button type="button" class="btn btn-link text-secondary mb-0 p-0 agent-link-btn"
                                                    data-toggle="modal" data-target="#AgentUrlModal"
                                                    data-item-id="{{ $agent->agent_id }}"
                                                    data-item-name="{{ $agent->title }}"
                                                    title="{{ __('agent.referral_link') }}"
                                                    >
                                                <i class="fas fa-link text-success"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($agent->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('agent.active') }}</span>
                                        @elseif ($agent->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('agent.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($agent->delete == 0 && 
                                            $agent->status == 1 && 
                                            $agent->agent_id != 0
                                        )
                                            <a href="{{ route('admin.gameplatformaccess.edit', $agent->agent_id) }}" 
                                                class="btn btn-link text-info text-secondary mb-0 p-0 text-xs font-weight-bold mb-0"
                                                data-toggle="tooltip" 
                                                data-original-title="{{ __('gameplatformaccess.edit_gameplatformaccess') }}"
                                                title="{{ __('gameplatformaccess.edit_gameplatformaccess') }}">
                                                {{ __('gameplatformaccess.title') }}
                                            </a>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($agent->delete == 0 &&
                                            $agent->balance !== $agent->principal && 
                                            $agent->agent_id != 0
                                        )
                                            <button type="button" class="btn btn-link text-secondary mb-0 p-0 clear-agent-btn"
                                                data-toggle="modal" data-target="#clearAgentConfirmationModal"
                                                data-agent-id="{{ $agent->agent_id }}"
                                                data-agent-name="{{ $agent->agent_name }}"
                                                data-clear-agent-route="{{ route('admin.agent.clear', '__ITEM_ID__') }}"
                                                title="{{ __('agent.clearagent') }}"
                                                >
                                                <i class="fas fa-money-bill"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('agent_management')
                                            && $agent->delete == 0
                                            && $agent->agent_id != 0
                                        )
                                            <a href="{{ route('admin.agent.edit', $agent->agent_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               title="{{ __('agent.edit_agent') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('agent_management')
                                            && $agent->delete == 0
                                            && $agent->agent_id !== 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $agent->agent_id }}"
                                                    data-item-name="{{ $agent->title }}"
                                                    data-delete-route="{{ route('admin.agent.destroy', '__ITEM_ID__') }}"
                                                    title="{{ __('agent.delete_agent') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('agent.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $agents->firstItem(), 'last' => $agents->lastItem(), 'total' => $agents->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $agents->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.agentclear')
@include('components.modals.delete-confirmation')
@include('components.modals.agent-url')
@include('components.modals.imagepreview')
@endsection
