@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.agreement_management'))
@section('header-title', __('module.agreement_management'))
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
                    <h6>{{ __('agreement.agreement_list') }}</h6>
                    <a href="{{ route('admin.agreement.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('agreement.add_new_agreement') }}</a>
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

                {{-- Filters --}}
                <div class="row mt-3">
                    <div class="col-12">
                        <form action="{{ route('admin.agreement.index') }}" method="GET">
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

                                @masteradmin
                                {{-- Agent Name --}}
                                <div class="col-md-2 mb-3">
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

                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.agreement.index') }}"
                                       class="btn btn-secondary btn-block">
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agreement.title') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agreement.picture') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agreement.desc') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agreement.url') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.language') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agreement.status') }}</th>
                                @masteradmin
                                    <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('agreement.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($agreements as $agreement)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $agreement->title }}</p>
                                    </td>
                                    <td>
                                        @if ($agreement->picture)
                                            <img src="{{ asset('storage/' . $agreement->picture) }}"
                                                 alt="{{ $agreement->title }}"
                                                 class="img-fluid cursor-pointer"
                                                 style="max-width: 30px; cursor:pointer;"
                                                 data-toggle="modal"
                                                 data-target="#imagePreviewModal"
                                                 data-image="{{ asset('storage/' . $agreement->picture) }}">
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            {{ Str::words($agreement->desc, 15, '...') }}
                                        </p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $agreement->url }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __('messages.'.$agreement->lang) }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($agreement->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('agreement.active') }}</span>
                                        @elseif ($agreement->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('agreement.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                        <td class="text-center">
                                            <p class="text-xs font-weight-bold mb-0">{{ optional($agreement->agent)->agent_name ?? '-' }}</p>
                                        </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('agreement_management')
                                            && $agreement->delete == 0
                                        )
                                            <a href="{{ route('admin.agreement.edit', $agreement->agreement_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               title="{{ __('agreement.edit_agreement') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('agreement_management')
                                            && $agreement->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $agreement->agreement_id }}"
                                                    data-item-name="{{ $agreement->title }}"
                                                    data-delete-route="{{ route('admin.agreement.destroy', '__ITEM_ID__') }}"
                                                    title="{{ __('agreement.delete_agreement') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">{{ __('agreement.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $agreements->firstItem(), 'last' => $agreements->lastItem(), 'total' => $agreements->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $agreements->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@include('components.modals.imagepreview')
@endsection
