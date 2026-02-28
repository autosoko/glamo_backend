<?php

namespace App\Support;

class CheckoutPayment
{
    public static function externalReference(string $token): string
    {
        return 'checkout-' . self::normalizeToken($token);
    }

    public static function pendingKey(string $token): string
    {
        return 'checkout-payment:pending:' . self::normalizeToken($token);
    }

    public static function referenceKey(string $reference): string
    {
        return 'checkout-payment:reference:' . strtolower(trim($reference));
    }

    public static function resultKey(string $token): string
    {
        return 'checkout-payment:result:' . self::normalizeToken($token);
    }

    public static function tokenFromExternalReference(string $externalReference): ?string
    {
        $externalReference = strtolower(trim($externalReference));
        if ($externalReference === '' || !str_starts_with($externalReference, 'checkout-')) {
            return null;
        }

        $token = trim(substr($externalReference, strlen('checkout-')));
        if ($token === '') {
            return null;
        }

        return self::normalizeToken($token);
    }

    private static function normalizeToken(string $token): string
    {
        return strtolower(trim($token));
    }
}
