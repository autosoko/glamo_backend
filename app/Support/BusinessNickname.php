<?php

namespace App\Support;

use App\Models\Provider;

class BusinessNickname
{
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function isTaken(string $nickname, ?int $ignoreProviderId = null): bool
    {
        $normalized = self::normalize($nickname);
        if ($normalized === '') {
            return false;
        }

        return self::nicknameExists($normalized, $ignoreProviderId);
    }

    /**
     * @return array<int, string>
     */
    public static function suggestions(string $nickname, ?int $ignoreProviderId = null, int $limit = 3): array
    {
        $base = self::normalize($nickname);
        if ($base === '' || $limit <= 0) {
            return [];
        }

        $cleanBase = preg_replace('/[^\p{L}\p{N}\s\-]+/u', '', $base) ?? $base;
        $cleanBase = self::normalize($cleanBase);
        if ($cleanBase === '') {
            $cleanBase = 'Business';
        }

        $candidates = [
            $cleanBase . ' Official',
            $cleanBase . ' Pro',
            $cleanBase . ' TZ',
        ];

        for ($i = 1; $i <= 40; $i++) {
            $candidates[] = $cleanBase . ' ' . $i;
            $candidates[] = $cleanBase . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        }

        $out = [];
        foreach ($candidates as $candidate) {
            $candidate = self::normalize($candidate);
            if ($candidate === '') {
                continue;
            }

            if (self::lower($candidate) === self::lower($base)) {
                continue;
            }

            if (in_array($candidate, $out, true)) {
                continue;
            }

            if (! self::nicknameExists($candidate, $ignoreProviderId)) {
                $out[] = $candidate;
            }

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private static function nicknameExists(string $nickname, ?int $ignoreProviderId = null): bool
    {
        $normalized = self::normalize($nickname);
        if ($normalized === '') {
            return false;
        }

        $query = Provider::query()
            ->whereNotNull('business_nickname')
            ->whereRaw("TRIM(business_nickname) <> ''")
            ->whereRaw('LOWER(TRIM(business_nickname)) = ?', [self::lower($normalized)]);

        if ($ignoreProviderId !== null) {
            $query->where('id', '!=', (int) $ignoreProviderId);
        }

        return $query->exists();
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
