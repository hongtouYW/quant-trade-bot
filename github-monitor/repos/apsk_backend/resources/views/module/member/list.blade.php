@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.member_management'))
@section('header-title', __('module.member_management'))
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
                    <h6>{{ __('member.member_list') }}</h6>
                    <a href="{{ route('admin.member.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('member.add_new_member') }}</a>
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
                <form action="{{ route('admin.member.index') }}" method="GET">
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

                                {{-- Shop Name --}}
                                <div class="col-md-2 mb-3">
                                    <label for="shop_id" class="form-label fw-bold">
                                        {{ __('shop.select') }}
                                    </label>
                                    <select class="form-control select2 @error('shop_id') is-invalid @enderror"
                                        id="shop_id"
                                        name="shop_id">
                                        <option value="">{{ __('shop.select') }}</option>
                                        @foreach ($shops as $shop)
                                            <option value="{{ $shop->shop_id }}"
                                                {{ request()->filled('shop_id') && 
                                                (int)request('shop_id') === (int)$shop->shop_id ? 'selected' : '' }}>
                                                {{ $shop->shop_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('shop_id')
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

                                {{-- Apply --}}
                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                {{-- Clear --}}
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.member.index') }}"
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
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.member_id') }}</th>
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7 ps-2">{{ __('member.member_login') }}</th>
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7 ps-2">{{ __('member.member_password') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.phone') }}</th>
                                <th class="text-uppercase text-uppercase text-primary text-md font-weight-bolder opacity-7">{{ __('member.balance') }}</th>
                                @masteradmin
                                    <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.last_login') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.status') }}</th>
                                @masteradmin
                                <th class="text-secondary opacity-7"></th>
                                @endmasteradmin
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($members as $member)
                                <tr>
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm">{{ $member->member_id }}</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-primary text-md font-weight-bold mb-0">{{ $member->member_login }}</p>
                                    </td>
                                    <td>
                                        <div class="position-relative">
                                            <input
                                                id="member-password-{{ $member->member_id }}"
                                                type="password"
                                                class="form-control form-control-sm"
                                                placeholder="••••••••"
                                                data-member-id="{{ $member->member_id }}"
                                                value="••••••••"
                                                style="padding-right: 2.2rem;"
                                                readonly
                                            >
                                            <span
                                                class="password-toggle-icon"
                                                data-member-id="{{ $member->member_id }}">
                                                <i class="fas fa-eye-slash"></i>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ formatPhone($member->phone) }}</p>
                                    </td>
                                    <td>
                                        <a class="text-primary text-md font-weight-bold mb-0" href="{{ route('admin.member.show', $member->member_id) }}">
                                            {{ number_format($member->balance, 2) }}
                                        </a>
                                    </td>
                                    @masteradmin
                                        <td class="text-center">
                                            <p class="text-xs font-weight-bold mb-0">{{ optional($member->agent)->agent_name ?? '-' }}</p>
                                        </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        <span class="text-secondary text-xs font-weight-bold">{{ $member->lastlogin_on ? \Carbon\Carbon::parse($member->lastlogin_on)->format('Y-m-d H:i') : __('member.never') }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($member->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('member.active') }}</span>
                                        @elseif ($member->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('member.inactive') }}</span>
                                        @endif
                                    </td>
                                    {{-- Sync Gamelog --}}
                                    @masteradmin
                                    <td class="text-center">
                                        <button
                                            class="btn btn-sm btn-warning sync-gamelog-btn"
                                            data-id="{{ $member->member_id }}">
                                            {{ __('gamelog.sync') }}
                                        </button>
                                        <div class="progress mt-1 sync-progress d-none" style="height: 18px;">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated"
                                                style="width: 0%">
                                                0%
                                            </div>
                                        </div>
                                    </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('member_management')
                                            && $member->delete == 0
                                        )
                                            <a href="{{ route('admin.member.edit', $member->member_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('member.edit_member') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('member_management')
                                            && $member->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $member->member_id }}"
                                                    data-item-name="{{ $member->member_name }}"
                                                    data-delete-route="{{ route('admin.member.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('member.delete_member') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('member.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $members->firstItem(), 'last' => $members->lastItem(), 'total' => $members->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $members->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@include('components.modals.gamelog')
@endsection
@section('css')
<style>
    .password-toggle-icon {
        position: absolute;
        top: 50%;
        right: 8px;
        transform: translateY(-50%);
        cursor: pointer;
        color: #6c757d;
        z-index: 5;
    }
    .password-toggle-icon:hover {
        color: #343a40;
    }
</style>
@endsection
@section('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $(document).on('click', '.password-toggle-icon', function () {
            const icon = $(this).find('i');
            const input = $(this).closest('.position-relative').find('input');
            const id = input.data('member-id');
            if (input.attr('type') === 'password') {
                $.ajax({
                    url: "{{ route('admin.member.revealpassword', ':id') }}".replace(':id', id),
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function (res) {
                        if (res.status) {
                            input.attr('type', 'text');
                            input.val(res.password);
                            icon.removeClass('fa-eye-slash').addClass('fa-eye');
                        } else {
                            console.error(res.message || 'Failed to reveal password');
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        console.error('Error revealing password');
                    }
                });
            } else {
                input.attr('type', 'password');
                input.val('••••••••');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            }
        });
        @masteradmin
        $(document).on('click', '.sync-gamelog-btn', function () {
            let btn = $(this);
            let memberId = btn.data('id');
            let progressContainer = btn.closest('td').find('.sync-progress');
            let bar = progressContainer.find('.progress-bar');
            $('#gamelogModalLabel').text( `{{ __('gamelog.sync') }} - {{__('role.member')}} : ${memberId} `);
            $('#gamelogModal').modal('show');
            btn.addClass('d-none');
            progressContainer.removeClass('d-none');
            bar.css('width', '0%').text('0%');
            $.post("{{ route('admin.member.syncgamemember', ':id') }}".replace(':id', memberId), {
                _token: "{{ csrf_token() }}"
            }, function (res) {
                if (!res.status) {
                    alert(res.error);
                    btn.removeClass('d-none');
                    progressContainer.addClass('d-none');
                    return;
                }
                let players = res.data;
                runBatchAjax({
                    items: players,
                    url: "{{ route('admin.member.syncgamelog', ':id') }}".replace(':id', memberId),
                    bar: bar,
                    stopOnError: false,
                    onProgress: function (item, index) {
                        gamelogdesc(`{{__('gamelog.sync')}} ${index + 1}. (ID: ${item.name ?? 'N/A'}) `);
                    },
                    onFinish: function ({ results, errors }) {
                        console.log('SYNC RESULTS:', results);
                        if (errors.length > 0) {
                            alert(errors.length + ' sync items failed');
                        }
                        startInsert(memberId, results, bar, btn, progressContainer);
                    },
                    onError: function (err) {
                        console.log('Sync error:', err);
                    }
                });

            });
        });
        function startInsert(memberId, gamelogs, bar, btn, progressContainer) {
            let totalTurnover = 0;
            runBatchAjax({
                items: gamelogs,
                url: "{{ route('admin.member.insertgamelog', ':id') }}".replace(':id', memberId),
                bar: bar,
                stopOnError: false,
                onProgress: function (item, index) {
                    gamelogdesc(`{{__('gamelog.sync')}} ${index + 1}. (ID: ${item.gamelogtarget_id ?? 'N/A'}) - ( ${item.remark ?? 'N/A'} ) `);
                },
                onFinish: function ({ results, errors }) {
                    results.forEach(r => {
                        if (r.data) {
                            totalTurnover += parseFloat(r.data);
                        }
                    });
                    console.log('TOTAL TURNOVER:', totalTurnover);
                    $.post("{{ route('admin.member.turnover', ':id') }}".replace(':id', memberId), {
                        _token: "{{ csrf_token() }}",
                        turnover: totalTurnover
                    });
                    if (errors.length > 0) {
                        alert(errors.length + ' items failed');
                    }
                    btn.removeClass('d-none');
                    progressContainer.addClass('d-none');
                }
            });
        }
        @endmasteradmin
    });
</script>
@endsection
