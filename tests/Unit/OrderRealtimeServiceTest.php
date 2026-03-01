<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderRealtimeService;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class OrderRealtimeServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_dispatch_status_changed_swallows_dispatcher_exceptions(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('Socket down'));

        $service = new OrderRealtimeService($dispatcher);
        $order = new Order([
            'id' => 99,
            'status' => 'cancelled',
            'order_no' => 'GL-RT-001',
        ]);

        $service->dispatchStatusChanged($order, [
            'target_screen' => 'home',
            'clear_active_order' => true,
        ]);

        $this->assertTrue(true);
    }
}
