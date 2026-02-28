<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SuspendedOrderService
{
    public function __construct(private readonly BeemSms $beemSms)
    {
    }

    public function processDueSchedules(int $limit = 100): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }

        $orderIds = Order::query()
            ->where('status', 'suspended')
            ->whereNotNull('suspended_until_at')
            ->where('suspended_until_at', '<=', now())
            ->whereNull('schedule_notified_at')
            ->orderBy('suspended_until_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $processed = 0;

        foreach ($orderIds as $orderId) {
            $payload = $this->activateDueOrder($orderId);
            if (!$payload) {
                continue;
            }

            $processed++;
            $this->notifyScheduleDue($payload);
        }

        return $processed;
    }

    private function activateDueOrder(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($orderId) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if (!$order) {
                return null;
            }

            if ((string) ($order->status ?? '') !== 'suspended') {
                return null;
            }

            $untilAt = $this->toCarbon($order->suspended_until_at ?? null);
            if (!$untilAt || $untilAt->gt(now())) {
                return null;
            }

            if (!empty($order->schedule_notified_at)) {
                return null;
            }

            $provider = Provider::query()->whereKey((int) $order->provider_id)->lockForUpdate()->first();
            if (!$provider) {
                return null;
            }

            $now = now();
            $order->update([
                'status' => 'accepted',
                'resumed_at' => $now,
                'schedule_notified_at' => $now,
            ]);

            $provider->update([
                'online_status' => 'offline',
                'offline_reason' => 'Ratiba ya oda ' . (string) ($order->order_no ?? '') . ' imefika. Kamilisha oda hii kwanza.',
            ]);

            $order->loadMissing(['client', 'provider.user', 'service']);

            $providerName = trim((string) data_get($order, 'provider.display_name'));
            if ($providerName === '') {
                $providerName = trim((string) data_get($order, 'provider.user.name'));
            }
            if ($providerName === '') {
                $providerName = 'Mtoa huduma';
            }

            return [
                'order_id' => (int) $order->id,
                'order_no' => (string) ($order->order_no ?? ''),
                'service_name' => trim((string) data_get($order, 'service.name')),
                'provider_id' => (int) data_get($order, 'provider.id'),
                'provider_name' => $providerName,
                'provider_phone' => trim((string) (data_get($order, 'provider.user.phone') ?: data_get($order, 'provider.phone_public'))),
                'provider_email' => trim((string) data_get($order, 'provider.user.email')),
                'client_id' => (int) data_get($order, 'client.id'),
                'client_name' => trim((string) data_get($order, 'client.name')),
                'client_phone' => trim((string) data_get($order, 'client.phone')),
                'scheduled_time' => $untilAt->format('d/m/Y H:i'),
            ];
        });
    }

    private function notifyScheduleDue(array $payload): void
    {
        $orderNo = trim((string) ($payload['order_no'] ?? ''));
        $service = trim((string) ($payload['service_name'] ?? ''));
        $providerName = trim((string) ($payload['provider_name'] ?? 'Mtoa huduma'));
        $providerFirst = $this->firstName($providerName);
        $clientName = trim((string) ($payload['client_name'] ?? 'Mteja'));
        $clientFirst = $this->firstName($clientName);

        $providerPhone = trim((string) ($payload['provider_phone'] ?? ''));
        if ($providerPhone !== '') {
            $providerSms = 'Glamo: Ratiba ya oda ' . $orderNo . ' imefika sasa.'
                . ($service !== '' ? ' Huduma: ' . $service . '.' : '')
                . ' Fungua dashboard ukamilishe oda hii.';

            $sent = $this->beemSms->sendMessage(
                $providerPhone,
                $this->limitSms($providerSms),
                (int) ($payload['provider_id'] ?? 1)
            );

            if (!$sent) {
                Log::warning('Suspended order provider SMS failed', [
                    'order_id' => (int) ($payload['order_id'] ?? 0),
                    'provider_id' => (int) ($payload['provider_id'] ?? 0),
                ]);
            }
        }

        $clientPhone = trim((string) ($payload['client_phone'] ?? ''));
        if ($clientPhone !== '') {
            $clientSms = 'Glamo: Oda ' . $orderNo . ' imeendelea kama ulivyopanga.'
                . ' ' . $providerFirst . ' amearifiwa na atakuhudumia muda wowote.'
                . ' Asante ' . $clientFirst . '.';

            $sent = $this->beemSms->sendMessage(
                $clientPhone,
                $this->limitSms($clientSms),
                (int) ($payload['client_id'] ?? 1)
            );

            if (!$sent) {
                Log::warning('Suspended order client SMS failed', [
                    'order_id' => (int) ($payload['order_id'] ?? 0),
                    'client_id' => (int) ($payload['client_id'] ?? 0),
                ]);
            }
        }

        $providerEmail = strtolower(trim((string) ($payload['provider_email'] ?? '')));
        if ($providerEmail !== '') {
            $subject = 'Glamo: Ratiba ya oda ' . $orderNo . ' imefika';
            $body = "Habari {$providerName},\n\n"
                . "Ratiba ya oda {$orderNo} imefika sasa.\n"
                . ($service !== '' ? "Huduma: {$service}\n" : '')
                . "Tafadhali fungua dashibodi ya mtoa huduma na ukamilishe oda hii.\n\n"
                . "Asante,\nGlamo";

            try {
                Mail::raw($body, function ($message) use ($providerEmail, $subject) {
                    $message->to($providerEmail)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::warning('Suspended order provider email failed', [
                    'order_id' => (int) ($payload['order_id'] ?? 0),
                    'provider_id' => (int) ($payload['provider_id'] ?? 0),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function firstName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Mtoa huduma';
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        return (string) ($parts[0] ?? $name);
    }

    private function limitSms(string $message, int $maxLength = 150): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $message));
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(substr($text, 0, max(1, $maxLength - 3))) . '...';
    }

    private function schemaReady(): bool
    {
        if (!Schema::hasTable('orders') || !Schema::hasTable('providers')) {
            return false;
        }

        return Schema::hasColumn('orders', 'suspended_until_at')
            && Schema::hasColumn('orders', 'schedule_notified_at')
            && Schema::hasColumn('orders', 'resumed_at')
            && Schema::hasColumn('providers', 'online_status')
            && Schema::hasColumn('providers', 'offline_reason');
    }
}
