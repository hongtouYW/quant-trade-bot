@extends('layouts.guest')
@section('title', __('messages.login')) {{-- Translated title --}}
@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0 rounded-lg mt-5">
                <div class="card-header bg-gradient-primary text-white text-center">
                    <h3 class="font-weight-bolder mb-0">{{ __('messages.sign_in') }}</h3> {{-- Translated "Sign In" header --}}
                </div>
                <div class="card-body p-5">
                    @if (session('success'))
                        <div class="alert alert-success" id="success-message">
                            {{ session('success') }}
                        </div>
                    @endif
                    <form id="login-form" action="{{ url('/home/login') }}" method="POST">
                        @csrf
                        <input type="hidden" name="type" value="user">
                        <div class="mb-3">
                            <label class="form-label">{{ __('messages.username') }}</label>
                            <input type="text" class="form-control" name="login" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('messages.password') }}</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
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
                        <div class="mb-3 d-none" id="two-factor-field">
                            <label class="form-label">{{ __('messages.two_factor_code') }}</label>
                            <input type="text" class="form-control" name="two_factor_code">
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn bg-gradient-primary w-100">{{ __('messages.sign_in') }}</button> {{-- Translated "Sign In" button --}}
                        </div>
                        <div id="error-message" class="alert alert-danger mt-3 d-none"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Pass translated messages from Blade to JavaScript
    const translatedMessages = {
        invalidCredentials: '{{ __('messages.invalid_credentials') }}',
        accountInactive: '{{ __('messages.account_inactive') }}',
        accountDeleted: '{{ __('messages.account_deleted') }}',
        twoFaRequired: '{{ __('messages.2fa_required') }}',
        invalidTwoFa: '{{ __('messages.2fa_invalid') }}',
        twoFaSetupError: '{{ __('messages.2fa_setup_error') }}',
        twoFaVerificationError: '{{ __('messages.2fa_verification_error') }}',
        databaseError: '{{ __('messages.database_error') }}',
        unexpectedError: '{{ __('messages.unexpected_error') }}',
        sessionExpired: '{{ __('messages.session_expired') }}',
        networkError: '{{ __('messages.network_error') }}',
        validationFailed: '{{ __('validation.custom.validation_error') }}'
    };

    sessionStorage.removeItem('api_token');

    function clearSuccessMessage() {
        const successMessage = document.getElementById('success-message');
        if (successMessage) {
            successMessage.classList.add('d-none');
        }
    }

    document.getElementById('login-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const errorMessage = document.getElementById('error-message');
        const twoFactorField = document.getElementById('two-factor-field');
        errorMessage.classList.add('d-none');
        clearSuccessMessage();

        try {
            // const response = await fetch(form.action, {
            //     method: 'POST',
            //     body: formData,
            //     credentials: 'same-origin',
            //     headers: {
            //         'Accept': 'application/json',
            //         'X-CSRF-TOKEN': document
            //             .querySelector('meta[name="csrf-token"]')
            //             .getAttribute('content')
            //     }
            // });
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (response.ok && data.status === true) {
                sessionStorage.setItem('api_token', data.token);
                sessionStorage.removeItem('expoadmin_login');
                sessionStorage.removeItem('expoadmin_password');
                document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf);
                window.location.href = '/dashboard';
            } else {
                errorMessage.classList.remove('d-none');
                let displayMessage = translatedMessages.unexpectedError; // Default to generic error

                // Handle validation errors
                if (data.code === 422 && data.error && typeof data.error === 'object') {
                    displayMessage = translatedMessages.validationFailed;
                    const errorList = [];
                    for (const field in data.error) {
                        errorList.push(...data.error[field]); // Collect all validation messages
                    }
                    errorMessage.innerHTML = `${displayMessage}<ul>${errorList.map(msg => `<li>${msg}</li>`).join('')}</ul>`;
                }
                // Handle other errors by matching translated messages
                else if (data.message) {
                    const messages = Object.values(translatedMessages);
                    if (messages.includes(data.message)) {
                        displayMessage = data.message; // Use the translated message directly
                    } else {
                        // Fallback for unmapped messages
                        displayMessage = data.message || data.error || translatedMessages.unexpectedError;
                    }
                    // Show 2FA field if required
                    if (data.message === translatedMessages.twoFaRequired) {
                        twoFactorField.classList.remove('d-none');
                    }
                }

                errorMessage.textContent = displayMessage;
                console.error('Login failed:', data); // Debug: Log error data
            }
        } catch (error) {
            errorMessage.classList.remove('d-none');
            errorMessage.textContent = translatedMessages.unexpectedError + ': ' + error.message;
            console.error('Error during login:', error);
        }
    });

    function saveloginstorage() {
        sessionStorage.setItem( 'expoadmin_login', document.querySelector('input[name="login"]')?.value || '' );
        sessionStorage.setItem( 'expoadmin_password', document.querySelector('input[name="password"]')?.value || '' );
    }

    document.addEventListener('input', function (e) {
        if (
            e.target.name === 'login' ||
            e.target.name === 'password'
        ) {
            saveloginstorage();
        }
    });

    window.onload = function() {
        // Skip token check if a success message is present (indicating recent logout)
        if (document.querySelector('.alert-success')) {
            return;
        }

        const token = sessionStorage.getItem('api_token');
        const errorMessage = document.getElementById('error-message');
        const login = sessionStorage.getItem('expoadmin_login');
        const password = sessionStorage.getItem('expoadmin_password');
        errorMessage.classList.add('d-none');
        console.log(token,errorMessage,login,password);
        if (login) {
            document.querySelector('input[name="login"]').value = login;
        }

        if (password) {
            document.querySelector('input[name="password"]').value = password;
        }
        if (token) {
            fetch('/profile', {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            })
            .then(async response => {
                if (response.ok) {
                    window.location.href = '/dashboard';
                } else {
                    try {
                        const errorData = await response.json();
                        sessionStorage.removeItem('api_token');
                        sessionStorage.removeItem('expoadmin_login');
                        sessionStorage.removeItem('expoadmin_password');
                        errorMessage.classList.remove('d-none');
                        errorMessage.textContent = errorData.message && Object.values(translatedMessages).includes(errorData.message)
                            ? errorData.message
                            : translatedMessages.sessionExpired;
                    } catch (jsonError) {
                        sessionStorage.removeItem('api_token');
                        errorMessage.classList.remove('d-none');
                        errorMessage.textContent = translatedMessages.networkError;
                        console.error('Error parsing /profile response:', jsonError);
                    }
                }
            })
            .catch(error => {
                sessionStorage.removeItem('api_token');
                errorMessage.classList.remove('d-none');
                errorMessage.textContent = translatedMessages.networkError;
                console.error('Network error checking token:', error);
            });
        }
    };
    function switchLang(locale) {
        saveloginstorage();
        fetch("/lang/" + locale, {
            method: "GET",
            credentials: "same-origin"
        }).then(() => {
            location.reload();
        });
    }
</script>
@endsection