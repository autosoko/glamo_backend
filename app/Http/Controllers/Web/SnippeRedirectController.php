<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\CheckoutPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SnippeRedirectController extends Controller
{
    public function done(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login', ['redirect' => $request->getRequestUri()]);
        }

        $checkoutToken = strtolower(trim((string) $request->query('checkout_token', '')));
        if ($checkoutToken !== '') {
            $orderId = (int) Cache::get(CheckoutPayment::resultKey($checkoutToken), 0);
            if ($orderId > 0) {
                $order = Order::query()
                    ->whereKey($orderId)
                    ->where('client_id', (int) $user->id)
                    ->first();

                if ($order) {
                    return redirect()->route('orders.show', ['order' => $order->id])
                        ->with('success', 'Malipo yamethibitishwa na oda imeundwa.');
                }
            }
        }

        $active = Order::query()
            ->where('client_id', (int) $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest()
            ->first();

        if ($active) {
            return redirect()->route('orders.show', ['order' => $active->id])
                ->with('success', 'Tuna-thibitisha malipo yako. Subiri kidogo.');
        }

        $category = trim((string) $request->query('category', ''));
        $service = trim((string) $request->query('service', ''));
        if ($checkoutToken !== '' && $category !== '' && $service !== '') {
            return redirect()->route('services.pay', [
                'category' => $category,
                'service' => $service,
            ])->with('success', 'Tuna-thibitisha malipo yako. Subiri sekunde chache kisha refresh.');
        }

        return redirect()->route('landing')->with('success', 'Asante. Malipo yamekamilika.');
    }

    public function cancel(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login', ['redirect' => $request->getRequestUri()]);
        }

        $checkoutToken = strtolower(trim((string) $request->query('checkout_token', '')));
        $category = trim((string) $request->query('category', ''));
        $service = trim((string) $request->query('service', ''));

        if ($checkoutToken !== '' && $category !== '' && $service !== '') {
            return redirect()->route('services.pay', [
                'category' => $category,
                'service' => $service,
            ])->with('error', 'Malipo hayajakamilika. Jaribu tena.');
        }

        $active = Order::query()
            ->where('client_id', (int) $user->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest()
            ->first();

        if ($active) {
            return redirect()->route('orders.show', ['order' => $active->id])
                ->with('error', 'Malipo hayajakamilika. Unaweza kujaribu tena au u-cancel oda.');
        }

        return redirect()->route('landing')->with('error', 'Malipo hayajakamilika.');
    }
}
