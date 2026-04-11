<!DOCTYPE html>
<html>
<head>
    <title>{{ __('messages.otp_title') }}</title>
</head>
<body>
    <p>{{ __('messages.otp_receipient') }}</p>
    <p>{{ __('messages.otp_message') }}<strong>{{ $otp }}</strong></p>
    <p>{{ __('messages.otp_validfor') }}{{ config('security.otp_expiration_minutes', 3) }}{{ __('messages.minutes') }}</p>
    <p>{{ __('messages.otp_private') }}</p>
    <p>{{ __('messages.otp_thank') }}</p>
    <p>{{ config('app.name') }} Team</p>
</body>
</html>