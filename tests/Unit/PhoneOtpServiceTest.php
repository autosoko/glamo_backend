<?php

namespace Tests\Unit;

use App\Services\BeemOtp;
use App\Services\BeemSms;
use App\Services\PhoneOtpService;
use Mockery;
use Tests\TestCase;

class PhoneOtpServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_issue_returns_beem_pin_when_otp_api_succeeds(): void
    {
        config()->set('beem.api_key', 'api123');
        config()->set('beem.secret_key', 'sec456');
        config()->set('app.debug', false);

        $beemOtp = Mockery::mock(BeemOtp::class);
        $beemSms = Mockery::mock(BeemSms::class);

        $beemOtp->shouldReceive('requestPin')
            ->once()
            ->with('255700001800')
            ->andReturn('pin-123');
        $beemOtp->shouldReceive('ttlMinutes')
            ->once()
            ->andReturn(5);
        $beemSms->shouldNotReceive('sendOtp');

        $service = new PhoneOtpService($beemOtp, $beemSms);
        $result = $service->issue('255700001800');

        $this->assertTrue($result['ok']);
        $this->assertSame('beem', $result['provider']);
        $this->assertSame('pin-123', $result['value']);
        $this->assertSame(300, $result['ttl_seconds']);
        $this->assertNull($result['debug_otp']);
    }

    public function test_issue_uses_sms_fallback_when_otp_api_fails(): void
    {
        config()->set('beem.api_key', 'api123');
        config()->set('beem.secret_key', 'sec456');
        config()->set('app.debug', false);

        $beemOtp = Mockery::mock(BeemOtp::class);
        $beemSms = Mockery::mock(BeemSms::class);

        $beemOtp->shouldReceive('requestPin')
            ->once()
            ->with('255700001800')
            ->andReturnNull();
        $beemSms->shouldReceive('sendOtp')
            ->once()
            ->with('255700001800', Mockery::on(fn ($otp) => is_string($otp) && preg_match('/^\d{6}$/', $otp) === 1))
            ->andReturnTrue();

        $service = new PhoneOtpService($beemOtp, $beemSms);
        $result = $service->issue('255700001800');

        $this->assertTrue($result['ok']);
        $this->assertSame('local', $result['provider']);
        $this->assertSame(300, $result['ttl_seconds']);
        $this->assertNull($result['debug_otp']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result['value']);
    }

    public function test_issue_uses_local_debug_fallback_when_delivery_fails(): void
    {
        config()->set('beem.api_key', 'api123');
        config()->set('beem.secret_key', 'sec456');
        config()->set('app.debug', true);

        $beemOtp = Mockery::mock(BeemOtp::class);
        $beemSms = Mockery::mock(BeemSms::class);

        $beemOtp->shouldReceive('requestPin')
            ->once()
            ->with('255700001800')
            ->andReturnNull();
        $beemSms->shouldReceive('sendOtp')
            ->once()
            ->with('255700001800', Mockery::on(fn ($otp) => is_string($otp) && preg_match('/^\d{6}$/', $otp) === 1))
            ->andReturnFalse();

        $service = new PhoneOtpService($beemOtp, $beemSms);
        $result = $service->issue('255700001800');

        $this->assertTrue($result['ok']);
        $this->assertSame('local', $result['provider']);
        $this->assertSame(300, $result['ttl_seconds']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result['value']);
        $this->assertSame($result['value'], $result['debug_otp']);
    }
}
