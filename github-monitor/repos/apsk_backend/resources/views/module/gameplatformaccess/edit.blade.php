@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('module.gameplatformaccess_management'))
@section('header-title', __('module.gameplatformaccess_management'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0 d-flex align-items-center justify-content-between">
                    <h6>{{ __('gameplatformaccess.edit_gameplatformaccess') }} : {{ $agent->agent_name }}</h6>
                </div>
                <form action="{{ route('admin.gameplatformaccess.update', $agent->agent_id) }}" method="POST" class="p-4">
                    @csrf
                    @method('PUT')

                    {{-- Validation Errors --}}
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Error Message --}}
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    {{-- Success Message --}}
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @foreach ($gameplatforms as $gameplatform)
                        <div class="row mb-3 align-items-center">
                            <!-- Platform Name -->
                            <div class="col-3">
                                <label class="form-label">{{ $gameplatform->gameplatform_name }}</label>
                            </div>

                            <!-- Commission -->
                            <div class="col-3">
                                <label for="commission_{{ $gameplatform->gameplatform_id }}">
                                    {{ __('gameplatformaccess.commission') }}
                                </label>
                                <input type="number" min="0.00" step="0.01"
                                    id="commission_{{ $gameplatform->gameplatform_id }}"
                                    name="commission[{{ $gameplatform->gameplatform_id }}]"
                                    value="{{ $gameplatform->commission }}"
                                    class="form-control commission-input"
                                    data-platform="{{ $gameplatform->gameplatform_id }}"
                                    {{ $gameplatform['status'] == 0 ? 'readonly' : '' }}>
                            </div>

                            <!-- Can Use Toggle -->
                            <div class="col-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox"
                                        class="custom-control-input can-use-switch"
                                        id="can_use_{{ $gameplatform->gameplatform_id }}"
                                        name="can_use[{{ $gameplatform->gameplatform_id }}]"
                                        value="1"
                                        data-platform="{{ $gameplatform->gameplatform_id }}"
                                        {{ $gameplatform['can_use'] == 1 ? 'checked' : '' }}
                                        {{ $gameplatform['status'] == 0 ? 'readonly' : '' }}>
                                    <label class="custom-control-label" for="can_use_{{ $gameplatform->gameplatform_id }}">
                                        {{ __('gameplatformaccess.can_use') }}
                                    </label>
                                </div>
                            </div>

                            <!-- Status Toggle -->
                            @if ( Auth::user()->user_role === 'masteradmin' )
                                <div class="col-3">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox"
                                            class="custom-control-input status-switch"
                                            id="status_{{ $gameplatform->gameplatform_id }}"
                                            name="status[{{ $gameplatform->gameplatform_id }}]"
                                            value="1"
                                            data-platform="{{ $gameplatform->gameplatform_id }}"
                                            {{ $gameplatform['status'] == 1 ? 'checked' : '' }} >
                                        <label class="custom-control-label" for="status_{{ $gameplatform->gameplatform_id }}">
                                            {{ __('gameplatformaccess.status') }}
                                        </label>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <button type="submit" class="btn btn-primary">{{ __('messages.edit') }}</button>
                    @if ( Auth::user()->user_role === 'masteradmin' )
                        <a href="{{ route('admin.agent.index') }}" class="btn btn-secondary">
                            {{ __('messages.cancel') }}
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>
@endsection
@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('.status-switch').change(function() {
            var platformId = $(this).data('platform');
            var isChecked = $(this).is(':checked');

            // Enable/disable corresponding commission and can_use
            $('#commission_' + platformId).prop('readonly', !isChecked);
            $('#can_use_' + platformId).prop('readonly', !isChecked);
        });
    });

</script>
@endpush
