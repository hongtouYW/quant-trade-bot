@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.gamelog_management'))
@section('header-title', __('module.gamelog_management'))
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
                    <h6>{{ __('gamelog.game_list') }}</h6>
                    @masteradmin
                    <!-- <a href="javascript:void(0)"
                            class="btn btn-primary btn-sm ms-auto gamelog-btn"
                            data-toggle="modal"
                            data-target="#gamelogModal">
                        {{ __('gamelog.sync') }}
                        <i class="fas fa-file-excel"></i>
                    </a> -->
                    @endmasteradmin
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
                        <form action="{{ route('admin.gamelog.index') }}" method="GET">
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
{{--                                <div class="col-md-2 mb-3">--}}
{{--                                    <label class="form-label fw-bold">{{ __('gamelog.status') }}</label>--}}
{{--                                    <select name="status" class="custom-select">--}}
{{--                                        <option value="">{{ __('messages.all_status') }}</option>--}}
{{--                                        <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>--}}
{{--                                            {{ __('messages.active') }}--}}
{{--                                        </option>--}}
{{--                                        <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>--}}
{{--                                            {{ __('messages.inactive') }}--}}
{{--                                        </option>--}}
{{--                                    </select>--}}
{{--                                </div>--}}

                                {{-- Provider --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">{{ __('provider.provider_name') }}</label>
                                    <select name="provider_id" class="form-control">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        @foreach($providers as $provider)
                                            <option value="{{ $provider->provider_id }}" @selected(request('provider_id')==$provider->provider_id)>
                                                {{ $provider->provider_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Platform --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">{{ __('game.platform') }}</label>
                                    <select name="platform_id" class="form-control">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        @foreach($platforms as $platform)
                                            <option value="{{ $platform->gameplatform_id }}" @selected(request('platform_id')==$platform->gameplatform_id)>
                                                {{ $platform->gameplatform_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Start datetime --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">{{ __('gamelog.startdt') }}</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="datetime-local" name="start_from" class="form-control" value="{{ request('start_from') }}">
                                        </div>
                                        <div class="col">
                                            <input type="datetime-local" name="start_to" class="form-control" value="{{ request('start_to') }}">
                                        </div>
                                    </div>
                                </div>

                                {{-- End datetime --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">{{ __('gamelog.enddt') }}</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="datetime-local" name="end_from" class="form-control" value="{{ request('end_from') }}">
                                        </div>
                                        <div class="col">
                                            <input type="datetime-local" name="end_to" class="form-control" value="{{ request('end_to') }}">
                                        </div>
                                    </div>
                                </div>

                                {{-- Agent (masteradmin only) --}}
                                @masteradmin
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">{{ __('user.agent_name') }}</label>
                                    <input type="text" name="agent_name" class="form-control"
                                           value="{{ request('agent_name') }}">
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
                                    <a href="{{ route('admin.gamelog.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.gamelog_id') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.gamelogtarget_id') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('game.platform') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('provider.provider_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamemember.name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.remark') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.tableid') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.before_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.after_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.betamount') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.winloss') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.startdt') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.enddt') }}</th>
                                <!-- <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.status') }}</th> -->
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamelog.agent_name') }}</th>
                                @endmasteradmin
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($gamelogs as $gamelog)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamelog->gamelog_id }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamelog->gamelogtarget_id }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamelog->gamemember->gameplatform->gameplatform_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamelog->gamemember->provider->provider_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamelog->gamemember->name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            {{ Str::words($gamelog->remark, 15, '...') }}
                                        </p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamelog->tableid }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($gamelog->before_balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($gamelog->after_balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($gamelog->betamount, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($gamelog->winloss, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamelog->startdt }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamelog->enddt }}</p>
                                    </td>
                                    <!-- <td class="text-center">
                                        @if ($gamelog->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('gamelog.active') }}</span>
                                        @elseif ($gamelog->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('gamelog.inactive') }}</span>
                                        @endif
                                    </td> -->
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($gamelog->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center">{{ __('gamelog.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $gamelogs->firstItem(), 'last' => $gamelogs->lastItem(), 'total' => $gamelogs->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $gamelogs->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
