<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SnippePay
{
    public function webhookUrl(): string
    {
        $configured = trim((string) config('snippe.webhook_url', ''));
        if ($configured !== '') {
            return $this->normalizeWebhookUrl($configured);
        }

        $appUrl = trim((string) config('app.url', ''));
        if ($appUrl !== '') {
            return $this->normalizeWebhookUrl(rtrim($appUrl, '/') . '/webhooks/snippe');
        }

        return $this->normalizeWebhookUrl((string) url('/webhooks/snippe'));
    }

    private function client(): PendingRequest
    {
        $timeout = (int) config('snippe.timeout', 30);
        if ($timeout <= 0) {
            $timeout = 30;
        }

        $baseUrl = rtrim((string) config('snippe.base_url', ''), '/');
        $apiKey = (string) config('snippe.api_key', '');

        if ($baseUrl === '') {
            throw new \RuntimeException('Snippe base URL missing. Set SNIPPE_BASE_URL.');
        }
        if ($apiKey === '') {
            throw new \RuntimeException('Snippe API key missing. Set SNIPPE_API_KEY.');
        }

        return Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->baseUrl($baseUrl)
            ->withToken($apiKey);
    }

    public function createPayment(array $payload, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $res = $this->client()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/v1/payments', $payload);

        if (!$res->successful()) {
            Log::warning('Snippe create payment failed', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            throw new \RuntimeException($this->errorMessage($res, 'Imeshindikana kuanzisha malipo. Jaribu tena.'));
        }

        return (array) $res->json();
    }

    public function getPayment(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new \RuntimeException('Missing payment reference.');
        }

        $res = $this->client()->get('/v1/payments/' . urlencode($reference));

        if (!$res->successful()) {
            Log::warning('Snippe get payment failed', [
                'status' => $res->status(),
                'body' => $res->body(),
                'reference' => $reference,
            ]);
            throw new \RuntimeException($this->errorMessage($res, 'Imeshindikana kuangalia status ya malipo. Jaribu tena.'));
        }

        return (array) $res->json();
    }

    public function createPaymentSession(array $payload, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $res = $this->client()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/v1/payment-sessions', $payload);

        if (!$res->successful()) {
            Log::warning('Snippe create payment session failed', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            throw new \RuntimeException($this->errorMessage($res, 'Imeshindikana kuanzisha session ya malipo. Jaribu tena.'));
        }

        return (array) $res->json();
    }

    public function getPaymentSession(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new \RuntimeException('Missing payment session reference.');
        }

        $res = $this->client()->get('/v1/payment-sessions/' . urlencode($reference));

        if (!$res->successful()) {
            Log::warning('Snippe get payment session failed', [
                'status' => $res->status(),
                'body' => $res->body(),
                'reference' => $reference,
            ]);
            throw new \RuntimeException($this->errorMessage($res, 'Imeshindikana kuangalia status ya payment session. Jaribu tena.'));
        }

        return (array) $res->json();
    }

    public function createPayout(array $payload, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = trim((string) $idempotencyKey);
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $res = $this->client()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/v1/payouts/send', $payload);

        if (!$res->successful()) {
            Log::warning('Snippe create payout failed', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            throw new \RuntimeException($this->errorMessage($res, 'Imeshindikana kuanzisha payout. Jaribu tena.'));
        }

        return (array) $res->json();
    }

    public function getPayout(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new \RuntimeException('Missing payout reference.');
        }

        $res = $this->client()->get('/v1/payouts/' . urlencode($reference));

        if (!$res->successful()) {
            Log::warning('Snippe get payout failed', [
                'status' => $res->status(),
                'body' => $res->body(),
                'reference' => $reference,
            ]);
            throw new \RuntimeException($this->errorMessage($res, 'Imeshindikana kuangalia status ya payout. Jaribu tena.'));
        }

        return (array) $res->json();
    }

    private function normalizeWebhookUrl(string $webhookUrl): string
    {
        $webhookUrl = trim($webhookUrl);
        if ($webhookUrl === '') {
            return $webhookUrl;
        }

        if (!preg_match('#^https?://#i', $webhookUrl)) {
            $webhookUrl = 'https://' . ltrim($webhookUrl, '/');
        }

        if (str_starts_with(strtolower($webhookUrl), 'http://')) {
            $webhookUrl = 'https://' . substr($webhookUrl, 7);
        }

        return $webhookUrl;
    }

    private function errorMessage(Response $response, string $fallback): string
    {
        $payload = (array) $response->json();

        $parts = [
            trim((string) data_get($payload, 'message', '')),
            trim((string) data_get($payload, 'error.message', '')),
            trim((string) data_get($payload, 'error', '')),
            trim((string) data_get($payload, 'error_code', '')),
        ];

        foreach ($parts as $part) {
            if ($part !== '') {
                return $fallback . ' (' . $part . ')';
            }
        }

        return $fallback;
    }
}
