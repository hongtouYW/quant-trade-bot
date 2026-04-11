@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.gamepoint_management'))
@section('header-title', __('module.gamepoint_management'))
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
                    <h6>{{ __('gamepoint.gamepoint_list') }}</h6>
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
                        <form action="{{ route('admin.gamepoint.index') }}" method="GET">
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
                                            {{ __('gamepoint.active') }}
                                        </option>
                                        <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>
                                            {{ __('gamepoint.pending') }}
                                        </option>
                                        <option value="-1" {{ request('status') == '-1' ? 'selected' : '' }}>
                                            {{ __('gamepoint.inactive') }}
                                        </option>
                                    </select>
                                </div>

                                {{-- Type --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">{{ __('gamepoint.type') }}</label>
                                    <select name="type" class="form-control">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        <option value="reload" @selected(request('type')=='reload')>{{ __('gamepoint.reload') }}</option>
                                        <option value="withdraw" @selected(request('type')=='withdraw')>{{ __('gamepoint.withdraw') }}</option>
                                    </select>
                                </div>

                                {{-- Agent (masteradmin only) --}}
                                @masteradmin
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">{{ __('user.agent_name') }}</label>
                                    <input type="text" name="agent_name" class="form-control"
                                           value="{{ request('agent_name') }}">
                                </div>
                                @endmasteradmin

                                {{-- Start On datetime range --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">{{ __('gamepoint.start_on') }}</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="datetime-local" name="start_from" class="form-control"
                                                   value="{{ request('start_from') }}">
                                        </div>
                                        <div class="col">
                                            <input type="datetime-local" name="start_to" class="form-control"
                                                   value="{{ request('start_to') }}">
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
                                    <a href="{{ route('admin.gamepoint.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamepoint.gamepoint_id') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.member_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.shop_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamemember.name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamepoint.type') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamepoint.ip') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.amount') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.before_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.after_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamepoint.start_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamepoint.end_on') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('gamepoint.status') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('user.agent_name') }}</th>
                                @endmasteradmin
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($gamepoints as $gamepoint)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamepoint->gamepoint_id }}</p>
                                    </td>
                                    <td>
                                        @if ( !is_null( $gamepoint->gamemember->member ) )
                                            <p class="text-xs font-weight-bold mb-0">{{ $gamepoint->gamemember->member->member_name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @if ( !is_null( $gamepoint->shop ) )
                                            <p class="text-xs font-weight-bold mb-0">{{ $gamepoint->shop->shop_name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @if ( !is_null( $gamepoint->gamemember ) )
                                            <p class="text-xs font-weight-bold mb-0">{{ $gamepoint->gamemember->name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $typecolor = "";
                                            switch($gamepoint->type) {
                                                case "reload":
                                                    $typecolor = "text-success";
                                                    break;
                                                case "withdraw":
                                                    $typecolor = "text-danger";
                                                    break;
                                                default:
                                                    break;
                                            }
                                        @endphp
                                        <p class="text-xs font-weight-bold mb-0 {{ $typecolor }}">{{ __('gamepoint.'.$gamepoint->type) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamepoint->ip }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($gamepoint->amount, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($gamepoint->before_balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($gamepoint->after_balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamepoint->start_on }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $gamepoint->end_on }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($gamepoint->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('gamepoint.active') }}</span>
                                        @elseif ($gamepoint->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @elseif ($gamepoint->status == 0)
                                            <span class="badge badge-sm bg-gradient-warning">{{ __('gamepoint.pending') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('gamepoint.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($gamepoint->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center">{{ __('gamepoint.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $gamepoints->firstItem(), 'last' => $gamepoints->lastItem(), 'total' => $gamepoints->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $gamepoints->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
