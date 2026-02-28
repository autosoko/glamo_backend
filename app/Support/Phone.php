<?php

namespace App\Support;

class Phone
{
    /**
     * Normalize a Tanzania phone number to MSISDN digits without "+" (e.g. 07xxxx -> 2557xxxx).
     * Returns null when format is not supported.
     */
    public static function normalizeTzMsisdn(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input);
        $digits = is_string($digits) ? $digits : '';

        if ($digits === '') {
            return null;
        }

        // 07xxxxxxxx / 06xxxxxxxx -> 2557xxxxxxxx / 2556xxxxxxxx
        if (preg_match('/^0(6|7)\\d{8}$/', $digits)) {
            return '255' . substr($digits, 1);
        }

        // 2557xxxxxxxx / 2556xxxxxxxx
        if (preg_match('/^255(6|7)\\d{8}$/', $digits)) {
            return $digits;
        }

        // 7xxxxxxxx / 6xxxxxxxx -> 2557xxxxxxxx / 2556xxxxxxxx
        if (preg_match('/^(6|7)\\d{8}$/', $digits)) {
            return '255' . $digits;
        }

        return null;
    }
}

