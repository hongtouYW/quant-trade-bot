@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
@endphp

@section('title', __('module.managercredit_management'))
@section('header-title', __('module.managercredit_management'))

@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <h6>{{ __('module.managercredit_management') }}</h6>
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
                        <form action="{{ route('admin.managercredit.index') }}" method="GET">
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
                                        {{ __('managercredit.type') }}
                                    </label>
                                    <select name="type" class="form-control">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        @php
                                            $types = ['clear', 'limit', 'deposit', 'withdraw'];
                                        @endphp

                                        @foreach ($types as $type)
                                            <option value="{{ $type }}"
                                                {{ request('type') === $type ? 'selected' : '' }}>
                                                {{ __('managercredit.'.$type) ?? ucfirst($type) }}
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
                                    <a href="{{ route('admin.managercredit.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('managercredit.managercredit_id') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('manager.manager_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('user.user_name') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('managercredit.type') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('managercredit.reason') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('managercredit.amount') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('managercredit.before_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('managercredit.after_balance') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('managercredit.submit_on') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('managercredit.status') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agent.agent_name') }}</th>
                                @endmasteradmin
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($managercredits as $managercredit)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $managercredit->managercredit_id }}</p>
                                    </td>
                                    <td>
                                        @if ( !is_null( $managercredit->manager ) )
                                            <p class="text-xs font-weight-bold mb-0">{{ $managercredit->manager->manager_name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @if ( !is_null( $managercredit->user ) )
                                            <p class="text-xs font-weight-bold mb-0">{{ $managercredit->user->user_name }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $typecolor = "";
                                            switch($managercredit->type) {
                                                case "deposit":
                                                    $typecolor = "text-success";
                                                    break;
                                                case "withdraw":
                                                    $typecolor = "text-danger";
                                                    break;
                                                case "clear":
                                                    $typecolor = "text-primary";
                                                    break;
                                                case "limit":
                                                    $typecolor = "text-secondary";
                                                    break;
                                                default:
                                                    break;
                                            }
                                        @endphp
                                        <p class="font-weight-bold mb-0 {{ $typecolor }}">{{ __($managercredit->type) }}</p>
                                    </td>
                                    <td>
                                        @if ( !is_null( $managercredit->reason ) )
                                            <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                                {{ Str::words( __($managercredit->reason), 15, '...') }}
                                            </p>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($managercredit->amount, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($managercredit->before_balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($managercredit->after_balance, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $managercredit->submit_on }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($managercredit->status == 1)
                                            <span class="badge badge-sm bg-gradient-success text-dark">{{ __('managercredit.active') }}</span>
                                        @elseif ($managercredit->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger text-dark">{{ __('messages.delete') }}</span>
                                        @elseif ($managercredit->status == 0)
                                            <span class="badge badge-sm bg-gradient-warning text-dark">{{ __('managercredit.pending') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary text-dark">{{ __('managercredit.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($managercredit->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center">{{ __('managercredit.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $managercredits->firstItem(), 'last' => $managercredits->lastItem(), 'total' => $managercredits->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $managercredits->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
