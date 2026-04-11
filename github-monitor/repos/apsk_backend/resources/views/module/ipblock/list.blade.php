@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.ipblock_management'))
@section('header-title', __('module.ipblock_management'))
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
                    <h6>{{ __('ipblock.ipblock_list') }}</h6>
                    <a href="{{ route('admin.ipblock.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('ipblock.add_new_ipblock') }}</a>
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
                        <form action="{{ route('admin.ipblock.index') }}" method="GET">
                            <div class="row align-items-end">

                                {{-- Search --}}
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

                                {{-- Apply --}}
                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                {{-- Clear --}}
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.ipblock.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('ipblock.ip') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('ipblock.reason') }}</th>
                                @masteradmin
                                    <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('ipblock.status') }}</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ipblocks as $ipblock)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $ipblock->ip }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate"
                                            title="{{$ipblock->reason}}">
                                            {{ Str::words($ipblock->reason, 15, '...') }}
                                        </p>
                                    </td>
                                    @masteradmin
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">
                                            {{ optional($ipblock->Agent)->agent_name }}
                                        </p>
                                    </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        @if ($ipblock->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('ipblock.active') }}</span>
                                        @elseif ($ipblock->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('ipblock.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($ipblock->delete == 0)
                                            <a href="{{ route('admin.ipblock.edit', $ipblock->ipblock_id) }}" class="btn btn-link text-secondary mb-0 p-0" data-toggle="tooltip" data-original-title="{{ __('ipblock.edit_ipblock') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                            <button type="button" class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $ipblock->ipblock_id }}"
                                                    data-item-name="{{ $ipblock->ip }}"
                                                    data-delete-route="{{ route('admin.ipblock.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('ipblock.delete_ipblock') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('ipblock.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $ipblocks->firstItem(), 'last' => $ipblocks->lastItem(), 'total' => $ipblocks->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $ipblocks->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
