@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp
@section('title', __('module.shop_management'))
@section('header-title', __('module.shop_management'))
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
                    <h6>{{ __('shop.shop_list') }}</h6>
                    <a href="{{ route('admin.shop.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('shop.add_new_shop') }}</a>
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
                <form action="{{ route('admin.shop.index') }}" method="GET">
                    <div class="row mt-3">
                        <div class="col-12">
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

                                {{-- Manager Name --}}
                                <div class="col-md-2 mb-3">
                                    <label for="manager_id" class="form-label fw-bold">
                                        {{ __('manager.select') }}
                                    </label>
                                    <select class="form-control select2 @error('manager_id') is-invalid @enderror"
                                        id="manager_id"
                                        name="manager_id">
                                        <option value="">{{ __('manager.select') }}</option>
                                        @foreach ($managers as $manager)
                                            <option value="{{ $manager->manager_id }}"
                                                {{ request()->filled('manager_id') && 
                                                (int)request('manager_id') === (int)$manager->manager_id ? 'selected' : '' }}>
                                                {{ $manager->manager_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('manager_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- User Name --}}
                                <div class="col-md-2 mb-3">
                                    <label for="user_id" class="form-label fw-bold">
                                        {{ __('user.select') }}
                                    </label>
                                    <select class="form-control select2 @error('user_id') is-invalid @enderror"
                                        id="user_id"
                                        name="user_id">
                                        <option value="">{{ __('user.select') }}</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->user_id }}"
                                                {{ request()->filled('user_id') && 
                                                (int)request('user_id') === (int)$user->user_id ? 'selected' : '' }}>
                                                {{ $user->user_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_id')
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
                                    <a href="{{ route('admin.shop.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.shop_id') }}</th>
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7 ps-2">{{ __('shop.shop_login') }}</th>
                                @masteradmin
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7 ps-2">{{ __('shop.shop_password') }}</th>
                                @endmasteradmin
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.shop_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.principal') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.lowestbalance') }}</th>
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7">{{ __('shop.balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('state.state_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('area.area_name') }}</th>
                                <th class="text-uppercase text-primary text-md font-weight-bolder opacity-7">{{ __('manager.manager_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.created_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.updated_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.referral_link') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.status') }}</th>
                                @masteradmin
                                    <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.last_login') }}</th>
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shops as $shop)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $shop->shop_id }}</p>
                                    </td>
                                    <td>
                                        <p class="text-primary text-md font-weight-bold mb-0">{{ $shop->shop_login }}</p>
                                    </td>
                                    @masteradmin
                                    <td>
                                        <div class="position-relative">
                                            <input
                                                id="shop-password-{{ $shop->shop_id }}"
                                                type="password"
                                                class="form-control form-control-sm"
                                                placeholder="••••••••"
                                                data-shop-id="{{ $shop->shop_id }}"
                                                value="••••••••"
                                                style="padding-right: 2.2rem;"
                                                readonly
                                            >
                                            <span
                                                class="password-toggle-icon"
                                                data-shop-id="{{ $shop->shop_id }}">
                                                <i class="fas fa-eye-slash"></i>
                                            </span>
                                        </div>
                                    </td>
                                    @endmasteradmin
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $shop->shop_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $shop->principal }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $shop->lowestbalance }}</p>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.shop.show', $shop->shop_id) }}"
                                           class="text-primary text-md font-weight-bold mb-0">
                                            {{ number_format($shop->balance, 2) }}
                                        </a>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($shop->Areas?->States)->state_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($shop->Areas)->area_name }}</p>
                                    </td>
                                    <td>
                                        @if ( !is_null( $shop->manager ) )
                                            <p class="text-primary text-md font-weight-bold mb-0">{{ $shop->manager->manager_name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $shop->created_on }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $shop->updated_on }}</p>
                                    </td>
                                    <td>
                                        @if ($shop->delete == 0)
                                            <button type="button" class="btn btn-link text-secondary mb-0 p-0 shop-link-btn"
                                                    data-toggle="modal" data-target="#ShopUrlModal"
                                                    data-item-id="{{ $shop->shop_id }}"
                                                    data-item-name="{{ $shop->title }}"
                                                    title="{{ __('shop.referral_link') }}"
                                                    >
                                                <i class="fas fa-link text-success"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($shop->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('shop.active') }}</span>
                                        @elseif ($shop->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('shop.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                        <td class="text-center">
                                            <p class="text-xs font-weight-bold mb-0">{{ optional($shop->agent)->agent_name ?? '-' }}</p>
                                        </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        <span class="text-secondary text-xs font-weight-bold">{{ $shop->lastlogin_on ? \Carbon\Carbon::parse($shop->lastlogin_on)->format('Y-m-d H:i') : __('shop.never') }}</span>
                                    </td>
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('shop_management')
                                            && $shop->delete == 0
                                        )
                                            <a href="{{ route('admin.shop.edit', $shop->shop_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('shop.edit_shop') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('shop_management')
                                            && $shop->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $shop->shop_id }}"
                                                    data-item-name="{{ $shop->shop_name }}"
                                                    data-delete-route="{{ route('admin.shop.delete', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('shop.delete_shop') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('shop.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $shops->firstItem(), 'last' => $shops->lastItem(), 'total' => $shops->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $shops->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation-reason')
@include('components.modals.shop-url')
@endsection

@masteradmin
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
<script>
    $(document).ready(function() {
        $(document).on('click', '.password-toggle-icon', function () {
            const icon = $(this).find('i');
            const input = $(this).closest('.position-relative').find('input');
            const id = input.data('shop-id');
            if (input.attr('type') === 'password') {
                $.ajax({
                    url: "{{ route('admin.shop.revealpassword', ':id') }}".replace(':id', id),
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
    });
</script>
@endsection
@endmasteradmin
