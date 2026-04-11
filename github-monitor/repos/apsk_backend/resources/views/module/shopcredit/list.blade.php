@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.shopcredit_management'))
@section('header-title', __('module.shopcredit_management'))
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
                    <h6>{{ __('shopcredit.shopcredit_list') }}</h6>
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
                        <form action="{{ route('admin.shopcredit.index') }}" method="GET">
                            <div class="row align-items-end">
                                {{-- Search --}}
                                <div class="col-md-6 mb-3">
                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="{{ __('messages.search_placeholder') }}"
                                        value="{{ request('search') }}"
                                    >
                                </div>
                                {{-- Status --}}
                                <div class="col-md-3 mb-3">
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
                                {{-- Type --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('shopcredit.type') }}
                                    </label>
                                    <select name="type" class="form-control">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        @php
                                            $types = ['clear', 'limit', 'deposit', 'withdraw'];
                                        @endphp

                                        @foreach ($types as $type)
                                            <option value="{{ $type }}"
                                                {{ request('type') === $type ? 'selected' : '' }}>
                                                {{ __('shopcredit.'.$type) ?? ucfirst($type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row align-items-end">
                                {{-- Manager Name --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('manager.manager_name') }}
                                    </label>
                                    <input
                                        type="text"
                                        name="manager_name"
                                        class="form-control"
                                        placeholder="{{ __('manager.manager_name') }}"
                                        value="{{ request('manager_name') }}">
                                </div>
                                @masteradmin
                                {{-- Agent Name --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('user.agent_name') }}
                                    </label>
                                    <input
                                        type="text"
                                        name="agent_name"
                                        class="form-control"
                                        placeholder="{{ __('user.agent_name') }}"
                                        value="{{ request('agent_name') }}">
                                </div>
                                @endmasteradmin
                                {{-- Apply --}}
                                <div class="col-md-2 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>
                                {{-- Clear --}}
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.shopcredit.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.shopcredit_id') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.shop_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('manager.manager_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.type') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.reason') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.amount') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.before_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.after_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.submit_on') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.status') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shopcredit.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shopcredits as $shopcredit)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $shopcredit->shopcredit_id }}</p>
                                    </td>
                                    <td>
                                        @if ( !is_null( $shopcredit->shop ) )
                                            <p class="text-xs font-weight-bold mb-0">{{ $shopcredit->shop->shop_name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @if ( !is_null( $shopcredit->manager ) )
                                            <p class="text-xs font-weight-bold mb-0">{{ $shopcredit->manager->manager_name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $typecolor = "";
                                            switch($shopcredit->type) {
                                                case "shopcredit.deposit":
                                                    $typecolor = "text-success";
                                                    break;
                                                case "shopcredit.withdraw":
                                                    $typecolor = "text-danger";
                                                    break;
                                                case "shopcredit.clear":
                                                    $typecolor = "text-primary";
                                                    break;
                                                case "shopcredit.limit":
                                                    $typecolor = "text-secondary";
                                                    break;
                                                default:
                                                    break;
                                            }
                                        @endphp
                                        <p class="font-weight-bold mb-0 {{ $typecolor }}">{{ __($shopcredit->type) }}</p>
                                    </td>
                                    <td>
                                        @if ( !is_null( $shopcredit->reason ) )
                                            <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                                {{ Str::words( __($shopcredit->reason), 15, '...') }}
                                            </p>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($shopcredit->amount, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($shopcredit->before_balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($shopcredit->after_balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $shopcredit->submit_on }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($shopcredit->status == 1)
                                            <span class="badge badge-sm bg-gradient-success text-dark">{{ __('shopcredit.active') }}</span>
                                        @elseif ($shopcredit->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger text-dark">{{ __('messages.delete') }}</span>
                                        @elseif ($shopcredit->status == 0)
                                            <span class="badge badge-sm bg-gradient-warning text-dark">{{ __('shopcredit.pending') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary text-dark">{{ __('shopcredit.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($shopcredit->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('shopcredit_management')
                                            && $shopcredit->delete == 0
                                            && $shopcredit->status == 0
                                        )
                                            <a href="{{ route('admin.shopcredit.edit', $shopcredit->shopcredit_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('shopcredit.edit_shopcredit') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                            <!-- <button type="button" class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $credit->credit_id }}"
                                                    data-item-name="{{ $credit->credit_id }}"
                                                    data-delete-route="{{ route('admin.credit.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('credit.delete_credit') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button> -->
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center">{{ __('shopcredit.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $shopcredits->firstItem(), 'last' => $shopcredits->lastItem(), 'total' => $shopcredits->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $shopcredits->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
