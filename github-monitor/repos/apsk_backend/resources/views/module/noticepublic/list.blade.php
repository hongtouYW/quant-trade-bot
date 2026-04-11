@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.noticepublic_management'))
@section('header-title', __('module.noticepublic_management'))
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
                    <h6>{{ __('noticepublic.noticepublic_list') }}</h6>
                    <a href="{{ route('admin.noticepublic.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('noticepublic.add_new_noticepublic') }}</a>
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
                <form action="{{ route('admin.noticepublic.index') }}" method="GET">
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

                                {{-- Recipient Type --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('noticepublic.recipient_type') }}
                                    </label>
                                    <select class="form-control select2 @error('recipient_type') is-invalid @enderror"
                                        id="recipient_type"
                                        name="recipient_type">
                                        <option value="">{{ __('noticepublic.recipient_type') }}</option>
                                        @foreach ($recipient_types as $recipient_type)
                                            <option value="{{ $recipient_type }}"
                                                {{ request()->filled('recipient_type') && 
                                                (string)request('recipient_type') === (string)$recipient_type ? 
                                                'selected' : '' }}>
                                                {{ __('noticepublic.'.$recipient_type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('recipient_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Language --}}
                                <div class="col-md-2 mb-3">
                                    <label for="language" class="form-label fw-bold">
                                        {{ __('messages.language') }}
                                    </label>
                                    <select class="form-control select2 @error('language') is-invalid @enderror"
                                        id="language"
                                        name="language">
                                        <option value="">{{ __('messages.language') }}</option>
                                        @foreach ($langs as $lang)
                                            <option value="{{ $lang }}"
                                                {{ request()->filled('language') && 
                                                (string)request('language') === (string)$lang ? 'selected' : '' }}>
                                                {{ __('messages.'.$lang) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('language')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="row align-items-end">

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

                                {{-- Start On --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">{{ __('noticepublic.start_on') }}</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="datetime-local"
                                                name="start_on_from"
                                                class="form-control"
                                                value="{{ request('start_on_from') }}">
                                        </div>
                                        <div class="col">
                                            <input type="datetime-local"
                                                name="start_on_to"
                                                class="form-control"
                                                value="{{ request('start_on_to') }}">
                                        </div>
                                    </div>
                                </div>

                                {{-- End On --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">{{ __('noticepublic.end_on') }}</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="datetime-local"
                                                name="end_on_from"
                                                class="form-control"
                                                value="{{ request('end_on_from') }}">
                                        </div>
                                        <div class="col">
                                            <input type="datetime-local"
                                                name="end_on_to"
                                                class="form-control"
                                                value="{{ request('end_on_to') }}">
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
                                    <a href="{{ route('admin.noticepublic.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('noticepublic.title') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('noticepublic.recipient_type') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('noticepublic.desc') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.language') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('noticepublic.start_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('noticepublic.end_on') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('noticepublic.status') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('user.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($noticepublics as $noticepublic)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $noticepublic->title }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __( 'noticepublic.'.$noticepublic->recipient_type ) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            {{ Str::words($noticepublic->desc, 15, '...') }}
                                        </p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __('messages.'.$noticepublic->lang) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $noticepublic->start_on }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $noticepublic->end_on }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($noticepublic->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('noticepublic.active') }}</span>
                                        @elseif ($noticepublic->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('noticepublic.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($noticepublic->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('noticepublic_management')
                                            && $noticepublic->delete == 0
                                        )
                                            <a href="{{ route('admin.noticepublic.edit', $noticepublic->notice_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('noticepublic.edit_noticepublic') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('noticepublic_management')
                                            && $noticepublic->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $noticepublic->notice_id }}"
                                                    data-item-name="{{ $noticepublic->title }}"
                                                    data-delete-route="{{ route('admin.noticepublic.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('noticepublic.delete_noticepublic') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('noticepublic.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $noticepublics->firstItem(), 'last' => $noticepublics->lastItem(), 'total' => $noticepublics->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $noticepublics->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
