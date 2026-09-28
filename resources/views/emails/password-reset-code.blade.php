<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body>
    <p>{{ __('site.password_reset.email_intro') }}</p>
    <p style="font-size: 2rem; font-weight: 700; letter-spacing: 0.25rem;">{{ $resetCode }}</p>
    <p>{{ __('site.password_reset.email_expiry_notice') }}</p>
    <p>{{ __('site.password_reset.email_ignore_notice') }}</p>
</body>
</html>
