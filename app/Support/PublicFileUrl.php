<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicFileUrl
{
    private static array $existingPathCache = [];

    public static function normalizePath(string $path): string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');

        foreach (['public/', 'storage/'] as $prefix) {
            if (Str::startsWith($normalized, $prefix)) {
                $normalized = ltrim((string) Str::after($normalized, $prefix), '/');
            }
        }

        return $normalized;
    }

    public static function url(?string $path, ?string $fallback = null): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || Str::startsWith($path, 'livewire-file:')) {
            return $fallback;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $normalized = self::normalizePath($path);
        if ($normalized === '') {
            return $fallback;
        }

        return asset('media/' . $normalized);
    }

    public static function existingUrl(?string $path, ?string $fallback = null): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || Str::startsWith($path, 'livewire-file:')) {
            return $fallback;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $normalized = self::normalizePath($path);
        if ($normalized === '') {
            return $fallback;
        }

        $exists = self::$existingPathCache[$normalized] ?? null;

        if ($exists === null) {
            $exists = Storage::disk('public')->exists($normalized);
            self::$existingPathCache[$normalized] = $exists;
        }

        if (!$exists) {
            return $fallback;
        }

        return asset('media/' . $normalized);
    }
}
