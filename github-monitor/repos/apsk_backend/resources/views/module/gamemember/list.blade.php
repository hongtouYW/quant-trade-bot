@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp
@section('title', __('module.gamemember_management'))
@section('header-title', __('module.gamemember_management'))
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
                    <h6>{{ __('gamemember.gamemember_list') }}</h6>
                    <!-- <a href="{{ route('admin.gamemember.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('gamemember.add_new_gamemember') }}</a> -->
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
                        <form action="{{ route('admin.gamemember.index') }}" method="GET">
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

                                {{-- Game Platform --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('gameplatform.select') }}
                                    </label>
                                    <select class="form-control select2 @error('gameplatform_id') is-invalid @enderror"
                                        id="gameplatform_id"
                                        name="gameplatform_id">
                                        <option value="">{{ __('gameplatform.select') }}</option>
                                        @foreach($gameplatforms as $platform)
                                            <option value="{{ $platform->gameplatform_id }}"
                                                @selected(request('gameplatform_id') == $platform->gameplatform_id)>
                                                {{ $platform->gameplatform_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Provider --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('provider.select') }}
                                    </label>
                                    <select class="form-control select2 @error('provider_id') is-invalid @enderror"
                                        id="provider_id"
                                        name="provider_id">
                                        <option value="">{{ __('provider.select') }}</option>
                                        @foreach($providers as $provider)
                                            <option value="{{ $provider->provider_id }}"
                                                @selected(request('provider_id') == $provider->provider_id)>
                                                {{ $provider->provider_name }}
                                            </option>
                                        @endforeach
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

                            </div>
                            <div class="row align-items-end">

                                {{-- Created On --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">{{ __('messages.created_on') }}</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="datetime-local"
                                                   name="created_from"
                                                   class="form-control"
                                                   value="{{ request('created_from') }}">
                                        </div>
                                        <div class="col">
                                            <input type="datetime-local"
                                                   name="created_to"
                                                   class="form-control"
                                                   value="{{ request('created_to') }}">
                                        </div>
                                    </div>
                                </div>

                                {{-- Apply --}}
                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                {{-- Clear --}}
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.gamemember.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamemember.gamemember_id') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.member_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.shop_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('game.platform') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.provider_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamemember.login') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamemember.name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamemember.balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.created_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.updated_on') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamemember.status') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($gamemembers as $gamemember)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->gamemember_id }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->member->member_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->shop->shop_name ?? '' }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->gameplatform->gameplatform_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->provider->provider_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->login }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($gamemember->balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->created_on }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamemember->updated_on }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($gamemember->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('gamemember.active') }}</span>
                                        @elseif ($gamemember->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('gamemember.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($gamemember->member->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('gamemember_management')
                                            && $gamemember->delete == 0
                                        )
                                            <!-- <a href="{{ route('admin.gamemember.edit', $gamemember->gamemember_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('gamemember.edit_gamemember') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a> -->
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('gamemember_management')
                                            && $gamemember->delete == 0
                                        )
                                            <!-- <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $gamemember->gamemember_id }}"
                                                    data-item-name="{{ $gamemember->name }}"
                                                    data-delete-route="{{ route('admin.gamemember.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('gamemember.delete_gamemember') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button> -->
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('gamemember.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $gamemembers->firstItem(), 'last' => $gamemembers->lastItem(), 'total' => $gamemembers->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $gamemembers->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
