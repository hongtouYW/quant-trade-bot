@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
@endphp

@section('title', __('messages.setup'))
@section('header-title', __('messages.setup'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('user.edit_user') }}: {{ $user->user_name }}</h6>
            </div>
            <div class="card-body pt-0">
                <form action="{{ route('setup.update', $user->user_id) }}" method="POST" enctype="multipart/form-data">
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

                    @if ( $user->user_role === 'superadmin' || $user->user_role === 'masteradmin' )
                        <div class="mb-3">
                            <div class="form-group">
                                <label for="icon" class="form-label">{{ __('agent.icon') }}</label>
                                <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" accept="image/*">
                                <span class="text-sm  text-secondary text-bold">{{__('messages.maxsizeinfo')}}</span>
                                @if ($agent->icon)
                                    <p class="text-sm mt-2">Current icon: <img src="{{ asset('storage/' . $agent->icon) }}" alt="{{ $agent->agent_name }} Icon" class="img-fluid" style="max-width: 50px;"></p>
                                @endif
                                @error('icon')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label for="user_name" class="form-label">{{ __('user.user_name') }}</label>
                        <input type="text" class="form-control @error('user_name') is-invalid @enderror" id="user_name" name="user_name" value="{{ old('user_name', $user->user_name) }}" required readonly>
                        @error('user_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="user_login" class="form-label">{{ __('user.user_login') }}</label>
                        <input type="text" class="form-control @error('user_login') is-invalid @enderror" id="user_login" name="user_login" value="{{ old('user_login', $user->user_login) }}" required>
                        @error('user_login')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="user_pass" class="form-label">{{ __('user.user_password') }}</label>
                        <input type="password" class="form-control @error('user_pass') is-invalid @enderror" id="user_pass" name="user_pass" placeholder="{{ __('user.leave_blank_for_no_change') }}">
                        @error('user_pass')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if ( $user->user_role === 'superadmin' || $user->user_role === 'masteradmin' )
                        <div class="mb-3">
                            <label for="support" class="form-label">{{ __('agent.support') }}</label>
                            <input type="text" class="form-control @error('support') is-invalid @enderror" id="support" name="support" value="{{ old('support', $agent->support) }}" >
                            @error('support')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="telegramsupport" class="form-label">{{ __('agent.telegramsupport') }}</label>
                            <input type="text" class="form-control @error('telegramsupport') is-invalid @enderror" id="telegramsupport" name="telegramsupport" value="{{ old('telegramsupport', $agent->telegramsupport) }}" >
                            @error('telegramsupport')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="whatsappsupport" class="form-label">{{ __('agent.whatsappsupport') }}</label>
                            <input type="text" class="form-control @error('whatsappsupport') is-invalid @enderror" id="whatsappsupport" name="whatsappsupport" value="{{ old('whatsappsupport', $agent->whatsappsupport) }}" >
                            @error('whatsappsupport')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                    <div class="mb-3">
                        <label for="language" class="form-label">{{ __('messages.language') }}</label>
                        <select id="language" class="form-control" onchange="switchLang(this.value)">
                            @php
                                $currentLang = session('locale', app()->getLocale());
                                $supportedLocales = config('languages.supported', ['en']); // fallback
                            @endphp
                            @php $currentLang = session('locale', app()->getLocale()) @endphp
                            @foreach($supportedLocales as $locale)
                                <option value="{{ $locale }}" {{ $currentLang == $locale ? 'selected' : '' }}>
                                    {{ __('messages.' . $locale) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <!-- <div class="mb-3">
                        <label for="theme" class="form-label">{{ __('messages.theme') }}</label>
                        <select id="theme" class="form-control" onchange="switchtheme(this.value)">
                            @php
                                $currenttheme = session('theme', 'light');
                            @endphp
                            <option value="light" {{ $currenttheme == "light" ? 'selected' : '' }}>
                                {{ __('messages.light') }}
                            </option>
                            <option value="dark" {{ $currenttheme == "dark" ? 'selected' : '' }}>
                                {{ __('messages.dark') }}
                            </option>
                        </select>
                    </div> -->
                    <button type="submit" class="btn btn-primary">{{ __('user.edit_user') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('js')
<script>
    function switchLang(locale) {
        fetch("/lang/" + locale, { method: "GET" })
            .then(() => {
                window.parent.location.reload();
            });
    }
    function switchtheme(theme) {
        const body = document.body;
        if (theme === 'dark') {
            body.classList.add('dark-mode');
        } else {
            body.classList.remove('dark-mode');
        }
        localStorage.setItem('theme', theme);
        @if (Auth::check())
        fetch('{{ route('theme.set') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ theme: theme })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                console.error('[Theme] Failed to save theme');
            }
            window.parent.location.reload();
        })
        .catch(err => console.error('[Theme] Fetch error:', err));
        @endif
    }
</script>
@endpush