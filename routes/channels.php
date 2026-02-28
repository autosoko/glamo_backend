<?php

use App\Models\Order;

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = Order::find($orderId);
    if (!$order) return false;

    // client or provider's user can listen
    if ((int) $order->client_id === (int) $user->id) return true;

    $providerUserId = $order->provider?->user_id;
    return (int) $providerUserId === (int) $user->id;
});
