<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body>
    <p>{{ __('site.verification.email_intro') }}</p>
    <p style="font-size: 2rem; font-weight: 700; letter-spacing: 0.25rem;">{{ $verificationCode }}</p>
    <p>{{ __('site.verification.email_expiry_notice') }}</p>
</body>
</html>
