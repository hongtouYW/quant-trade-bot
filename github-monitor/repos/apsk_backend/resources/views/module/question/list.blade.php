@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.question_management'))
@section('header-title', __('module.question_management'))
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
                    <h6>{{ __('question.question_list') }}</h6>
                    <a href="{{ route('admin.question.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('question.add_new_question') }}</a>
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
                        <form action="{{ route('admin.question.index') }}" method="GET">
                            <div class="row align-items-end">

                                {{-- Search --}}
                                <div class="col-md-4 mb-3">
                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        placeholder="{{ __('messages.search_placeholder') }}"
                                        value="{{ request()->query('search') }}"
                                    >
                                </div>

                                {{-- Status --}}
                                <div class="col-md-3 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">{{ __('messages.all_status') }}</option>
                                        <option value="1" {{ request()->query('status') === '1' ? 'selected' : '' }}>
                                            {{ __('messages.active') }}
                                        </option>
                                        <option value="0" {{ request()->query('status') === '0' ? 'selected' : '' }}>
                                            {{ __('messages.inactive') }}
                                        </option>
                                    </select>
                                </div>

                                {{-- Delete --}}
                                <div class="col-md-3 mb-3">
                                    <select name="delete" class="form-control">
                                        <option value="">{{ __('messages.all_delete') }}</option>
                                        <option value="0" {{ request()->query('delete') === '0' ? 'selected' : '' }}>
                                            {{ __('messages.not_deleted') }}
                                        </option>
                                        <option value="1" {{ request()->query('delete') === '1' ? 'selected' : '' }}>
                                            {{ __('messages.deleted') }}
                                        </option>
                                    </select>
                                </div>

                                {{-- Apply --}}
                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                {{-- Clear --}}
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.question.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('question.title') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('question.question_type') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('question.question_desc') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('question.picture') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.language') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('question.status') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('user.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($questions as $question)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $question->title }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __( 'question.'.$question->question_type ) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            @if ($question->question_desc)
                                                {{ Str::words( $question->question_desc , 15, '...') }}
                                            @endif
                                        </p>
                                    </td>
                                    <td>
                                        @if ($question->picture)
                                            <img src="{{ asset('storage/' . $question->picture) }}"
                                                 alt="{{ $question->title }}"
                                                 class="img-fluid cursor-pointer"
                                                 style="max-width: 100px; cursor:pointer;"
                                                 data-toggle="modal"
                                                 data-target="#imagePreviewModal"
                                                 data-image="{{ asset('storage/' . $question->picture) }}">
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __('messages.'.$question->lang) }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($question->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('question.active') }}</span>
                                        @elseif ($question->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('question.inactive') }}</span>
                                        @endif
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($question->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('question_management')
                                            && $question->delete == 0
                                        )
                                            <a href="{{ route('admin.question.edit', $question->question_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('question.edit_question') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('question_management')
                                            && $question->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $question->question_id }}"
                                                    data-item-name="{{ $question->title }}"
                                                    data-delete-route="{{ route('admin.question.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('question.delete_question') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">{{ __('question.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $questions->firstItem(), 'last' => $questions->lastItem(), 'total' => $questions->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $questions->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@include('components.modals.imagepreview')
@endsection
