@props([
    'title' => 'Glamo',
    'preheader' => '',
    'buttonText' => null,
    'buttonUrl' => null,
])

@php
    $websiteUrl = rtrim((string) config('services.glamo.website_url', 'https://getglamo.com'), '/');
    $appStoreUrl = (string) config('services.glamo.app_store_url', 'https://apps.apple.com/');
    $playStoreUrl = (string) config('services.glamo.play_store_url', 'https://play.google.com/store');
    $supportEmail = (string) config('services.glamo.support_email', 'info@getglamo.com');
    $logoUrl = asset('images/logo.png');
@endphp
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;">
    <div style="display:none;opacity:0;visibility:hidden;overflow:hidden;height:0;width:0;max-height:0;max-width:0;">
        {{ $preheader }}
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="640" style="width:100%;max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 24px 16px;background:linear-gradient(135deg,#5a0e24 0%,#7a1431 100%);">
                            <a href="{{ $websiteUrl }}" target="_blank" style="text-decoration:none;display:inline-block;">
                                <img src="{{ $logoUrl }}" alt="Glamo" style="height:34px;width:auto;display:block;">
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            <h1 style="margin:0 0 12px;font-size:24px;line-height:1.3;color:#111827;font-family:Arial,Helvetica,sans-serif;">
                                {{ $title }}
                            </h1>

                            <div style="font-size:15px;line-height:1.7;color:#374151;font-family:Arial,Helvetica,sans-serif;">
                                {{ $slot }}
                            </div>

                            @if(filled($buttonText) && filled($buttonUrl))
                                <div style="margin-top:20px;">
                                    <a href="{{ $buttonUrl }}" target="_blank" style="display:inline-block;background:#5a0e24;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 20px;border-radius:10px;font-family:Arial,Helvetica,sans-serif;">
                                        {{ $buttonText }}
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 24px 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="padding:14px 0;border-top:1px solid #e5e7eb;">
                                        <p style="margin:0 0 8px;font-size:12px;color:#6b7280;font-family:Arial,Helvetica,sans-serif;">
                                            Pakua app ya Glamo:
                                        </p>
                                        <a href="{{ $appStoreUrl }}" target="_blank" style="display:inline-block;margin-right:8px;">
                                            <img src="https://developer.apple.com/assets/elements/badges/download-on-the-app-store.svg" alt="App Store" style="height:40px;width:auto;display:block;">
                                        </a>
                                        <a href="{{ $playStoreUrl }}" target="_blank" style="display:inline-block;">
                                            <img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Google Play" style="height:40px;width:auto;display:block;">
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <p style="margin:0;font-size:12px;line-height:1.6;color:#6b7280;font-family:Arial,Helvetica,sans-serif;">
                                            Website:
                                            <a href="{{ $websiteUrl }}" target="_blank" style="color:#5a0e24;text-decoration:none;">{{ $websiteUrl }}</a>
                                            <br>
                                            Msaada:
                                            <a href="mailto:{{ $supportEmail }}" style="color:#5a0e24;text-decoration:none;">{{ $supportEmail }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

