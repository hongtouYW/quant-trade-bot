@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Str;
@endphp

@section('title', __('module.game_management'))
@section('header-title', __('module.game_management'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">

            {{-- Card Header --}}
            <div class="card-header pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h6>{{ __('game.game_list') }}</h6>
                    @masteradmin
                        <!-- <a href="{{ route('admin.game.create') }}" class="btn btn-primary btn-sm ms-auto">
                            {{ __('game.add_new_game') }}
                        </a> -->
                    @endmasteradmin
                </div>

                {{-- Alerts --}}
                <div class="row">
                    <div class="col-md-12">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show">
                                {{ session('error') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Filters --}}
                <div class="row mt-3">
                    <div class="col-12">
                        <form action="{{ route('admin.game.index') }}" method="GET">
                            <div class="row align-items-end">

                                <div class="col-md-2 mb-3">
                                    <input
                                        type="text"
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
                                    <label for="gameplatform_id" class="form-label fw-bold">
                                        {{ __('gameplatform.select') }}
                                    </label>
                                    <select class="form-control select2 @error('gameplatform_id') is-invalid @enderror"
                                        id="gameplatform_id"
                                        name="gameplatform_id">
                                        <option value="">{{ __('gameplatform.select') }}</option>
                                        @foreach ($gameplatforms as $gameplatform)
                                            <option value="{{ $gameplatform->gameplatform_id }}"
                                            {{ request('gameplatform_id') == $gameplatform->gameplatform_id ? 'selected' : '' }}
                                                >
                                                {{ $gameplatform->gameplatform_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('gameplatform_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Provider --}}
                                <div class="col-md-2 mb-3">
                                    <label for="provider_id" class="form-label fw-bold">
                                        {{ __('provider.select') }}
                                    </label>
                                    <select class="form-control select2 @error('provider_id') is-invalid @enderror"
                                        id="provider_id"
                                        name="provider_id">
                                        <option value="">{{ __('provider.select') }}</option>
                                        @foreach ($providers as $provider)
                                            <option value="{{ $provider->provider_id }}"
                                            {{ request('provider_id') == $provider->provider_id ? 'selected' : '' }}
                                            >
                                                {{ $provider->provider_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('provider_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Type --}}
                                <div class="col-md-2 mb-3">
                                    <label for="gametype_id" class="form-label fw-bold">
                                        {{ __('gametype.select') }}
                                    </label>
                                    <select class="form-control select2 @error('gametype_id') is-invalid @enderror"
                                        id="gametype_id"
                                        name="gametype_id">
                                        <option value="">{{ __('gametype.select') }}</option>
                                        @foreach ($gametypes as $gametype)
                                            <option value="{{ $gametype->gametype_id }}"
                                            {{ 
                                                (string) request('gametype_id') === (string) $gametype->gametype_id ? 
                                                'selected' : 
                                                '' 
                                            }}
                                            >
                                                {{ __('gametype.'.$gametype->type_name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('gametype_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.game.index') }}" class="btn btn-secondary btn-block">
                                        {{ __('messages.clear_filters') }}
                                    </a>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                        <tr>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('game.game_name') }}
                            </th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('game.game_desc') }}
                            </th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('game.platform') }}
                            </th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('provider.provider_name') }}
                            </th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('game.icon') }}
                            </th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('game.icon_zh') }}
                            </th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('game.api') }}
                            </th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('gametype.type_name') }}
                            </th>
                            <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">
                                {{ __('game.status') }}
                            </th>
                            @masteradmin
                            <th class="text-secondary opacity-7"></th>
                            @endmasteradmin
                        </tr>
                        </thead>

                        <tbody>
                        @forelse ($games as $game)
                            <tr>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">{{ $game->game_name }}</p>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width:200px">
                                        {{ Str::words($game->game_desc, 15, '...') }}
                                    </p>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">
                                        {{ optional($game->Gameplatform)->gameplatform_name }}
                                    </p>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">
                                        {{ optional($game->Provider)->provider_name }}
                                    </p>
                                </td>
                                <td>
                                    @if ($game->icon)
                                        <img src="{{ asset('storage/' . $game->icon) }}"
                                                alt="{{ $game->game_name }}"
                                                class="img-fluid cursor-pointer"
                                                style="max-width: 100px; cursor:pointer;"
                                                data-toggle="modal"
                                                data-target="#imagePreviewModal"
                                                data-image="{{ asset('storage/' . $game->icon) }}"
                                                loading="lazy">
                                    @endif
                                </td>
                                <td>
                                    @if ($game->icon_zh)
                                        <img src="{{ asset('storage/' . $game->icon_zh) }}"
                                                alt="{{ $game->game_name }}"
                                                class="img-fluid cursor-pointer"
                                                style="max-width: 100px; cursor:pointer;"
                                                data-toggle="modal"
                                                data-target="#imagePreviewModal"
                                                data-image="{{ asset('storage/' . $game->icon_zh) }}"
                                                loading="lazy">
                                    @endif
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">{{ $game->api }}</p>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">
                                        {{ __('gametype.'.$game->gameType->type_name) }}
                                    </p>
                                </td>
                                <td class="text-center">
                                    @if ($game->status == 1)
                                        <span class="badge badge-sm bg-gradient-success">
                                            {{ __('game.active') }}
                                        </span>
                                    @elseif ($game->delete == 1)
                                        <span class="badge badge-sm bg-gradient-danger">
                                            {{ __('messages.delete') }}
                                        </span>
                                    @else
                                        <span class="badge badge-sm bg-gradient-secondary">
                                            {{ __('game.inactive') }}
                                        </span>
                                    @endif
                                </td>
                                @masteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('game_management')
                                            && $game->delete == 0
                                        )
                                            <a href="{{ route('admin.game.edit', $game->game_id) }}"
                                                class="btn btn-link text-secondary mb-0 p-0"
                                                data-toggle="tooltip"
                                                data-original-title="{{ __('game.edit_game') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('game_management')
                                            && $game->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $game->game_id }}"
                                                    data-item-name="{{ $game->game_name }}"
                                                    data-delete-route="{{ route('admin.game.destroy', ['game' => '__ITEM_ID__']) }}"
                                                    data-original-title="{{ __('game.delete_game') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                @endmasteradmin
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100" class="text-center">
                                    {{ __('game.no_data_found') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', [
                            'first' => $games->firstItem(),
                            'last' => $games->lastItem(),
                            'total' => $games->total()
                        ]) }}
                    </div>
                    {{ $games->links('vendor.pagination.custom') }}
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@include('components.modals.imagepreview')
@endsection
