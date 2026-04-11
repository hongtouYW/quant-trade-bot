@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.performance_management'))
@section('header-title', __('module.performance_management'))
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
                    <h6>{{ __('performance.performance_list') }}</h6>
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
                        <form action="{{ route('admin.performance.index') }}" method="GET">
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

                                {{-- Commission Rank --}}
                                <div class="col-md-2 mb-3">
                                    <label for="commissionrank_id" class="form-label fw-bold">
                                        {{ __('commissionrank.select') }}
                                    </label>
                                    <select class="form-control select2 @error('commissionrank_id') is-invalid @enderror"
                                        id="commissionrank_id"
                                        name="commissionrank_id">
                                        <option value="">{{ __('commissionrank.select') }}</option>
                                        @foreach ($commissionranks as $commissionrank)
                                            <option value="{{ $commissionrank->commissionrank_id }}"
                                                {{ request()->filled('commissionrank_id') && 
                                                (int)request('commissionrank_id') === (int)$commissionrank->commissionrank_id ? 'selected' : '' }}>
                                                {{ $commissionrank->rank }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('commissionrank_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                            </div>
                            <div class="row align-items-end">

                                {{-- Apply --}}
                                <div class="col-md-2 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>
                                {{-- Clear --}}
                                <div class="col-md-2 mb-3">
                                    <a href="{{ route('admin.performance.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.id') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.performance_date') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('member.member_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.downline') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.upline') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('commissionrank.rank') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.sales_amount') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.commission_amount') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.before_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.after_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.notes') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('performance.status') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.agent_name') }}</th>
                                @endmasteradmin
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($performances as $performance)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $performance->id }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $performance->performance_date }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($performance->Member)->member_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($performance->Mydownline)->member_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($performance->Myupline)->member_name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($performance->Commissionrank)->rank }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($performance->sales_amount, 4) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($performance->commission_amount, 4) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($performance->before_balance, 4) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($performance->after_balance, 4) }}</p>
                                    </td>
                                    <td>
                                        @if ( !is_null( $performance->notes ) )
                                            @php
                                                $jsonnotes = json_decode($performance->notes, true);
                                                $notes = __( 
                                                    $jsonnotes['template'], 
                                                    [
                                                        'member_name' => $jsonnotes['data']['member_name'],
                                                        'amount' => number_format( $jsonnotes['data']['amount'], 4),
                                                    ]
                                                );
                                            @endphp
                                            <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                                {{ Str::words( $notes, 15, '...') }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($performance->status == 1)
                                            <span class="badge badge-sm bg-gradient-success text-dark">{{ __('performance.active') }}</span>
                                        @elseif ($performance->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger text-dark">{{ __('messages.delete') }}</span>
                                        @elseif ($performance->status == 0)
                                            <span class="badge badge-sm bg-gradient-warning text-dark">{{ __('performance.pending') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary text-dark">{{ __('performance.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($performance->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('performance.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $performances->firstItem(), 'last' => $performances->lastItem(), 'total' => $performances->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $performances->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
