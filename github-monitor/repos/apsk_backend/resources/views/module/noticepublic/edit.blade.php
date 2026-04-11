@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    use Carbon\Carbon;
@endphp

@section('title', __('noticepublic.edit_noticepublic'))
@section('header-title', __('noticepublic.edit_noticepublic'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('noticepublic.edit_noticepublic') }}: {{ $noticepublic->title }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <form action="{{ route('admin.noticepublic.update', $noticepublic->notice_id) }}" method="POST" class="p-4" enctype="multipart/form-data">
                    @csrf
                    @method('PUT') {{-- Use PUT method for update --}}

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="recipient_type" class="form-label">{{ __('noticepublic.recipient_type') }}</label>
                        <select class="form-control @error('recipient_type') is-invalid @enderror" 
                        id="recipient_type" 
                        name="recipient_type"
                        required>
                            <option value="">{{ __('noticepublic.recipient_type') }}</option>
                            @foreach ($recipient_types as $recipient_type)
                                <option value="{{ $recipient_type }}" 
                                {{ $recipient_type == $noticepublic->recipient_type ? 'selected' : '' }}>
                                    {{ __('noticepublic.'.$recipient_type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('recipient_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="title" class="form-label">{{ __('noticepublic.title') }}</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $noticepublic->title) }}" required readonly>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="desc" class="form-label">{{ __('noticepublic.desc') }}</label>
                        <textarea class="form-control @error('desc') is-invalid @enderror" id="desc" name="desc" maxlength="1000" style="height: 100px;">{{ old('desc', $noticepublic->desc) }}</textarea>
                        @error('desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            {{ __('noticepublic.start_on') }} - {{ __('noticepublic.end_on') }}
                        </label>
                        <input type="text" 
                            id="date_range" 
                            class="form-control"
                            required>
                        <!-- Hidden fields for backend -->
                        <input type="hidden" name="start_on" id="start_on"
                            value="{{ old('start_on', optional(Carbon::parse($noticepublic->start_on))->format('Y-m-d')) }}">
                        <input type="hidden" name="end_on" id="end_on"
                            value="{{ old('end_on', optional(Carbon::parse($noticepublic->end_on))->format('Y-m-d')) }}">
                    </div>
                    <div class="mb-3">
                        <label for="language" class="form-label">{{ __('messages.selectlanguage') }}</label>
                        <select class="form-control select2 @error('lang') is-invalid @enderror" 
                        id="language" 
                        name="language"
                        required>
                            <option value="">{{ __('messages.selectlanguage') }}</option>
                            @foreach ($langs as $lang)
                                <option value="{{ $lang }}" {{ $noticepublic->lang == $lang ? 'selected' : '' }}>
                                    {{ __('messages.'.$lang) }}
                                </option>
                            @endforeach
                        </select>
                        @error('language')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @masteradmin
                    <div class="mb-3">
                        <label for="agent_id" class="form-label">{{ __('agent.select') }}</label>
                        <select class="form-control select2 @error('agent_id') is-invalid @enderror"
                            id="agent_id"
                            name="agent_id">
                            <option value="">{{ __('agent.select') }}</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->agent_id }}"
                                    {{ $agent->agent_id == $noticepublic->agent_id ? 'selected' : '' }}>
                                    {{ $agent->agent_name }} ({{ $agent->agent_code }})
                                </option>
                            @endforeach
                        </select>
                        @error('agent_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @endmasteradmin
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status" name="status" value="1" {{ old('status', $noticepublic->status) == 1 ? 'checked' : '' }} >
                        <label class="form-check-label" for="status">{{ __('noticepublic.active_status') }}</label>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('noticepublic.edit_noticepublic') }}</button>
                    <a href="{{ route('admin.noticepublic.index') }}" class="btn btn-secondary">{{ __('messages.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script>
    $(function () {
        let start = $('#start_on').val() 
            ? moment($('#start_on').val()) 
            : moment();
        let end = $('#end_on').val() 
            ? moment($('#end_on').val()) 
            : moment().add(1, 'days');
        function updateInputs(start, end) {
            $('#date_range').val(start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));
            $('#start_on').val(start.format('YYYY-MM-DD'));
            $('#end_on').val(end.format('YYYY-MM-DD'));
        }
        $('#date_range').daterangepicker({
            startDate: start,
            endDate: end,
            locale: {
                format: 'YYYY-MM-DD'
            },
            autoApply: true
        }, updateInputs);

        // set initial display
        updateInputs(start, end);
    });
</script>
@endpush
