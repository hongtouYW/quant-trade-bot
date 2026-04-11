@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.credit_management'))
@section('header-title', __('module.credit_management'))
@section('header-description')
    {{-- Use Auth::user() for session-based authentication --}}
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header p-0 pt-1">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'complete' ? 'active' : '' }}"
                        href="{{ route('admin.credit.index', ['tab' => 'complete']) }}">
                            {{ __('credit.complete') }}
                            <span class="badge bg-success me-1" title="{{ __('credit.active') }}">
                                {{ $completeCount ?? 0 }}
                            </span>
                            <span class="badge bg-danger" title="{{ __('credit.inactive') }}">
                                {{ $failCount ?? 0 }}
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'pending' ? 'active' : '' }}"
                        href="{{ route('admin.credit.index', ['tab' => 'pending']) }}">
                            {{ __('credit.pending') }}
                            <span class="badge bg-warning">{{ $pendingCount ?? 0 }}</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="card mb-4">
                    <div class="card-header pb-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6>
                               {{ __('credit.credit_list') }} - {{ $tab === 'pending' ? __('credit.pending') : __('credit.complete') }}
                            </h6>
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
                            <div class="col-10">
                                <form action="{{ route('admin.credit.index') }}" method="GET">
                                    <input type="hidden" name="tab" value="{{ $tab }}">
                                    <div class="row align-items-end">
                                        {{-- Search --}}
                                        <div class="col-md-2 mb-3">
                                            <input
                                                type="text"
                                                name="search"
                                                class="form-control"
                                                placeholder="{{ __('messages.search_placeholder') }}"
                                                value="{{ request('search') }}"
                                            >
                                        </div>
                                        {{-- Status --}}
                                        @if ($tab !== 'pending')
                                            <div class="col-md-2 mb-3">
                                                <label class="form-label fw-bold">
                                                    {{ __('user.status') }}
                                                </label>
                                                <select name="status" class="form-control">
                                                    <option value="">{{ __('messages.all_status') }}</option>
                                                    <option value="1">{{ __('credit.active') }}</option>
                                                    <option value="-1">{{ __('credit.inactive') }}</option>
                                                </select>
                                            </div>
                                        @endif
                                        {{-- Type --}}
                                        @if ($tab !== 'complete')
                                            <div class="col-md-2 mb-3">
                                                <label class="form-label fw-bold">{{ __('credit.type') }}</label>
                                                <select name="type" class="form-control">
                                                    <option value="">{{ __('messages.all_status') }}</option>
                                                    <option value="deposit" @selected(request('type')=='deposit')>
                                                        {{__('credit.deposit')}}
                                                    </option>
                                                    <option value="withdraw" @selected(request('type')=='withdraw')>
                                                        {{__('credit.withdraw')}}
                                                    </option>
                                                </select>
                                            </div>
                                        @endif
                                        {{-- Payment Gateway --}}
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label fw-bold">{{ __('paymentgateway.payment_name') }}</label>
                                            <select name="payment_id" class="form-control">
                                                <option value="">{{ __('messages.all_status') }}</option>
                                                @foreach($paymentgateways as $pg)
                                                    <option value="{{ $pg->payment_id }}"
                                                        @selected(request('payment_id')==$pg->payment_id)>
                                                        {{ $pg->payment_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        {{-- Agent --}}
                                        @masteradmin
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label fw-bold">{{ __('credit.agent_name') }}</label>
                                            <input type="text" name="agent_name" class="form-control"
                                                   value="{{ request('agent_name') }}">
                                        </div>
                                        @endmasteradmin
                                        {{-- Shop --}}
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label fw-bold">{{ __('shop.shop_name') }}</label>
                                            <input type="text" name="shop_name" class="form-control"
                                                   value="{{ request('shop_name') }}">
                                        </div>
                                        {{-- Submit On --}}
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label fw-bold">{{ __('credit.submit_on') }}</label>
                                            <div class="row g-2">
                                                <div class="col">
                                                    <input type="datetime-local" name="submit_from" class="form-control"
                                                           value="{{ request('submit_from') }}">
                                                </div>
                                                <div class="col">
                                                    <input type="datetime-local" name="submit_to" class="form-control"
                                                           value="{{ request('submit_to') }}">
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Approve On --}}
                                        @if ($tab !== 'pending')
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label fw-bold">{{ __('credit.approve_on') }}</label>
                                                <div class="row g-2">
                                                    <div class="col">
                                                        <input type="datetime-local" name="approve_from" class="form-control"
                                                               value="{{ request('approve_from') }}">
                                                    </div>
                                                    <div class="col">
                                                        <input type="datetime-local" name="approve_to" class="form-control"
                                                               value="{{ request('approve_to') }}">
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        {{-- Apply --}}
                                        <div class="col-md-1 mb-3">
                                            <button type="submit" class="btn btn-info btn-block">
                                                {{ __('messages.apply_filters') }}
                                            </button>
                                        </div>
                                        {{-- Clear --}}
                                        <div class="col-md-1 mb-3">
                                            <a href="{{ route('admin.credit.index', ['tab' => $tab]) }}"
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
                        <div class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.credit_id') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.orderid') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.transactionId') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('paymentgateway.payment_name') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.member_name') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('bank.bank_name') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.bank_account') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.bank_full_name') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('shop.shop_name') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.type') }}</th>
                                        @if ($tab !== 'pending')
                                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.reason') }}</th>
                                        @endif
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.amount') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.before_balance') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.after_balance') }}</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.submit_on') }}</th>
                                        @if ($tab !== 'pending')
                                            <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.approve_on') }}</th>
                                        @endif
                                        <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.status') }}</th>
                                        @masteradmin
                                            <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('credit.agent_name') }}</th>
                                        @endmasteradmin
                                        <th class="text-secondary text-xs opacity-7"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($credits as $credit)
                                        <tr>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ $credit->credit_id }}</p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ $credit->orderid }}</p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ $credit->transactionId }}</p>
                                            </td>
                                            <td>
                                                @if ( !is_null( $credit->paymentgateway ) )
                                                    <p class="text-xs font-weight-bold mb-0">{{ __( 'credit.'.$credit->paymentgateway->payment_name ) }}</p>
                                                @endif
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ optional($credit->Member)->member_name }}</p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ optional($credit->Bankaccount?->Bank)->bank_name }}</p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ optional($credit->Bankaccount)->bank_account }}</p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ optional($credit->Bankaccount)->bank_full_name }}</p>
                                            </td>
                                            <td>
                                                @if ( !is_null( $credit->shop ) )
                                                    <p class="text-xs font-weight-bold mb-0">{{ $credit->shop->shop_name }}</p>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $typecolor = "";
                                                    switch($credit->type) {
                                                        case "deposit":
                                                            $typecolor = "text-success";
                                                            break;
                                                        case "withdraw":
                                                            $typecolor = "text-danger";
                                                            break;
                                                        case "gamedeposit":
                                                            $typecolor = "text-primary";
                                                            break;
                                                        case "gamewithdraw":
                                                            $typecolor = "text-maroon";
                                                            break;
                                                        case "firstbonus":
                                                            $typecolor = "text-orange";
                                                            break;
                                                        case "dailybonus":
                                                            $typecolor = "text-orange";
                                                            break;
                                                        case "weeklybonus":
                                                            $typecolor = "text-orange";
                                                            break;
                                                        case "monthlybonus":
                                                            $typecolor = "text-orange";
                                                            break;
                                                        default:
                                                            break;
                                                    }
                                                @endphp
                                                <p class="text-xs font-weight-bold mb-0 {{ $typecolor }}">{{ __('credit.'.$credit->type) }}</p>
                                            </td>
                                            @if ($tab !== 'pending')
                                                <td>
                                                    @if ( !is_null( $credit->reason ) )
                                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                                            {{ Str::words( __($credit->reason) , 15, '...') }}
                                                        </p>
                                                    @else
                                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                                            -
                                                        </p>
                                                    @endif
                                                </td>
                                            @endif
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ number_format($credit->amount, 2) }}</p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ number_format($credit->before_balance, 2) }}</p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ number_format($credit->after_balance, 2) }}</p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ $credit->submit_on }}</p>
                                            </td>
                                            @if ($tab !== 'pending')
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0">{{ $credit->updated_on }}</p>
                                                </td>
                                            @endif
                                            <td class="text-center">
                                                @if ($credit->status == 1)
                                                    <span class="badge badge-sm bg-gradient-success">{{ __('credit.active') }}</span>
                                                @elseif ($credit->delete == 1)
                                                    <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                                @elseif ($credit->status == 0)
                                                    <span class="badge badge-sm bg-gradient-warning text-dark">{{ __('credit.pending') }}</span>
                                                @else
                                                    <span class="badge badge-sm bg-gradient-danger">{{ __('credit.inactive') }}</span>
                                                @endif
                                            </td>
                                            @masteradmin
                                                <td class="text-center">
                                                    <p class="text-xs font-weight-bold mb-0">{{ optional($credit->agent)->agent_name ?? '-' }}</p>
                                                </td>
                                            @endmasteradmin
                                            <td class="text-center">
                                                @if ($tab === 'pending')
                                                    {{-- Edit --}}
                                                    @if ( canEdit('credit_management') && 
                                                        $credit->delete == 0 && 
                                                        $credit->status == 0 && 
                                                        $credit->type === 'withdraw' 
                                                    )
                                                        <a href="{{ route('admin.credit.edit', $credit->credit_id) }}"
                                                            class="btn btn-link text-secondary text-xs mb-0 p-0"
                                                            data-toggle="tooltip"
                                                            data-original-title="{{ __('credit.edit_credit') }}">
                                                            <i class="fas fa-edit text-info"></i>
                                                        </a>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="100" class="text-center">{{ __('credit.no_data_found') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-container mt-3">
                            <div class="pagination-summary">
                                {{ __('pagination.showing', ['first' => $credits->firstItem(), 'last' => $credits->lastItem(), 'total' => $credits->total()]) }}
                            </div>
                            <nav aria-label="Page navigation">
                                {{ $credits->links('vendor.pagination.custom') }}
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
@push('js')
<script>
    // Initialize tooltips inside AdminLTE iframe
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el)
    })
</script>
@endpush
