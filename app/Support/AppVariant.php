<?php

namespace App\Support;

class AppVariant
{
    public const CLIENT = 'glamo_client';

    public const PROVIDER = 'glamo_provider';

    public static function normalize(null|string|array $variants): array
    {
        $items = is_array($variants) ? $variants : [$variants];

        return collect($items)
            ->map(fn ($variant) => self::normalizeOne($variant))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeOne(?string $variant): ?string
    {
        $raw = strtolower(trim((string) $variant));

        if ($raw === '') {
            return null;
        }

        return match ($raw) {
            self::CLIENT,
            'glamo',
            'client',
            'customer',
            'consumer',
            'com.beautful.link' => self::CLIENT,

            self::PROVIDER,
            'glamo_pro',
            'glamopro',
            'provider',
            'pro',
            'vendor',
            'com.glamopro.link' => self::PROVIDER,

            default => null,
        };
    }

    public static function fromRole(?string $role): ?string
    {
        return match (strtolower(trim((string) $role))) {
            'client' => self::CLIENT,
            'provider' => self::PROVIDER,
            default => null,
        };
    }

    public static function definitions(): array
    {
        return [
            self::CLIENT => [
                'key' => self::CLIENT,
                'name' => 'Glamo',
                'package_id' => (string) config('services.glamo.client.package_id', 'com.beautful.link'),
                'play_store_url' => (string) config('services.glamo.client.play_store_url', config('services.glamo.play_store_url', '')),
                'app_store_url' => (string) config('services.glamo.client.app_store_url', config('services.glamo.app_store_url', '')),
            ],
            self::PROVIDER => [
                'key' => self::PROVIDER,
                'name' => 'Glamo Pro',
                'package_id' => (string) config('services.glamo.provider.package_id', 'com.glamopro.link'),
                'play_store_url' => (string) config('services.glamo.provider.play_store_url', config('services.glamo.play_store_url', '')),
                'app_store_url' => (string) config('services.glamo.provider.app_store_url', config('services.glamo.app_store_url', '')),
            ],
        ];
    }
}
