@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;
@endphp
@section('title', __('module.notification_management'))
@section('header-title', __('module.notification_management'))
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
                    <h6>{{ __('notification.notification_list') }}</h6>
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
                        <form action="{{ route('admin.notification.index') }}" method="GET">
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
                                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>
                                            {{ __('messages.active') }}
                                        </option>
                                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>
                                            {{ __('messages.inactive') }}
                                        </option>
                                    </select>
                                </div>

                                {{-- Delete --}}
                                <div class="col-md-3 mb-3">
                                    <select name="delete" class="form-control">
                                        <option value="">{{ __('messages.all_delete') }}</option>
                                        <option value="0" {{ request('delete') === '0' ? 'selected' : '' }}>
                                            {{ __('messages.not_deleted') }}
                                        </option>
                                        <option value="1" {{ request('delete') === '1' ? 'selected' : '' }}>
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
                                    <a href="{{ route('admin.notification.index') }}"
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
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('notification.created_on') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('notification.sender') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('notification.recipient') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('notification.title') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('notification.notification_type') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('notification.notification_desc') }}</th>
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('notification.status') }}</th>
                                <th class="text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('notification.firebase_error') }}</th>
                                @masteradmin
                                <th class="text-center text-uppercase text-secondary text-xs font-weight-bolder opacity-7">{{ __('user.agent_name') }}</th>
                                @endmasteradmin
                                <!-- <th class="text-secondary opacity-7"></th> -->
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($notifications as $notification)
                                <tr>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $notification->created_on }}</p>
                                    </td>
                                    @php
                                        $sender_type = $notification->sender_type;
                                        $recipient_type = $notification->recipient_type;
                                    @endphp
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">
                                            {{ optional($notification->sender)->{$sender_type.'_name'} ?? 
                                                __('notification.system') }}
                                            @php
                                                if ( $notification->sender ) {
                                                    echo '<br>';
                                                }
                                            @endphp
                                            {{ optional($notification->sender)->{$sender_type.'_name'} ? 
                                                __('notification.'.$sender_type) : ""
                                            }}
                                        </p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">
                                            {{ optional($notification->recipient)->{$recipient_type.'_name'} ?? 
                                            __('notification.system') }}
                                            @php
                                                if ( $notification->recipient ) {
                                                    echo '<br>';
                                                }
                                            @endphp
                                            {{ optional($notification->recipient)->{$recipient_type.'_name'} ? 
                                                __('notification.'.$recipient_type) : ""
                                            }}
                                        </p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __($notification->title) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __( 'notification.'.$notification->notification_type ) }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0 text-truncate" style="max-width: 200px;">
                                            {{ Str::words( NotificationDescDetail($notification->notification_desc) , 15, '...') }}
                                        </p>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $typecolor = "";
                                            $notificationstatuslabel = "";
                                            switch($notification->status) {
                                                case 0:
                                                    $typecolor = "bg-gradient-secondary";
                                                    $notificationstatuslabel = "notification.pending";
                                                    break;
                                                case -1:
                                                    $typecolor = "bg-gradient-danger";
                                                    $notificationstatuslabel = "notification.inactive";
                                                    break;
                                                case 1:
                                                    $typecolor = "bg-gradient-success";
                                                    $notificationstatuslabel = "notification.active";
                                                    break;
                                                default:
                                                    break;
                                            }
                                        @endphp
                                        <span class="badge badge-sm {{ $typecolor }}">{{ __($notificationstatuslabel) }}</span>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ __($notification->firebase_error) }}</p>
                                    </td>
                                    @masteradmin
                                    <td class="text-center">
                                        <p class="text-xs font-weight-bold mb-0">{{ optional($notification->agent)->agent_name ?? '-' }}</p>
                                    </td>
                                    @endmasteradmin
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100" class="text-center">{{ __('notification.no_data_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container mt-3">
                    <div class="pagination-summary">
                        {{ __('pagination.showing', ['first' => $notifications->firstItem(), 'last' => $notifications->lastItem(), 'total' => $notifications->total()]) }}
                    </div>
                    <nav aria-label="Page navigation">
                        {{ $notifications->links('vendor.pagination.custom') }}
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
@include('components.modals.delete-confirmation')
@endsection
