<?php

namespace Tests\Unit;

use App\Services\BeemOtp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BeemOtpTest extends TestCase
{
    public function test_request_pin_returns_pin_id_on_success(): void
    {
        config()->set('beem.api_key', 'api123');
        config()->set('beem.secret_key', 'sec456');
        config()->set('beem.otp.app_id', 1);
        config()->set('beem.otp.request_url', 'https://apiotp.beem.africa/v1/request');

        Http::fake([
            'apiotp.beem.africa/v1/request' => Http::response([
                'data' => [
                    'pinId' => 'pin-123',
                    'message' => [
                        'code' => 100,
                        'message' => 'SMS sent successfully',
                    ],
                ],
            ], 200),
        ]);

        $service = new BeemOtp();
        $pinId = $service->requestPin('+255 700 001 800');

        $this->assertSame('pin-123', $pinId);

        Http::assertSent(function ($request) {
            $expectedAuth = 'Basic '.base64_encode('api123:sec456');
            $authHeader = $request->header('Authorization')[0] ?? null;

            return $request->url() === 'https://apiotp.beem.africa/v1/request'
                && $authHeader === $expectedAuth
                && $request['appId'] === 1
                && $request['msisdn'] === '255700001800';
        });
    }

    public function test_verify_pin_returns_true_when_code_is_117(): void
    {
        config()->set('beem.api_key', 'api123');
        config()->set('beem.secret_key', 'sec456');
        config()->set('beem.otp.verify_url', 'https://apiotp.beem.africa/v1/verify');

        Http::fake([
            'apiotp.beem.africa/v1/verify' => Http::response([
                'data' => [
                    'message' => [
                        'code' => 117,
                        'message' => 'Valid Pin',
                    ],
                ],
            ], 200),
        ]);

        $service = new BeemOtp();
        $isValid = $service->verifyPin('pin-123', '231663');

        $this->assertTrue($isValid);
    }

    public function test_verify_pin_returns_false_when_code_is_not_117(): void
    {
        config()->set('beem.api_key', 'api123');
        config()->set('beem.secret_key', 'sec456');
        config()->set('beem.otp.verify_url', 'https://apiotp.beem.africa/v1/verify');

        Http::fake([
            'apiotp.beem.africa/v1/verify' => Http::response([
                'data' => [
                    'message' => [
                        'code' => 114,
                        'message' => 'Invalid Pin',
                    ],
                ],
            ], 200),
        ]);

        $service = new BeemOtp();
        $isValid = $service->verifyPin('pin-123', '000000');

        $this->assertFalse($isValid);
    }
}
