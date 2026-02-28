<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BookingWindow
{
    /**
     * @return array{open: bool, timezone: string, now: CarbonImmutable, start_minutes: int, end_minutes: int}
     */
    public static function status(?CarbonInterface $now = null): array
    {
        $timezone = trim((string) config('glamo_pricing.booking_timezone', 'Africa/Dar_es_Salaam'));
        if ($timezone === '') {
            $timezone = 'Africa/Dar_es_Salaam';
        }

        $startMinutes = self::parseToMinutes((string) config('glamo_pricing.booking_start_time', '05:00'), 5 * 60);
        $endMinutes = self::parseToMinutes((string) config('glamo_pricing.booking_end_time', '20:59'), (20 * 60) + 59);

        $current = $now
            ? CarbonImmutable::instance($now)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        $nowMinutes = (((int) $current->hour) * 60) + ((int) $current->minute);

        // Supports both same-day windows (start <= end) and overnight windows.
        $open = $startMinutes <= $endMinutes
            ? ($nowMinutes >= $startMinutes && $nowMinutes <= $endMinutes)
            : ($nowMinutes >= $startMinutes || $nowMinutes <= $endMinutes);

        return [
            'open' => $open,
            'timezone' => $timezone,
            'now' => $current,
            'start_minutes' => $startMinutes,
            'end_minutes' => $endMinutes,
        ];
    }

    public static function isOpenNow(?CarbonInterface $now = null): bool
    {
        return (bool) (self::status($now)['open'] ?? false);
    }

    public static function closedMessage(): string
    {
        $msg = trim((string) config('glamo_pricing.booking_closed_message', ''));
        if ($msg !== '') {
            return $msg;
        }

        return 'Fanya booking mapema kuanzia asubuhi saa 11 hadi usiku saa 2.';
    }

    private static function parseToMinutes(string $value, int $fallback): int
    {
        $value = trim($value);
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
            return $fallback;
        }

        $hour = (int) ($m[1] ?? 0);
        $minute = (int) ($m[2] ?? 0);

        return ($hour * 60) + $minute;
    }
}

