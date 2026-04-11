@extends('adminlte::page')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.promotion_management'))
@section('header-title', __('module.promotion_management'))
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
                    <h6>{{ __('promotion.promotion_list') }}</h6>
                    <a href="{{ route('admin.promotion.create') }}" class="btn btn-primary btn-sm ms-auto">{{ __('promotion.add_new_promotion') }}</a>
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
                        <form action="{{ route('admin.promotion.index') }}" method="GET">
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

                                {{-- Language --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label fw-bold">
                                        {{ __('messages.language') }}
                                    </label>
                                    <select name="language" class="form-control">
                                        @php
                                            $alllang = config('languages.supported');
                                        @endphp
                                        <option value="">{{ __('messages.language') ?? 'All' }}</option>
                                        @foreach ($alllang as $lang)
                                            <option value="{{$lang}}" 
                                                {{ request('language') === 'en' ? 'selected' : '' }}
                                            >
                                                {{ __('messages.'.$lang) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Promote Type --}}
                                <div class="col-md-2 mb-3">
                                    <label for="promotiontype_id" class="form-label fw-bold">
                                        {{ __('promotion.type') }}
                                    </label>
                                    <select class="form-control select2 @error('promotiontype_id') is-invalid @enderror"
                                        id="promotiontype_id"
                                        name="promotiontype_id">
                                        <option value=""></option>
                                        @foreach ($promotiontypes as $promotiontype)
                                            <option value="{{ $promotiontype->promotiontype_id }}"
                                                {{ request()->filled('promotiontype_id') && 
                                                (int)request('promotiontype_id') === (int)$promotiontype->promotiontype_id ? 'selected' : '' }}>
                                                {{ __("promotion.".$promotiontype->promotion_type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('promotiontype_id')
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

                                {{-- Apply --}}
                                <div class="col-md-1 mb-3">
                                    <button type="submit" class="btn btn-info btn-block">
                                        {{ __('messages.apply_filters') }}
                                    </button>
                                </div>

                                {{-- Clear --}}
                                <div class="col-md-1 mb-3">
                                    <a href="{{ route('admin.promotion.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('promotion.title') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('promotion.type') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('promotion.photo') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('promotion.amount') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('promotion.percent') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('promotion.newbie') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('promotion.url') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('vip.vip_list') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.language') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('promotion.status') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.created_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('messages.updated_on') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('user.agent_name') }}</th>
                                @endmasteradmin
                                <th class="text-secondary opacity-7"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($promotions as $promotion)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 100px;">
                                            {{ Str::words($promotion->title, 10, '...') }}
                                        </p>
                                    </td>
                                    <td>
                                        @if ( $promotion->Promotiontype )
                                            <p class="text-xs font-weight-bold mb-0">{{ __('promotion.'.optional($promotion->Promotiontype)->promotion_type) }}</p>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($promotion->photo)
                                            <img src="{{ asset('storage/' . $promotion->photo) }}"
                                                 alt="{{  $promotion->title }}"
                                                 class="img-fluid cursor-pointer"
                                                 style="max-width: 100px; cursor:pointer;"
                                                 data-toggle="modal"
                                                 data-target="#imagePreviewModal"
                                                 data-image="{{ asset('storage/' . $promotion->photo) }}"
                                                 loading="lazy">
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($promotion->amount, 2) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ number_format($promotion->percent, 2) }}</p>
                                    </td>
                                    <td>
                                        @if ($promotion->newbie == 1)
                                            <span class="text-success">✓</span>
                                        @else
                                            <span class="text-danger">✗</span>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $promotion->url }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $promotion->MyVip ? $promotion->MyVip->vip_name : '' }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __('messages.'.$promotion->lang) }}</p>
                                    </td>
                                    <td class="text-center">
                                        @if ($promotion->status == 1)
                                            <span class="badge badge-sm bg-gradient-success">{{ __('promotion.active') }}</span>
                                        @elseif ($promotion->delete == 1)
                                            <span class="badge badge-sm bg-gradient-danger">{{ __('messages.delete') }}</span>
                                        @else
                                            <span class="badge badge-sm bg-gradient-secondary">{{ __('promotion.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $promotion->created_on }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $promotion->updated_on }}</p>
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($promotion->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                    <td class="text-center">
                                        {{-- Edit --}}
                                        @if (
                                            canEdit('promotion_management')
                                            && $promotion->delete == 0
                                        )
                                            <a href="{{ route('admin.promotion.edit', $promotion->promotion_id) }}"
                                               class="btn btn-link text-secondary mb-0 p-0"
                                               data-toggle="tooltip"
                                               data-original-title="{{ __('promotion.edit_promotion') }}">
                                                <i class="fas fa-edit text-info"></i>
                                            </a>
                                        @endif

                                        {{-- Delete --}}
                                        @if (
                                            canDelete('promotion_management')
                                            && $promotion->delete == 0
                                        )
                                            <button type="button"
                                                    class="btn btn-link text-secondary mb-0 p-0 delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteConfirmationModal"
                                                    data-item-id="{{ $promotion->promotion_id }}"
                                                    data-item-name="{{ $promotion->title }}"
                                                    data-delete-route="{{ route('admin.promotion.destroy', '__ITEM_ID__') }}"
                                                    data-original-title="{{ __('promotion.delete_promotion') }}">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('promotion.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $promotions->firstItem(), 'last' => $promotions->lastItem(), 'total' => $promotions->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $promotions->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@include('components.modals.imagepreview')
@endsection
