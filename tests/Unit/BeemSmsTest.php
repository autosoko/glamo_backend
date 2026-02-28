<?php

namespace Tests\Unit;

use App\Services\BeemSms;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BeemSmsTest extends TestCase
{
    public function test_send_message_trims_credentials_and_sender_id(): void
    {
        config()->set('beem.api_key', '  "api123"  ');
        config()->set('beem.secret_key', "  'sec456'  ");
        config()->set('beem.sender_id', '  "Glamo"  ');
        config()->set('beem.sms_url', 'https://apisms.beem.africa/v1/send');

        Http::fake([
            'apisms.beem.africa/v1/send' => Http::response([
                'message' => 'OK',
            ], 200),
        ]);

        $service = new BeemSms();
        $sent = $service->sendMessage('0712345678', 'Habari', 7);

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            $expectedAuth = 'Basic ' . base64_encode('api123:sec456');
            $authHeader = $request->header('Authorization')[0] ?? null;

            return $request->url() === 'https://apisms.beem.africa/v1/send'
                && $authHeader === $expectedAuth
                && $request['source_addr'] === 'Glamo'
                && $request['recipients'][0]['recipient_id'] === 7
                && $request['recipients'][0]['dest_addr'] === '255712345678';
        });
    }
}
