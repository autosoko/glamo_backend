<x-emails.glamo-layout
    title="OTP ya Glamo"
    preheader="OTP yako ya kuthibitisha akaunti ya Glamo."
    :button-text="'Fungua Glamo'"
    :button-url="rtrim((string) config('services.glamo.website_url', 'https://getglamo.com'), '/')"
>
    <p style="margin:0 0 10px;">Tumia OTP hii kuthibitisha akaunti yako:</p>

    <div style="display:inline-block;padding:12px 16px;border-radius:12px;background:#f3f4f6;font-weight:800;font-size:20px;letter-spacing:2px;">
        {{ $otp }}
    </div>

    <p style="margin:12px 0 0;">OTP inaisha ndani ya dakika 5.</p>
</x-emails.glamo-layout>
